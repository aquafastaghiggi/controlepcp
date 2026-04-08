<?php
$pdo = new PDO('mysql:host=127.0.0.1:3306;dbname=controlepcp_sandbox;charset=utf8mb4','root','k7m2y9u4');

// Ver todas as datas disponíveis em codi_calendario
echo "=== DATAS DISPONÍVEIS EM codi_calendario ===\n\n";
$sql = "SELECT DISTINCT cal_data FROM codi_calendario ORDER BY cal_data DESC LIMIT 20";
$result = $pdo->query($sql);
$dates = $result->fetchAll(PDO::FETCH_COLUMN);
echo "Datas: " . implode(", ", $dates) . "\n\n";

// Procurar por 3734 em qualquer lugar
echo "=== PROCURANDO '3734' OU '201055' EM TODOS OS JSONs ===\n\n";

$sql = "SELECT cal_id, cal_data, cal_dados_json 
        FROM codi_calendario
        ORDER BY cal_data DESC
        LIMIT 200";
$result = $pdo->query($sql);
$rows = $result->fetchAll(PDO::FETCH_ASSOC);

$found_3734 = false;
$found_201055 = false;

foreach ($rows as $row) {
    $json_str = $row['cal_dados_json'];
    
    if (strpos($json_str, '3734') !== false) {
        echo "✅ ENCONTRADO 3734 em cal_id {$row['cal_id']} | Data: {$row['cal_data']}\n";
        $data = json_decode($json_str, true);
        echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
        $found_3734 = true;
    }
    
    if (strpos($json_str, '201055') !== false || strpos($json_str, '0201055') !== false) {
        echo "✅ ENCONTRADO 201055 em cal_id {$row['cal_id']} | Data: {$row['cal_data']}\n";
        $data = json_decode($json_str, true);
        echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
        $found_201055 = true;
        if ($found_3734 && $found_201055) break;
    }
}

if (!$found_3734 && !$found_201055) {
    echo "❌ Não encontrou 3734 nem 201055\n\n";
    echo "Mostrando últimos 5 registros de codi_calendario:\n";
    $sql = "SELECT cal_id, cal_data, SUBSTR(cal_dados_json, 1, 200) as preview 
            FROM codi_calendario
            ORDER BY cal_id DESC
            LIMIT 5";
    $result = $pdo->query($sql);
    foreach ($result->fetchAll(PDO::FETCH_ASSOC) as $row) {
        echo "ID: {$row['cal_id']} | Data: {$row['cal_data']} | Preview: {$row['preview']}\n";
    }
}

// Procurar em codi_performance
echo "\n=== PROCURANDO EM codi_performance ===\n\n";
$sql = "SELECT perf_id, perf_ordem_producao, perf_dados_json 
        FROM codi_performance
        ORDER BY perf_id DESC
        LIMIT 100";
$result = $pdo->query($sql);
$rows = $result->fetchAll(PDO::FETCH_ASSOC);

foreach ($rows as $row) {
    if (strpos($row['perf_dados_json'], '201055') !== false || 
        strpos($row['perf_dados_json'], '3734') !== false) {
        echo "✅ ENCONTRADO em perf_id {$row['perf_id']} | OP: {$row['perf_ordem_producao']}\n";
        $data = json_decode($row['perf_dados_json'], true);
        echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
    }
}
?>
