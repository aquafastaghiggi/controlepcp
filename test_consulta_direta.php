<?php
// Script de teste direto da consulta
try {
    $pdo = new PDO(
        'mysql:host=localhost;dbname=controlepcp;charset=utf8mb4',
        'root',
        '',
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
    
    echo "=== PLANEJADO (prg_itens) ===\n";
    $stmt = $pdo->query("
        SELECT prg_programa_id, prg_itens_op, prg_sku, prg_quantidade, prg_data_inicio, prg_data_fim
        FROM prg_itens
        WHERE prg_itens_op IN ('201055', '0201055')
        ORDER BY prg_programa_id
    ");
    
    $programas = $stmt->fetchAll();
    foreach ($programas as $p) {
        echo "  Programa ID: {$p['prg_programa_id']}, OP: {$p['prg_itens_op']}, SKU: {$p['prg_sku']}, Qtd: {$p['prg_quantidade']}\n";
    }
    
    if (empty($programas)) {
        echo "  Nenhum programa encontrado!\n";
        exit(1);
    }
    
    // Usar o primeiro programa
    $primeiro = $programas[0];
    $prg_programa_id = $primeiro['prg_programa_id'];
    $planejado = (float)$primeiro['prg_quantidade'];
    
    echo "\n=== REALIZADO (sch_linhas) para Programa ID: {$prg_programa_id} ===\n";
    $stmt = $pdo->prepare("
        SELECT 
            SUM(sch_quantidade) as total,
            COUNT(*) as num,
            GROUP_CONCAT(DISTINCT sch_sku) as skus,
            MIN(DATE(sch_data_inicio)) as min_data,
            MAX(DATE(sch_data_inicio)) as max_data
        FROM sch_linhas
        WHERE prg_programa_id = ?
        AND DATE(sch_data_inicio) BETWEEN '2026-03-27' AND '2026-03-28'
    ");
    $stmt->execute([$prg_programa_id]);
    $realizado_row = $stmt->fetch();
    
    $realizado = $realizado_row['total'] ? (float)$realizado_row['total'] : 0.0;
    $taxa = $planejado > 0 ? round(($realizado / $planejado) * 100, 2) : 0;
    
    echo "  Total Realizado: {$realizado} un\n";
    echo "  Num Schedules: {$realizado_row['num']}\n";
    echo "  SKUs: {$realizado_row['skus']}\n";
    echo "  Datas: {$realizado_row['min_data']} a {$realizado_row['max_data']}\n";
    echo "  Taxa de Execução: {$taxa}%\n";
    
    echo "\n=== RESUMO ===\n";
    echo "  Planejado: {$planejado} un\n";
    echo "  Realizado: {$realizado} un\n";
    echo "  Taxa: {$taxa}%\n";
    echo "  Esperado do usuário: 5000 planejado, 3734 realizado (74.68% taxa)\n";
    
} catch (\Exception $e) {
    echo "ERRO: " . $e->getMessage() . "\n";
    exit(1);
}
?>
