<?php

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

use App\Database\Connection;
 use App\Data\DatabaseData;
 use App\Repository\ProgramacaoRepository;
 use App\Services\Scheduler;
 use App\Services\WorkCalendar;
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

function infer_line_code_from_program_skus(array $program): ?string
{
    $skus = [];

    foreach ($program as $item) {
        if (!is_array($item)) {
            continue;
        }

        $sku = trim((string) ($item['sku'] ?? ''));
        if ($sku === '' || strtoupper($sku) === 'SETUP') {
            continue;
        }

        $skus[] = $sku;
    }

    $skus = array_values(array_unique($skus));
    if ($skus === []) {
        return null;
    }

    $pdo = Connection::get();

    $placeholders = implode(',', array_fill(0, count($skus), '?'));
    $stmt = $pdo->prepare(
        "SELECT prd_linha_id FROM prd_produtos WHERE prd_sku IN ({$placeholders}) GROUP BY prd_linha_id ORDER BY COUNT(*) DESC, prd_linha_id ASC LIMIT 1"
    );
    $stmt->execute($skus);
    $lineId = (int) ($stmt->fetchColumn() ?: 0);
    if ($lineId <= 0) {
        return null;
    }

    $stmt = $pdo->prepare('SELECT lin_codigo FROM lin_linhas WHERE lin_id = :id LIMIT 1');
    $stmt->execute(['id' => $lineId]);
    $code = trim((string) ($stmt->fetchColumn() ?: ''));
    if ($code === '') {
        return null;
    }

    return normalize_line_code($code);
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

     $datasetsPayload = isset($payload['datasets']) && is_array($payload['datasets']) ? $payload['datasets'] : null;

     $lineCodeFromPayload = !empty($payload['line_code']) ? normalize_line_code((string) $payload['line_code']) : null;
     $lineCodeFromDatasets = null;
     if (is_array($datasetsPayload) && isset($datasetsPayload['calendar']) && is_array($datasetsPayload['calendar'])) {
         $lineFromCalendar = trim((string) ($datasetsPayload['calendar']['line'] ?? ''));
         if ($lineFromCalendar !== '') {
             $lineCodeFromDatasets = normalize_line_code($lineFromCalendar);
         }
     }

     $baseStart = DateTimeHelper::fromLocalInput((string) ($payload['base_start'] ?? ''));
     $queryDateTime = DateTimeHelper::fromLocalInput((string) ($payload['query_datetime'] ?? ''));
     $productionEfficiency = (float) ($payload['production_efficiency'] ?? 100);
     $numeroOp = !empty($payload['numero_op']) ? (string) $payload['numero_op'] : null;
     $program = $payload['items'] ?? [];

     if (!is_array($program) || $program === []) {
         pcp_json_response(422, ['message' => 'Informe ao menos um item para calcular.']);
     }

     $lineCodeFromSkus = infer_line_code_from_program_skus($program);

     $lineCode = $lineCodeFromDatasets ?? $lineCodeFromSkus ?? $lineCodeFromPayload;
     if ($lineCodeFromDatasets !== null && $lineCodeFromSkus !== null && $lineCodeFromDatasets !== $lineCodeFromSkus) {
         $lineCode = $lineCodeFromSkus;
     }

     $productionEfficiency = $productionEfficiency <= 0 ? 100.0 : $productionEfficiency;

     $datasetRepo = new DatabaseData(null, $lineCode);
     $datasets = $datasetRepo->all();
     $effectiveLine = (string) ($datasets['calendar']['line'] ?? '');
     if ($effectiveLine === '') {
         $effectiveLine = $lineCode ?? 'L2';
     }

     $programacaoConfig = null;
     $calendarDayOrders = [];
     foreach ($program as $item) {
         if (!is_array($item)) {
             continue;
         }
 
         if (isset($item['programacao_config']) && is_array($item['programacao_config'])) {
             $programacaoConfig = $item['programacao_config'];
             break;
         }
     }

     if (is_array($programacaoConfig)) {
         $selectedDay = trim((string) ($programacaoConfig['selected_day'] ?? ''));
         $efficiencyFromConfig = (float) ($programacaoConfig['efficiency'] ?? 100);
         $ordersFromConfig = is_array($programacaoConfig['orders'] ?? null) ? $programacaoConfig['orders'] : [];

         $ordersByDayFromConfig = $programacaoConfig['orders_by_day'] ?? null;
         if (is_array($ordersByDayFromConfig)) {
             foreach ($ordersByDayFromConfig as $dateKey => $dayOrdersRaw) {
                 $date = trim((string) $dateKey);
                 if ($date === '' || preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $date) !== 1) {
                     continue;
                 }

                 if (!is_array($dayOrdersRaw)) {
                     continue;
                 }

                 $normalized = [1 => false, 2 => false, 3 => false, 4 => false];
                 for ($order = 1; $order <= 4; $order++) {
                     $raw = $dayOrdersRaw[$order] ?? $dayOrdersRaw[(string) $order] ?? null;
                     if (is_bool($raw)) {
                         $normalized[$order] = $raw;
                         continue;
                     }

                     if ($raw === 0 || $raw === 1 || $raw === '0' || $raw === '1') {
                         $normalized[$order] = ((int) $raw) === 1;
                         continue;
                     }
                 }

                 $calendarDayOrders[$date] = $normalized;
             }
         }
 
         if ($selectedDay !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $selectedDay) === 1) {
             $useOrder3 = !empty($ordersFromConfig[3]) || !empty($ordersFromConfig['3']);
             $useOrder4 = !empty($ordersFromConfig[4]) || !empty($ordersFromConfig['4']);

             if ($calendarDayOrders !== []) {
                 foreach ($calendarDayOrders as $dayOrders) {
                     if (!is_array($dayOrders)) {
                         continue;
                     }

                     if (!empty($dayOrders[3])) {
                         $useOrder3 = true;
                     }

                     if (!empty($dayOrders[4])) {
                         $useOrder4 = true;
                     }

                     if ($useOrder3 && $useOrder4) {
                         break;
                     }
                 }
             }

             if (isset($program[0]) && is_array($program[0])) {
                 if (!array_key_exists('use_order_3', $program[0])) {
                     $program[0]['use_order_3'] = $useOrder3;
                 }
                 if (!array_key_exists('use_order_4', $program[0])) {
                     $program[0]['use_order_4'] = $useOrder4;
                 }
             }
         }

         if ($efficiencyFromConfig > 0) {
             $productionEfficiency = $efficiencyFromConfig;
         }
     }

     if (!$baseStart) {
         pcp_json_response(422, ['message' => 'Informe a data/hora base.']);
     }

     $scheduler = new Scheduler($datasets);
     $result = $scheduler->calculate($program, $baseStart, $queryDateTime, $productionEfficiency, $calendarDayOrders);

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
