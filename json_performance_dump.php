<?php
$pdo = new PDO(
    'mysql:host=127.0.0.1:3306;dbname=controlepcp_sandbox;charset=utf8mb4',
    'root',
    'k7m2y9u4'
);

echo "=== JSON COMPLETO DE codi_performance ===\n\n";

$result = $pdo->query("SELECT perf_dados_json FROM codi_performance LIMIT 1");
$row = $result->fetch(PDO::FETCH_ASSOC);

if ($row) {
    echo "JSON RAW:\n";
    echo $row['perf_dados_json'] . "\n";
    
    echo "\n\nJSON FORMATTED:\n";
    $json = json_decode($row['perf_dados_json'], true);
    echo json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}

echo "\n\n=== AMOSTRA DE DADOS COM CONTEÚDO ===\n";
$result = $pdo->query("
    SELECT perf_id, perf_item_codi, perf_ordem_producao, perf_dados_json
    FROM codi_performance
    WHERE perf_dados_json IS NOT NULL
    LIMIT 3
");

while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
    echo "\n--- Performance ID: {$row['perf_id']} ---\n";
    echo "perf_item_codi: {$row['perf_item_codi']}\n";
    echo "perf_ordem_producao: {$row['perf_ordem_producao']}\n";
    
    $json = json_decode($row['perf_dados_json'], true);
    echo "JSON keys: " . implode(', ', array_keys($json)) . "\n";
    
    // Mostrar itens principais
    if (isset($json['item'])) {
        echo "  Item: " . json_encode($json['item']) . "\n";
    }
    if (isset($json['operacoes'])) {
        echo "  Operações: " . count($json['operacoes']) . " registros\n";
        if (is_array($json['operacoes']) && count($json['operacoes']) > 0) {
            echo "    Primeira: " . json_encode(reset($json['operacoes'])) . "\n";
        }
    }
}
