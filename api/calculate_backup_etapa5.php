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
        '[%s] trace=%s %s: %s\n',
        (new \DateTimeImmutable('now'))->format('Y-m-d H:i:s'),
        $traceId,
        get_class($e),
        $e->getMessage()
    );

    @file_put_contents($dir . '/api-error.log', $line, FILE_APPEND);
}

try {
    $payload = json_decode((string) file_get_contents('php://input'), true);

    if (!is_array($payload)) {
        pcp_json_response(400, ['message' => 'Payload invalido.']);
    }

    $lineCode = !empty($payload['line_code']) ? normalize_line_code((string) $payload['line_code']) : null;

    $baseStart = DateTimeHelper::fromLocalInput((string) ($payload['base_start'] ?? ''));
    $queryDateTime = DateTimeHelper::fromLocalInput((string) ($payload['query_datetime'] ?? ''));
    $productionEfficiency = (float) ($payload['production_efficiency'] ?? 100);
    $numeroOp = !empty($payload['numero_op']) ? (string) $payload['numero_op'] : null;
    $program = $payload['items'] ?? [];

    if (!$baseStart) {
        pcp_json_response(422, ['message' => 'Informe a data/hora base.']);
    }

    if (!is_array($program) || $program === []) {
        pcp_json_response(422, ['message' => 'Informe ao menos um item para calcular.']);
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

    pcp_json_response(200, $result);
} catch (\Throwable $e) {
    $traceId = bin2hex(random_bytes(6));
    pcp_log_error($traceId, $e);

    pcp_json_response(500, [
        'message' => $e->getMessage(),
        'trace' => $traceId,
    ]);
}
