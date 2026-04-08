<?php
require 'src/bootstrap.php';

use App\Database\Connection;

$pdo = Connection::get();

echo "=== Procurando 201055 no banco ===\n\n";

// Procurar em prg_programas
echo "1. Em prg_programas (todos):\n";
$sql = "SELECT prg_id, prg_numero_op, prg_status FROM prg_programas LIMIT 10";
$stmt = $pdo->query($sql);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
var_dump($rows);

// Procurar em prg_itens
echo "\n2. Em prg_itens com 201055:\n";
$sql = "SELECT prg_programa_id, prg_itens_op FROM prg_itens WHERE prg_itens_op LIKE '%201055%' LIMIT 5";
$stmt = $pdo->query($sql);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
var_dump($rows);

// Procurar em sch_linhas
echo "\n3. Em sch_linhas (programa 8 - LN10):\n";
$sql = "SELECT sch_id, sch_programa_id, sch_sku, sch_tipo FROM sch_linhas WHERE sch_programa_id = 8 LIMIT 5";
$stmt = $pdo->query($sql);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
var_dump($rows);

// Relacionar sch_linhas com prg_itens
echo "\n4. Tabela prg_itens para programa 8:\n";
$sql = "SELECT * FROM prg_itens WHERE prg_programa_id = 8 LIMIT 5";
$stmt = $pdo->query($sql);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
var_dump($rows);

// Procurar qual programa tem a sequência ou itens com 201055
echo "\n5. Procurando 201 em prg_itens_op:\n";
$sql = "SELECT DISTINCT prg_programa_id, prg_itens_op FROM prg_itens WHERE prg_itens_op LIKE '201%' LIMIT 10";
$stmt = $pdo->query($sql);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
var_dump($rows);
?>
