<?php
declare(strict_types=1);
require __DIR__ . '/src/bootstrap.php';
use App\Database\Connection;
$pdo = Connection::get();
$stmt = $pdo->query('SELECT prg_id, prg_numero_op FROM prg_programas ORDER BY prg_id LIMIT 20');
foreach ($stmt as $row) {
    echo $row['prg_id'] . ' -> ' . ($row['prg_numero_op'] ?? 'NULL') . PHP_EOL;
}
