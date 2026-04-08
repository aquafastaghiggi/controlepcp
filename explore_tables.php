<?php
require 'src/Database/Connection.php';
use App\Database\Connection;

$pdo = Connection::get();

echo "=== TODAS AS TABELAS ===\n";
$result = $pdo->query('SHOW TABLES');
$tables = $result->fetchAll(PDO::FETCH_COLUMN);

foreach ($tables as $table) {
    echo "$table\n";
}

echo "\n=== TABELAS RELACIONADAS A PLANEJAMENTO/PREVISÃO ===\n";
foreach ($tables as $table) {
    if (strpos(strtolower($table), 'sch') !== false || 
        strpos(strtolower($table), 'prod') !== false || 
        strpos(strtolower($table), 'plan') !== false ||
        strpos(strtolower($table), 'seq') !== false) {
        echo "✓ $table\n";
    }
}
?>
