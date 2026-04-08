<?php
$pdo = new PDO(
    'mysql:host=127.0.0.1:3306;dbname=controlepcp_sandbox;charset=utf8mb4',
    'root',
    'k7m2y9u4'
);

// Buscar OP 201055
$stmt = $pdo->query("SELECT DISTINCT prg_itens_op FROM prg_itens WHERE prg_itens_op = '201055' LIMIT 1");
$result = $stmt->fetch(PDO::FETCH_ASSOC);

if ($result) {
    echo "✓ OP 201055 existe no banco de dados local\n\n";
    
    // Mostrar detalhes
    $stmt = $pdo->query("SELECT p.prg_itens_op, p.prg_sku, p.prg_quantidade, s.sch_data_ini, s.sch_data_fim
    FROM prg_itens p
    LEFT JOIN sch_linhas s ON p.prg_id = s.sch_prg_id
    WHERE p.prg_itens_op = '201055'
    ORDER BY p.prg_sku");
    
    echo "DETALHES DA OP 201055:\n";
    $count = 0;
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $count++;
        echo "  " . $count . ". SKU: {$row['prg_sku']} | Qtd: {$row['prg_quantidade']} | Período: {$row['sch_data_ini']} até {$row['sch_data_fim']}\n";
    }
} else {
    echo "❌ OP 201055 NÃO existe no banco local\n";
    
    echo "\nPrimeiras 20 OPs disponíveis:\n";
    $stmt = $pdo->query("SELECT DISTINCT prg_itens_op FROM prg_itens ORDER BY prg_itens_op LIMIT 20");
    $ops = $stmt->fetchAll(PDO::FETCH_COLUMN);
    foreach ($ops as $i => $op) {
        echo ($i+1) . ". $op\n";
    }
}
?>
