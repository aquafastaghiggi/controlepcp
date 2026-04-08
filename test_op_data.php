<?php
require 'src/Database/Connection.php';
use App\Database\Connection;

$pdo = Connection::get();

echo "=== AMOSTRA DE DADOS DE OP (PERFORMANCE) ===\n";
$result = $pdo->query('SELECT perf_codigo_codi, perf_recurso_codi_id, perf_item_codi, perf_ordem_producao, perf_dados_json FROM codi_performance WHERE perf_ordem_producao IS NOT NULL LIMIT 5');

foreach ($result->fetchAll(PDO::FETCH_ASSOC) as $row) {
    echo 'ID: ' . $row['perf_codigo_codi'] . ' | OP: ' . $row['perf_ordem_producao'] . ' | Item: ' . $row['perf_item_codi'] . "\n";
}

echo "\n=== TOTAL COM OP ===\n";
$count = $pdo->query('SELECT COUNT(*) FROM codi_performance WHERE perf_ordem_producao IS NOT NULL')->fetchColumn();
echo 'Registros com OP: ' . $count . "\n";

echo "\n=== VERIFICAR RELACIONAMENTO COM CALENDARIO ===\n";
$result = $pdo->query('
    SELECT c.cal_data, c.cal_hora_inicio, r.cod_nome_recurso, p.perf_ordem_producao, p.perf_item_codi
    FROM codi_calendario c
    LEFT JOIN codi_recursos r ON c.cal_recurso_codi_id = r.cod_codigo_codi
    LEFT JOIN codi_performance p ON c.cal_recurso_codi_id = p.perf_recurso_codi_id
    WHERE p.perf_ordem_producao IS NOT NULL
    LIMIT 5
');

foreach ($result->fetchAll(PDO::FETCH_ASSOC) as $row) {
    echo '[' . $row['cal_data'] . ' ' . $row['cal_hora_inicio'] . '] ' . $row['cod_nome_recurso'] . ' - OP: ' . $row['perf_ordem_producao'] . "\n";
}

echo "\n=== TODAS AS OPS UNICAS ===\n";
$result = $pdo->query('SELECT DISTINCT perf_ordem_producao FROM codi_performance WHERE perf_ordem_producao IS NOT NULL ORDER BY perf_ordem_producao LIMIT 15');
$ops = $result->fetchAll(PDO::FETCH_COLUMN);
echo "Total de OPs únicas: " . count($ops) . "\n";
echo "Exemplos: " . implode(", ", array_slice($ops, 0, 10)) . "\n";
?>
