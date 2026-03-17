<?php

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

use App\Data\DatabaseData;

header('Content-Type: application/json; charset=utf-8');

$repo = new DatabaseData();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $data = $repo->all();
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$payload = json_decode((string) file_get_contents('php://input'), true);
if (!is_array($payload)) {
    http_response_code(400);
    echo json_encode(['message' => 'Payload invalido.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$datasets = $payload['datasets'] ?? $payload;
if (!is_array($datasets)) {
    http_response_code(422);
    echo json_encode(['message' => 'Payload deve conter "datasets".'], JSON_UNESCAPED_UNICODE);
    exit;
}

$repo->persistDataset($datasets);

$data = $repo->all();

echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
