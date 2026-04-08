<?php
require 'src/Database/Connection.php';
use App\Database\Connection;

$pdo = Connection::get();

echo "=== ESTRUTURA DA TABELA sch_linhas ===\n";
$result = $pdo->query('DESCRIBE sch_linhas');
foreach ($result->fetchAll(PDO::FETCH_ASSOC) as $col) {
    echo $col['Field'] . " (" . $col['Type'] . ") - " . ($col['Key'] ?: 'NULL') . "\n";
}

echo "\n=== AMOSTRA DE DADOS sch_linhas ===\n";
$result = $pdo->query('SELECT * FROM sch_linhas LIMIT 3');
$rows = $result->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $row) {
    echo json_encode($row, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
}

echo "\n=== CONTAGEM ===\n";
$count = $pdo->query('SELECT COUNT(*) FROM sch_linhas')->fetchColumn();
echo "Total de registros: $count\n";

echo "\n=== CAMPOS COM CHAVE (para mapear) ===\n";
try {
    $stmt = $pdo->prepare('
        SELECT 
            COUNT(DISTINCT sch_linha_id) as linhas,
            COUNT(DISTINCT DATE(sch_data)) as datas
        FROM sch_linhas
    ');
    $stmt->execute();
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    echo json_encode($stats, JSON_PRETTY_PRINT) . "\n";
} catch (\Exception $e) {
    echo "Erro: " . $e->getMessage() . "\n";
}
?>
