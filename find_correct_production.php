<?php
$pdo = new PDO('mysql:host=127.0.0.1:3306;dbname=controlepcp_sandbox;charset=utf8mb4','root','k7m2y9u4');

echo "=== SOMA CORRETA DE PRODUÇÃO 27/03-28/03 ===\n\n";

// Buscar apenas os que têm sku (não são setup)
$sql = "
SELECT 
    SUM(CAST(sch_quantidade AS DECIMAL(10,2))) as total,
    COUNT(*) as count
FROM sch_linhas
WHERE sch_programa_id IN (
    SELECT prg_programa_id FROM prg_itens WHERE prg_itens_op = '201055'
)
  AND sch_data_inicio >= '2026-03-27'
  AND sch_data_inicio <= '2026-03-28'
  AND sch_sku IS NOT NULL
  AND sch_sku != ''
";

$result = $pdo->query($sql);
$row = $result->fetch(PDO::FETCH_ASSOC);

echo "Total com SKU (27-28/03): {$row['total']} un ({$row['count']} registros)\n";

// Detalhe por SKU
echo "\n=== DETALHADO POR SKU ===\n";
$sql = "
SELECT 
    sch_sku,
    SUM(CAST(sch_quantidade AS DECIMAL(10,2))) as total,
    COUNT(*) as count
FROM sch_linhas
WHERE sch_programa_id IN (
    SELECT prg_programa_id FROM prg_itens WHERE prg_itens_op = '201055'
)
  AND sch_data_inicio >= '2026-03-27'
  AND sch_data_inicio <= '2026-03-28'
  AND sch_sku IS NOT NULL
  AND sch_sku != ''
GROUP BY sch_sku
";

$result = $pdo->query($sql);
foreach ($result->fetchAll(PDO::FETCH_ASSOC) as $row) {
    echo "SKU {$row['sch_sku']}: {$row['total']} un ({$row['count']} schedules)\n";
}

// Para SKU 20010003 apenas
echo "\n=== DADOS DE ORIGEM PLANEJADO 5000 ===\n";
$sql = "SELECT * FROM prg_itens WHERE prg_itens_op = '201055' AND prg_sku = '20010003'";
$result = $pdo->query($sql);
$row = $result->fetch(PDO::FETCH_ASSOC);
echo json_encode($row, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";

echo "\n=== BUSCANDO ONDE ESTÁ 3734 ===\n";

// Talvez seja uma soma parcial? 
// Se temos 5000 + 6000 planejado, e produzido é 3734, talvez seja só um dia?
echo "\nPor dia:\n";
$sql = "
SELECT 
    DATE(sch_data_inicio) as data,
    sch_sku,
    SUM(CAST(sch_quantidade AS DECIMAL(10,2))) as total
FROM sch_linhas
WHERE sch_programa_id IN (
    SELECT prg_programa_id FROM prg_itens WHERE prg_itens_op = '201055'
)
  AND sch_data_inicio >= '2026-03-27'
  AND sch_data_inicio <= '2026-03-28'
  AND sch_sku IS NOT NULL
  AND sch_sku != ''
GROUP BY DATE(sch_data_inicio), sch_sku
ORDER BY data, sch_sku
";

$result = $pdo->query($sql);
foreach ($result->fetchAll(PDO::FETCH_ASSOC) as $row) {
    echo "Data {$row['data']} | SKU {$row['sch_sku']}: {$row['total']}\n";
}

// Verificar se é um percentual ou cálculo
echo "\n=== POSSIBILIDADES ===\n";
echo "5000 * 0.7468 = " . (5000 * 0.7468) . "\n";
echo "5000 * 0.75 - algo = ?\n";
echo "3734 / 5000 = " . (3734 / 5000) . " (" . ((3734 / 5000) * 100) . "%)\n";
?>
