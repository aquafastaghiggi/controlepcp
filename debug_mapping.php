<?php
require 'src/bootstrap.php';

use App\Database\Connection;

$pdo = Connection::get();

echo "=== Mapeando sch_linhas para prg_itens ===\n";
echo "Programa 9, primeiro schedule item:\n\n";

// Pegar o primeiro item de schedule do programa 9
$sql = "SELECT sch_sequencia, sch_sku FROM sch_linhas WHERE sch_programa_id = 9 LIMIT 1";
$stmt = $pdo->query($sql);
$sch = $stmt->fetch(PDO::FETCH_ASSOC);
var_dump($sch);

echo "\n\nProcurando item correspondente em prg_itens para programa 9:\n";
// Tentar by sequential ou by sku
if ($sch) {
    echo "Por SKU:\n";
    $sql = "SELECT * FROM prg_itens WHERE prg_programa_id = 9 AND prg_sku = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$sch['sch_sku']]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);
    var_dump($item);
}
?>
