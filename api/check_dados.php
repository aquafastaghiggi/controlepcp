<?php
$db = new PDO("mysql:host=127.0.0.1;dbname=controlepcp_sandbox;charset=utf8mb4", 'root', 'k7m2y9u4');

echo "=== VERIFICAÇÃO DE DADOS ===\n\n";

$tables = ['prg_itens', 'prg_programas', 'sch_linhas'];

foreach ($tables as $tabela) {
    $count = $db->query("SELECT COUNT(*) FROM $tabela")->fetchColumn();
    echo "$tabela: $count registros\n";
}

echo "\n=== AMOSTRA DE prg_itens ===\n";
$stmt = $db->query("SELECT * FROM prg_itens LIMIT 3");
foreach ($stmt as $row) {
    echo json_encode($row) . "\n";
}

echo "\n=== AMOSTRA DE sch_linhas ===\n";
$stmt = $db->query("SELECT * FROM sch_linhas LIMIT 3");
foreach ($stmt as $row) {
    echo json_encode($row) . "\n";
}

?>
