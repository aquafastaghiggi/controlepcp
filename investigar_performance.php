<?php
$pdo = new PDO(
    'mysql:host=127.0.0.1:3306;dbname=controlepcp_sandbox;charset=utf8mb4',
    'root',
    'k7m2y9u4'
);

echo "=== TABELA: codi_performance ===\n";
$result = $pdo->query("DESCRIBE codi_performance");
while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
    echo "  {$row['Field']} ({$row['Type']})\n";
}

echo "\nDados (primeiros 5):\n";
$result = $pdo->query("SELECT * FROM codi_performance LIMIT 5");
while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
    echo "\n---\n";
    foreach ($row as $k => $v) {
        echo "  $k: " . substr((string)$v, 0, 60) . "\n";
    }
}

echo "\n\n=== PROCURANDO ORDEM/OP E SKU ===\n";
$result = $pdo->query("
    SELECT GROUP_CONCAT(DISTINCT COLUMN_NAME) as campos
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_NAME='codi_performance'
    AND (COLUMN_NAME LIKE '%ordem%' 
         OR COLUMN_NAME LIKE '%op%'
         OR COLUMN_NAME LIKE '%sku%'
         OR COLUMN_NAME LIKE '%item%'
         OR COLUMN_NAME LIKE '%produto%'
         OR COLUMN_NAME LIKE '%quantidade%'
    )
");
$row = $result->fetch(PDO::FETCH_ASSOC);
echo "Campos interessantes encontrados:\n";
echo "  " . ($row['campos'] ?: 'Nenhum encontrado') . "\n";

echo "\n\n=== TABELA: codi_sincronizacao ===\n";
$result = $pdo->query("DESCRIBE codi_sincronizacao");
$cols = $result->fetchAll(PDO::FETCH_ASSOC);
if (count($cols) > 0) {
    echo "Campos:\n";
    foreach ($cols as $row) {
        echo "  {$row['Field']}\n";
    }
    
    echo "\nDados:\n";
    $result = $pdo->query("SELECT * FROM codi_sincronizacao LIMIT 3");
    while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
        echo json_encode($row) . "\n";
    }
}
