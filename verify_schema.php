<?php
$pdo = new PDO(
    'mysql:host=127.0.0.1:3306;dbname=controlepcp_sandbox;charset=utf8mb4',
    'root',
    'k7m2y9u4'
);

echo "=== VERIFICANDO ESTRUTURA DE TABELAS ===\n\n";

// Ver colunas de sch_linhas
echo "Colunas de sch_linhas:\n";
$stmt = $pdo->query("DESCRIBE sch_linhas");
$cols = $stmt->fetchAll(PDO::FETCH_COLUMN);
echo "- " . implode("\n- ", $cols) . "\n\n";

// Buscar dados da OP 201055
echo "=== DADOS DA OP 201055 ===\n";
$stmt = $pdo->query("
    SELECT p.prg_itens_op, p.prg_sku, p.prg_quantidade, p.prg_id,
           GROUP_CONCAT(DISTINCT s.sch_data_inicio) AS datas
    FROM prg_itens p
    LEFT JOIN sch_linhas s ON p.prg_id = s.sch_prg_id
    WHERE p.prg_itens_op = '201055'
    GROUP BY p.prg_sku
");

$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "Total: " . count($rows) . " SKUs\n\n";

foreach ($rows as $row) {
    echo "SKU: {$row['prg_sku']} | Qtd: {$row['prg_quantidade']} | Datas: {$row['datas']}\n";
}
?>
