<?php
require __DIR__ . '/src/bootstrap.php';

use App\Database\Connection;

$pdo = Connection::get();

// Ver as primeiras programações
$stmt = $pdo->query("
    SELECT prg_id, prg_numero_op, prg_status, prg_criado_em
    FROM prg_programas
    LIMIT 10
");

echo "<h2>Programações no banco:</h2>";
foreach ($stmt as $row) {
    echo "<pre>";
    print_r($row);
    echo "</pre>";
}

// Ver IDs 8 e 9 especificamente
echo "<h2>Programações 8 e 9:</h2>";
$stmt = $pdo->prepare("
    SELECT prg_id, prg_numero_op, prg_status, prg_criado_em 
    FROM prg_programas 
    WHERE prg_id IN (8, 9)
");
$stmt->execute();

foreach ($stmt as $row) {
    echo "<pre>";
    print_r($row);
    echo "</pre>";
}
