<?php
try {
    $pdo = new PDO('mysql:host=127.0.0.1;dbname=controlepcp;charset=utf8mb4', 'root', '');
    
    // 1. Buscar programa
    $stmt = $pdo->query("SELECT prg_programa_id, prg_itens_op, prg_sku, prg_quantidade FROM prg_itens WHERE prg_itens_op IN ('201055', '0201055') LIMIT 1");
    $programa = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$programa) {
        echo "OP não encontrada\n";
        exit(1);
    }
    
    $prog_id = $programa['prg_programa_id'];
    $planejado = $programa['prg_quantidade'];
    
    echo "=== PLANEJADO ===\n";
    echo "Primeira OP encontrada:\n";
    echo "  Programa ID: {$prog_id}\n";
    echo "  OP: {$programa['prg_itens_op']}\n";
    echo "  SKU: {$programa['prg_sku']}\n";
    echo "  Quantidade: {$planejado}\n";
    
    // 2. Buscar schedules do programa
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
    
    echo "\n=== REALIZADO ===\n";
    echo "Período: 2026-03-27 a 2026-03-28\n";
    echo "  Total: {$realizado}\n";
    echo "  Schedules: {$sched['num']}\n";
    echo "  Taxa: {$taxa}%\n";
    
    echo "\n=== RESULTADO ===\n";
    echo "Planejado: {$planejado}\n";
    echo "Realizado: {$realizado}\n";
    echo "Taxa: {$taxa}%\n";
    echo "\n!!! ESPERADO DO USUÁRIO: Planejado 5000, Realizado 3734, Taxa 74.68%\n";
    
} catch (Exception $e) {
    echo "ERRO: " . $e->getMessage() . "\n";
}
?>
