<?php
$pdo = new PDO('mysql:host=127.0.0.1:3306;dbname=controlepcp_sandbox;charset=utf8mb4','root','k7m2y9u4');

echo "=== PROCURANDO 3734 NOS JSONs ===\n\n";

// Procurar em codi_performance
echo "Buscando em codi_performance...\n";
$sql = "SELECT perf_id, perf_ordem_producao, perf_dados_json 
        FROM codi_performance
        LIMIT 100";
$result = $pdo->query($sql);
$rows = $result->fetchAll(PDO::FETCH_ASSOC);

echo "Total de registros: " . count($rows) . "\n\n";

foreach ($rows as $row) {
    if (strpos($row['perf_dados_json'], '3734') !== false) {
        echo "✅ ENCONTRADO em perf_id {$row['perf_id']} | OP: {$row['perf_ordem_producao']}\n";
        echo "JSON:\n";
        $data = json_decode($row['perf_dados_json'], true);
        echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
    }
}

// Procurar em codi_calendario
echo "\n=== PROCURANDO NOS JSONs codi_calendario ===\n\n";
$sql = "SELECT cal_id, cal_data, cal_dados_json 
        FROM codi_calendario
        WHERE cal_data BETWEEN '2026-03-27' AND '2026-03-28'
        LIMIT 50";
$result = $pdo->query($sql);
$rows = $result->fetchAll(PDO::FETCH_ASSOC);

echo "Registros entre 27-28/03: " . count($rows) . "\n\n";

foreach ($rows as $row) {
    if (strpos($row['cal_dados_json'], '3734') !== false || strpos($row['cal_dados_json'], '201055') !== false) {
        echo "✅ ENCONTRADO em cal_id {$row['cal_id']} | Data: {$row['cal_data']}\n";
        echo "JSON:\n";
        $data = json_decode($row['cal_dados_json'], true);
        echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
    }
}

echo "\n=== TODOS OS JSONs DE 27-28/03 ===\n\n";
$sql = "SELECT cal_id, cal_data, cal_dados_json 
        FROM codi_calendario
        WHERE cal_data BETWEEN '2026-03-27' AND '2026-03-28'";
$result = $pdo->query($sql);
$rows = $result->fetchAll(PDO::FETCH_ASSOC);

echo "Total registros 27-28/03: " . count($rows) . "\n\n";
foreach ($rows as $i => $row) {
    echo "--- Registro " . ($i+1) . " (cal_id {$row['cal_id']}, data {$row['cal_data']}) ---\n";
    $data = json_decode($row['cal_dados_json'], true);
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
}
?>
