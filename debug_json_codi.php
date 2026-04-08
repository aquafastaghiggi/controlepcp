<?php
$pdo = new PDO(
    'mysql:host=127.0.0.1:3306;dbname=controlepcp_sandbox;charset=utf8mb4',
    'root',
    'k7m2y9u4'
);

echo "=== Primeiro registro de codi_performance ===\n";
$sql = "SELECT perf_dados_json FROM codi_performance LIMIT 1";
$result = $pdo->query($sql);
$row = $result->fetch(PDO::FETCH_ASSOC);

$json = json_decode($row['perf_dados_json'], true);
echo "Estrutura do JSON:\n";
echo json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";

echo "\n=== Keys disponíveis no JSON: ===\n";
if (is_array($json)) {
    print_r(array_keys($json));
}

echo "\n=== Tentando extrair 'item.codItem' ===\n";
$sql2 = "
    SELECT 
        SUBSTR(JSON_EXTRACT(perf_dados_json, '$.item.codItem'), 2, -1) AS sku,
        SUBSTR(JSON_EXTRACT(perf_dados_json, '$.item.nomeItem'), 2, -1) AS descricao
    FROM codi_performance 
    LIMIT 5
";
$result2 = $pdo->query($sql2);
while ($row = $result2->fetch(PDO::FETCH_ASSOC)) {
    echo json_encode($row) . "\n";
}

echo "\n=== Verificar se 'ordemProducao' existe no JSON ===\n";
$sql3 = "
    SELECT 
        perf_id,
        JSON_EXTRACT(perf_dados_json, '$.ordemProducao') AS op_json,
        JSON_EXTRACT(perf_dados_json, '$.ordem_producao') AS op_json2,
        JSON_EXTRACT(perf_dados_json, '$.operacao') AS operacao,
        JSON_EXTRACT(perf_dados_json, '$.operacaoId') AS operacao_id
    FROM codi_performance 
    LIMIT 5
";
$result3 = $pdo->query($sql3);
while ($row = $result3->fetch(PDO::FETCH_ASSOC)) {
    echo json_encode($row) . "\n";
}

echo "\n=== Total de registros codi_performance ===\n";
$sql4 = "SELECT COUNT(*) FROM codi_performance";
echo $pdo->query($sql4)->fetchColumn() . " registros\n";
