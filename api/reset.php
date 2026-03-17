<?php

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

use App\Database\Connection;

header('Content-Type: application/json; charset=utf-8');

try {
    $pdo = Connection::get();

    // Carrega o esquema + seed inicial
    $schemaFile = __DIR__ . '/../db/schema.sql';
    if (!is_file($schemaFile)) {
        throw new \RuntimeException('Arquivo de schema nao encontrado: ' . $schemaFile);
    }

    $sql = file_get_contents($schemaFile);
    if ($sql === false) {
        throw new \RuntimeException('Falha ao ler o arquivo de schema.');
    }

    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');

    foreach (array_filter(array_map('trim', explode(';', $sql))) as $stmt) {
        if ($stmt === '') {
            continue;
        }

        // Remove ponto-e-virgula final caso exista (para evitar ';;' e erros de sintaxe)
        $stmt = rtrim($stmt, "\n\r ;");
        if ($stmt === '') {
            continue;
        }

        $pdo->exec($stmt);
    }

    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

    echo json_encode(['status' => 'ok', 'message' => 'Dados reiniciados com sucesso.']);
    exit;
} catch (\Throwable $e) {
    if (isset($pdo)) {
        try {
            $pdo->rollBack();
        } catch (\Throwable $rollbackEx) {
            // Ignora se não houver transação ativa ou se já tiver sido finalizada.
        }
    }

    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    exit;
}
