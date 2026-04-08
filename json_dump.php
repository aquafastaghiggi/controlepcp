<?php
$pdo = new PDO(
    'mysql:host=127.0.0.1:3306;dbname=controlepcp_sandbox;charset=utf8mb4',
    'root',
    'k7m2y9u4'
);

$result = $pdo->query("SELECT cal_dados_json FROM codi_calendario LIMIT 1");
$row = $result->fetch(PDO::FETCH_ASSOC);

if ($row) {
    echo "JSON RAW:\n";
    echo $row['cal_dados_json'] . "\n";
    
    echo "\n\nJSON FORMATTED:\n";
    $json = json_decode($row['cal_dados_json'], true);
    print_r($json);
}
