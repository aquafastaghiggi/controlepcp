<?php
// Usar o bootstrap para pegar a conexão correta
require_once __DIR__ . '/src/bootstrap.php';

use App\Database\Connection;

try {
    $pdo = Connection::get();
    
    // 1. Buscar programa
    $stmt = $pdo->query("SELECT prg_programa_id, prg_itens_op, prg_sku, prg_quantidade FROM prg_itens WHERE prg_itens_op IN ('201055', '0201055') LIMIT 1");
    $programa = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$programa) {
        http_response_code(404);
        die(json_encode(['erro' => 'OP não encontrada']));
    }
    
    $prog_id = $programa['prg_programa_id'];
    $planejado = $programa['prg_quantidade'];
    
    // 2. Buscar schedules
    $stmt = $pdo->prepare("
        SELECT 
            SUM(sch_quantidade) as total,
            COUNT(*) as num
        FROM sch_linhas
        WHERE prg_programa_id = ?
        AND DATE(sch_data_inicio) BETWEEN '2026-03-27' AND '2026-03-28'
    ");
    $stmt->execute([$prog_id]);
    $sched = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $realizado = $sched['total'] ? (float)$sched['total'] : 0;
    $taxa = $planejado > 0 ? round(($realizado / $planejado) * 100, 2) : 0;
    
    $resultado = [
        'planejado' => [
            'quantidade' => (float)$planejado,
            'programa_id' => (int)$prog_id,
            'op' => $programa['prg_itens_op'],
            'sku' => $programa['prg_sku']
        ],
        'realizado' => [
            'quantidade' => $realizado,
            'schedules' => (int)$sched['num'],
            'taxa_percent' => $taxa
        ],
        'resumo' => [
            'planejado' => (float)$planejado,
            'realizado' => $realizado,
            'taxa_percent' => $taxa,
            'diferenca' => $realizado - (float)$planejado,
            'esperado' => [
                'planejado' => 5000,
                'realizado' => 3734,
                'taxa_percent' => 74.68
            ]
        ]
    ];
    
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($resultado, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'erro' => $e->getMessage(),
        'arquivo' => $e->getFile(),
        'linha' => $e->getLine()
    ]);
}
?>
