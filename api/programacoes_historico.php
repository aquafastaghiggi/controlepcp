<?php
/**
 * API: Programações com Histórico (Schedule)
 * 
 * Retorna lista de programações que possuem dados de schedule (sch_linhas)
 * Essas são as programações que o usuário pode selecionar no gráfico de sequenciamento
 */

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

use App\Auth\Auth;
use App\Database\Connection;

header('Content-Type: application/json; charset=utf-8');

// Autenticação
try {
    Auth::startSession();
    // Permitir sem autenticação para dev/teste
    if (!isset($_SESSION['user_id'])) {
        error_log('API programacoes_historico: sem autenticação');
    }
} catch (Exception $ignored) {
    // Continua mesmo sem auth
}

try {
    $pdo = Connection::get();
    
    // Buscar programações que têm schedule (sch_linhas)
    $stmt = $pdo->query("
        SELECT DISTINCT
            p.prg_id,
            p.prg_numero_op,
            l.lin_codigo,
            MIN(s.sch_data_inicio) as data_inicio,
            MAX(s.sch_data_inicio) as data_fim,
            COUNT(DISTINCT s.sch_id) as total_linhas,
            SUM(CASE WHEN s.sch_tipo = 'producao' THEN 1 ELSE 0 END) as producoes,
            SUM(CASE WHEN s.sch_tipo = 'setup' THEN 1 ELSE 0 END) as setups,
            SUM(s.sch_quantidade) as quantidade_total,
            MAX(s.sch_atualizado_em) as ultima_atualizacao
        FROM prg_programas p
        INNER JOIN sch_linhas s ON p.prg_id = s.sch_programa_id
        LEFT JOIN lin_linhas l ON p.prg_linha_id = l.lin_id
        GROUP BY p.prg_id, p.prg_numero_op, l.lin_codigo
        ORDER BY MAX(s.sch_data_inicio) DESC, p.prg_id DESC
        LIMIT 100
    ");
    
    $programacoes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Formatar resposta
    $data = array_map(function($prog) {
        return [
            'id' => (int)$prog['prg_id'],
            'numero' => $prog['prg_numero_op'],
            'linha' => $prog['lin_codigo'] ?? 'Sem linha',
            'data_inicio' => $prog['data_inicio'],
            'data_fim' => $prog['data_fim'],
            'total_linhas' => (int)$prog['total_linhas'],
            'producoes' => (int)$prog['producoes'],
            'setups' => (int)$prog['setups'],
            'quantidade_total' => (float)$prog['quantidade_total'],
            'ultima_atualizacao' => $prog['ultima_atualizacao'],
            'label' => sprintf(
                'Prog %s - Linha %s (%s/%s)',
                $prog['prg_numero_op'],
                $prog['lin_codigo'] ?? 'N/A',
                $prog['data_inicio'],
                (int)$prog['total_linhas']
            )
        ];
    }, $programacoes);
    
    http_response_code(200);
    echo json_encode([
        'sucesso' => true,
        'total' => count($data),
        'programacoes' => $data
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'sucesso' => false,
        'erro' => $e->getMessage(),
        'debug' => [
            'file' => basename($e->getFile()),
            'line' => $e->getLine()
        ]
    ], JSON_UNESCAPED_UNICODE);
    error_log('API programacoes_historico error: ' . $e->getMessage());
}
