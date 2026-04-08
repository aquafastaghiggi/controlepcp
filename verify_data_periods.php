<?php
require 'src/Database/Connection.php';
use App\Database\Connection;

$pdo = Connection::get();

echo "=== PERÍODO DOS DADOS DO CODI ===\n\n";

echo "CALENDÁRIO:\n";
$result = $pdo->query('
    SELECT 
        MIN(cal_data) as data_minima,
        MAX(cal_data) as data_maxima,
        COUNT(*) as total_registros,
        COUNT(DISTINCT cal_data) as dias_unicos
    FROM codi_calendario
');
$stats = $result->fetch(PDO::FETCH_ASSOC);
echo "  Data mínima: " . $stats['data_minima'] . "\n";
echo "  Data máxima: " . $stats['data_maxima'] . "\n";
echo "  Total registros: " . $stats['total_registros'] . "\n";
echo "  Dias únicos: " . $stats['dias_unicos'] . "\n";

echo "\nPERFORMANCE:\n";
$result = $pdo->query('
    SELECT 
        GROUP_CONCAT(DISTINCT DATE_FORMAT(CURDATE(), "%Y")) as anos_codi,
        COUNT(*) as total_registros
    FROM codi_performance
');
$stats = $result->fetch(PDO::FETCH_ASSOC);
echo "  Total registros: " . $stats['total_registros'] . "\n";

echo "\n\n=== PERÍODO DO PLANEJAMENTO (sch_linhas) ===\n\n";

echo "PLANEJAMENTO:\n";
$result = $pdo->query('
    SELECT 
        MIN(sch_data_inicio) as data_minima,
        MAX(sch_data_inicio) as data_maxima,
        COUNT(*) as total_registros
    FROM sch_linhas
');
$stats = $result->fetch(PDO::FETCH_ASSOC);
echo "  Data mínima: " . $stats['data_minima'] . "\n";
echo "  Data máxima: " . $stats['data_maxima'] . "\n";
echo "  Total registros: " . $stats['total_registros'] . "\n";

echo "\n\n=== AMOSTRA DE DATAS DO CODI ===\n";
$result = $pdo->query('
    SELECT DISTINCT DATE(cal_data) as data FROM codi_calendario
    GROUP BY DATE(cal_data)
    ORDER BY cal_data DESC
    LIMIT 10
');
foreach ($result->fetchAll(PDO::FETCH_ASSOC) as $row) {
    echo "  " . $row['data'] . "\n";
}

echo "\n=== AMOSTRA DE DATAS DO PLANEJAMENTO ===\n";
$result = $pdo->query('
    SELECT DISTINCT sch_data_inicio as data FROM sch_linhas
    ORDER BY sch_data_inicio DESC
    LIMIT 10
');
foreach ($result->fetchAll(PDO::FETCH_ASSOC) as $row) {
    echo "  " . $row['data'] . "\n";
}
?>
