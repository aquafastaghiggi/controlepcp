<?php
/**
 * API: Realizado Agrupado por OP
 * 
 * Busca dados de realizado_2026_excel agrupados por OP
 * Filtra por período (semana) se necessário
 * 
 * Parâmetros:
 * - programacao_id: ID da programação (para validar OPs)
 * - data_inicio: YYYY-MM-DD (opcional, padrão: primeira data da prog)
 * - data_fim: YYYY-MM-DD (opcional, padrão: última data da prog)
 */

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

use App\Auth\Auth;
use App\Database\Connection;

header('Content-Type: application/json; charset=utf-8');

// Autenticação (leniente)
try {
    Auth::startSession();
} catch (Exception $ignored) {}

try {
    $programacao_id = (int)($_GET['programacao_id'] ?? 0);
    $data_inicio = $_GET['data_inicio'] ?? null;
    $data_fim = $_GET['data_fim'] ?? null;
    
    if ($programacao_id <= 0) {
        throw new Exception('programacao_id é obrigatória');
    }
    
    $pdo = Connection::get();
    
    // Se não forneceu datas, usar o período da programação
    if (!$data_inicio || !$data_fim) {
        $stmt = $pdo->prepare("
            SELECT MIN(sch_data_inicio) as inicio, MAX(sch_data_inicio) as fim
            FROM sch_linhas
            WHERE sch_programa_id = ?
        ");
        $stmt->execute([$programacao_id]);
        $period = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$period || !$period['inicio']) {
            throw new Exception('Programação sem schedule');
        }
        
        $data_inicio = $data_inicio ?: $period['inicio'];
        $data_fim = $data_fim ?: $period['fim'];
    }
    
    // Buscar OPs da programação (para validar)
    $stmt = $pdo->prepare("
        SELECT DISTINCT pi.prg_itens_op
        FROM prg_itens pi
        WHERE pi.prg_programa_id = ? AND pi.prg_itens_op IS NOT NULL
    ");
    $stmt->execute([$programacao_id]);
    $ops_programa = array_map(fn($row) => (string)$row['prg_itens_op'], $stmt->fetchAll(PDO::FETCH_ASSOC));
    
    // Buscar realizado agrupado por OP no período
    $sql = "
        SELECT 
            ordem_op,
            SUM(quantidade) as total_realizado,
            COUNT(*) as registros,
            MIN(data_evento) as primeira_data,
            MAX(data_evento) as ultima_data
        FROM realizado_2026_excel
        WHERE data_evento >= ? AND data_evento <= ?
    ";
    
    $params = [$data_inicio, $data_fim];
    
    // Se a programação tem OPs específicas, filtrar por elas
    if (!empty($ops_programa)) {
        $placeholders = implode(',', array_fill(0, count($ops_programa), '?'));
        $sql .= " AND ordem_op IN ($placeholders)";
        $params = array_merge($params, $ops_programa);
    }
    
    $sql .= "
        GROUP BY ordem_op
        ORDER BY SUM(quantidade) DESC
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $realizado = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Formatar resposta
    $data = array_map(function($row) {
        return [
            'ordem_op' => (string)$row['ordem_op'],
            'quantidade' => (float)$row['total_realizado'],
            'registros' => (int)$row['registros'],
            'primeira_data' => $row['primeira_data'],
            'ultima_data' => $row['ultima_data']
        ];
    }, $realizado);
    
    // Calcular totais
    $total_realizado = array_sum(array_column($data, 'quantidade'));
    
    http_response_code(200);
    echo json_encode([
        'sucesso' => true,
        'programacao_id' => $programacao_id,
        'periodo' => [
            'inicio' => $data_inicio,
            'fim' => $data_fim
        ],
        'total_realizado' => $total_realizado,
        'registros' => count($data),
        'realizado' => $data
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'sucesso' => false,
        'erro' => $e->getMessage(),
        'debug' => [
            'file' => basename($e->getFile()),
            'line' => $e->getLine()
        ]
    ], JSON_UNESCAPED_UNICODE);
    error_log('API realizado_agrupado error: ' . $e->getMessage());
}
