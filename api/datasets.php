<?php

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

use App\Data\DatabaseData;

header('Content-Type: application/json; charset=utf-8');

function pcp_json_response(int $status, array $payload): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function pcp_log_error(string $traceId, \Throwable $e): void
{
    $dir = __DIR__ . '/../.tmp';
    if (!is_dir($dir)) {
        @mkdir($dir, 0777, true);
    }

    $line = sprintf(
        "[%s] trace=%s %s: %s\n",
        (new \DateTimeImmutable('now'))->format('Y-m-d H:i:s'),
        $traceId,
        get_class($e),
        $e->getMessage()
    );

    // Keep it simple: append one line per error.
    @file_put_contents($dir . '/api-error.log', $line, FILE_APPEND);
}

function buildDatabaseData(): DatabaseData
{
    $defaultHost = getenv('DB_HOST') ?: '127.0.0.1';

    try {
        return new DatabaseData();
    } catch (\RuntimeException $e) {
        if (strpos($e->getMessage(), '[2002]') !== false && $defaultHost === '127.0.0.1') {
            putenv('DB_HOST=localhost');
            return new DatabaseData();
        }

        throw $e;
    }
}

try {
    $repo = buildDatabaseData();

    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'GET') {
        pcp_json_response(200, $repo->all());
    }

    $rawInput = (string) file_get_contents('php://input');
    $payload = json_decode($rawInput, true);
    if (!is_array($payload)) {
        pcp_json_response(400, ['message' => 'Payload invalido: ' . json_last_error_msg()]);
    }

    $datasets = $payload['datasets'] ?? $payload;
    if (!is_array($datasets)) {
        pcp_json_response(422, ['message' => 'Payload deve conter "datasets".']);
    }

    $repo->persistDataset($datasets);

    pcp_json_response(200, $repo->all());
} catch (\Throwable $e) {
    $traceId = bin2hex(random_bytes(6));
    pcp_log_error($traceId, $e);

    pcp_json_response(500, [
        'message' => $e->getMessage(),
        'trace' => $traceId,
    ]);
}