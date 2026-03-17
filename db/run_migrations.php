<?php
require __DIR__ . '/../src/bootstrap.php';

$pdo = App\Database\Connection::get();

$migration = "ALTER TABLE prg_programas ADD COLUMN IF NOT EXISTS prg_numero_op VARCHAR(120) NULL UNIQUE";

try {
    $pdo->exec($migration);
    echo "✓ Migration executada com sucesso!\n";
} catch (Exception $e) {
    echo "✗ Erro na migration: " . $e->getMessage() . "\n";
    exit(1);
}
