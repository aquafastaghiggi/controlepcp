<?php
$pdo = new PDO(
    'mysql:host=127.0.0.1:3306;dbname=controlepcp_sandbox;charset=utf8mb4',
    'root',
    'k7m2y9u4'
);

echo "=== ESTRUTURA JSON DO CODI ===\n\n";

$result = $pdo->query("SELECT cal_dados_json FROM codi_calendario LIMIT 3");
$i = 0;
while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
    $i++;
    echo "--- Registro #$i ---\n";
    $json = json_decode($row['cal_dados_json'], true);
    echo json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
}

echo "\n=== ANALISANDO ESTRUTURA ===\n";
$result = $pdo->query("SELECT cal_dados_json FROM codi_calendario LIMIT 1");
$row = $result->fetch(PDO::FETCH_ASSOC);
if ($row) {
    $json = json_decode($row['cal_dados_json'], true);
    
    echo "Chaves principais:\n";
    foreach ($json as $k => $v) {
        if (is_array($v)) {
            echo "  [$k] => " . gettype($v) . " com " . count($v) . " itens\n";
            if (count($v) > 0) {
                $first = reset($v);
                if (is_array($first)) {
                    echo "       Subchaves: " . implode(', ', array_keys($first)) . "\n";
                } else {
                    echo "       Valores: " . implode(', ', array_slice($v, 0, 3)) . "\n";
                }
            }
        } else {
            echo "  [$k] => " . substr((string)$v, 0, 60) . "\n";
        }
    }
}
