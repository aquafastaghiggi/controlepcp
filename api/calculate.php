<?php

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

use App\Data\DatabaseData;
use App\Repository\ProgramacaoRepository;
use App\Services\Scheduler;
use App\Support\DateTimeHelper;

function normalize_line_code(?string $value): ?string
{
    if ($value === null) {
        return null;
    }

    $clean = preg_replace('/[^a-zA-Z0-9]/', '', trim($value));
    if ($clean === '') {
        return null;
    }

    return strtoupper($clean);
}

header('Content-Type: application/json; charset=utf-8');

$payload = json_decode((string) file_get_contents('php://input'), true);

if (!is_array($payload)) {
    http_response_code(400);
    echo json_encode(['message' => 'Payload invalido.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$lineCode = !empty($payload['line_code']) ? normalize_line_code((string) $payload['line_code']) : null;

$baseStart = DateTimeHelper::fromLocalInput((string) ($payload['base_start'] ?? ''));
$queryDateTime = DateTimeHelper::fromLocalInput((string) ($payload['query_datetime'] ?? ''));
$productionEfficiency = (float) ($payload['production_efficiency'] ?? 100);
$numeroOp = !empty($payload['numero_op']) ? (string) $payload['numero_op'] : null;
$program = $payload['items'] ?? [];

if (!$baseStart) {
    http_response_code(422);
    echo json_encode(['message' => 'Informe a data/hora base.'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!is_array($program) || $program === []) {
    http_response_code(422);
    echo json_encode(['message' => 'Informe ao menos um item para calcular.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$productionEfficiency = $productionEfficiency <= 0 ? 100.0 : $productionEfficiency;

$datasetRepo = new DatabaseData(null, $lineCode);
$datasets = $datasetRepo->all();
$effectiveLine = $lineCode ?? ($datasets['calendar']['line'] ?? '');
if ($effectiveLine === '') {
    $effectiveLine = 'L2';
}

$scheduler = new Scheduler($datasets);
$result = $scheduler->calculate($program, $baseStart, $queryDateTime, $productionEfficiency);

$programRepo = new ProgramacaoRepository();
$programRepo->salvarExecucao(
    $effectiveLine,
    $baseStart,
    $queryDateTime,
    $productionEfficiency,
    $program,
    $result['rows'] ?? [],
    $numeroOp
);

echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
