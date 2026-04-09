<?php
/**
 * Consulta OP 201055 com Planejado vs Realizado
 * GET /api/op_consultation.php?op=201055&data_inicio=2026-03-27&data_fim=2026-03-28
 */

declare(strict_types=1);
require __DIR__ . '/../src/bootstrap.php';

use App\Database\Connection;

header('Content-Type: application/json; charset=utf-8');

try {
    $op = isset($_GET['op']) ? trim((string)$_GET['op']) : '201055';
    $data_inicio = isset($_GET['data_inicio']) ? trim((string)$_GET['data_inicio']) : '2026-03-27';
    $data_fim = isset($_GET['data_fim']) ? trim((string)$_GET['data_fim']) : '2026-03-28';
    
    // Remove leading zeros from OP if present
    $op_clean = ltrim($op, '0') ?: '0';
    
    $pdo = Connection::get();
    
    // ===== PLANEJADO: Buscar apenas 1 prg_programa_id da OP =====
    $stmt = $pdo->prepare("
        SELECT 
            prg_programa_id,
            prg_itens_op,
            prg_sku,
            prg_quantidade,
            prg_data_inicio,
            prg_data_fim
        FROM prg_itens
        WHERE prg_itens_op = ? OR prg_itens_op = ?
        LIMIT 1
    ");
    $stmt->execute([$op_clean, $op]);
    $programa = $stmt->fetch(\PDO::FETCH_ASSOC);
    
    if (!$programa) {
        http_response_code(404);
        echo json_encode(['erro' => "OP {$op} não encontrada"], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    $planejado_quantidade = (float)$programa['prg_quantidade'];
    $prg_programa_id = (int)$programa['prg_programa_id'];
    $sku = $programa['prg_sku'];
    
    // ===== REALIZADO: Somar schedules do período =====
    $stmt = $pdo->prepare("
        SELECT 
            SUM(sch_quantidade) as total_quantidade,
            COUNT(*) as num_schedules,
            GROUP_CONCAT(DISTINCT sch_sku) as skus
        FROM sch_linhas
        WHERE prg_programa_id = ?
        AND DATE(sch_data_inicio) BETWEEN ? AND ?
    ");
    $stmt->execute([$prg_programa_id, $data_inicio, $data_fim]);
    $schedules = $stmt->fetch(\PDO::FETCH_ASSOC);
    
    $realizado_quantidade = $schedules['total_quantidade'] ? (float)$schedules['total_quantidade'] : 0.0;
    
    // ===== CÁLCULO DE TAXA =====
    $taxa_execucao = 0.0;
    if ($planejado_quantidade > 0) {
        $taxa_execucao = ($realizado_quantidade / $planejado_quantidade) * 100;
    }
    
    $response = [
        'op' => $op_clean,
        'sku' => $sku,
        'periodo' => [
            'inicio' => $data_inicio,
            'fim' => $data_fim
        ],
        'planejado' => [
            'quantidade' => $planejado_quantidade,
            'programa_id' => $prg_programa_id,
            'data_planejada' => [
                'inicio' => $programa['prg_data_inicio'],
                'fim' => $programa['prg_data_fim']
            ]
        ],
        'realizado' => [
            'quantidade' => $realizado_quantidade,
            'num_schedules' => (int)$schedules['num_schedules'],
            'taxa_execucao_percent' => round($taxa_execucao, 2)
        ],
        'resumo' => [
            'planejado' => $planejado_quantidade,
            'realizado' => $realizado_quantidade,
            'taxa_percent' => round($taxa_execucao, 2),
            'desvio' => $realizado_quantidade - $planejado_quantidade
        ]
    ];
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    
} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode([
        'erro' => $e->getMessage(),
        'arquivo' => $e->getFile(),
        'linha' => $e->getLine()
    ], JSON_UNESCAPED_UNICODE);
}
?>
