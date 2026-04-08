<?php
$pdo = new PDO(
    'mysql:host=127.0.0.1:3306;dbname=controlepcp_sandbox;charset=utf8mb4',
    'root',
    'k7m2y9u4'
);

echo "=== ESTRUTURA DE prd_produtos ===\n";
$result = $pdo->query('DESCRIBE prd_produtos');
while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
    echo "- " . $row['Field'] . " (" . $row['Type'] . ")\n";
}

echo "\n=== DADOS DE prd_produtos (primeiros 5) ===\n";
$result = $pdo->query('SELECT * FROM prd_produtos LIMIT 5');
while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
    echo json_encode($row, JSON_UNESCAPED_UNICODE) . "\n";
}

// Buscar produto específico
echo "\n=== Procurar produto com SKU 20160001 ===\n";
$result = $pdo->query("SELECT * FROM prd_produtos WHERE prd_sku LIKE '%20160001%' OR prd_codigo_codi LIKE '%20160001%'");
while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
    echo json_encode($row, JSON_UNESCAPED_UNICODE) . "\n";
}

echo "\n=== Procurar produto com SKU 20010003 ===\n";
$result = $pdo->query("SELECT * FROM prd_produtos WHERE prd_sku LIKE '%20010003%' OR prd_codigo_codi LIKE '%20010003%'");
while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
    echo json_encode($row, JSON_UNESCAPED_UNICODE) . "\n";
}

echo "\n=== Mostrar alguns SKUs e seus CODI ===\n";
$result = $pdo->query('SELECT prd_sku, prd_codigo_codi, prd_nome FROM prd_produtos LIMIT 10');
while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
    echo "SKU: {$row['prd_sku']} | CODI: {$row['prd_codigo_codi']} | Nome: {$row['prd_nome']}\n";
}
