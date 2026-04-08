<?php
/**
 * Buscar coluna correta e dados de 201055
 */

$db = new PDO("mysql:host=127.0.0.1;dbname=controlepcp_sandbox;charset=utf8mb4", 'root', 'k7m2y9u4');

echo "=== ESTRUTURA DE TABELAS ===\n\n";

// Ver colunas da tabela prg_programas
$sql = "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = 'controlepcp_sandbox' AND TABLE_NAME = 'prg_programas'";
$cols = $db->query($sql)->fetchAll(PDO::FETCH_COLUMN);

echo "Colunas de prg_programas:\n";
foreach ($cols as $col) {
    echo "  - $col\n";
}

// Agora buscar programas com 201055
echo "\n\n=== BUSCANDO 201055 ===\n\n";

$sql2 = "SELECT * FROM prg_programas LIMIT 3";
$progs = $db->query($sql2)->fetchAll(PDO::FETCH_ASSOC);

if (count($progs) > 0) {
    echo "Primeira programa:\n";
    print_r($progs[0]);
}

// Procurar por 201055 em prg_itens
echo "\n\n=== PROCURANDO 201055 EM PRG_ITENS ===\n\n";

$sql3 = "SELECT * FROM prg_itens WHERE prg_itens_op = '201055' LIMIT 5";
$itens = $db->query($sql3)->fetchAll(PDO::FETCH_ASSOC);

echo "Encontrados " . count($itens) . " itens\n\n";

if (count($itens) > 0) {
    echo "Primeiro item:\n";
    foreach ($itens[0] as $k => $v) {
        echo "  $k: $v\n";
    }
}

?>
