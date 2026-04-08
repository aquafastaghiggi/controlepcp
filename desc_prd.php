<?php
$pdo = new PDO(
    'mysql:host=127.0.0.1:3306;dbname=controlepcp_sandbox;charset=utf8mb4',
    'root',
    'k7m2y9u4'
);

echo "=== Estrutura prd_produtos ===\n";
$result = $pdo->query('DESCRIBE prd_produtos');
while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
    echo $row['Field'] . " - " . $row['Type'] . "\n";
}

echo "\n=== Primeiro registro prd_produtos ===\n";
$result = $pdo->query('SELECT * FROM prd_produtos LIMIT 1');
$row = $result->fetch(PDO::FETCH_ASSOC);
foreach ($row as $key => $value) {
    echo "$key: " . (is_null($value) ? "NULL" : $value) . "\n";
}

echo "\n=== Total de registros ===\n";
$count = $pdo->query('SELECT COUNT(*) FROM prd_produtos')->fetchColumn();
echo "Total: $count produtos\n";
