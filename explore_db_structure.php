<?php
$pdo = new PDO('mysql:host=localhost;dbname=controlepcp_sandbox', 'root', 'k7m2y9u4');

// Listar todas as tabelas
$tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

echo "TABELAS DISPONÍVEIS:\n";
echo str_repeat("=", 50) . "\n";

foreach ($tables as $table) {
    echo "\n$table:\n";
    $columns = $pdo->query("DESCRIBE $table")->fetchAll();
    foreach ($columns as $col) {
        echo "  - {$col['Field']} ({$col['Type']})\n";
    }
}
