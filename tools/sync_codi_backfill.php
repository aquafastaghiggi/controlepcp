<?php

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

use App\Database\Connection;

set_time_limit(0);
ignore_user_abort(true);

function log_line(string $msg): void
{
    $stamp = date('Y-m-d H:i:s');
    echo '[' . $stamp . '] ' . $msg . PHP_EOL;
}

function normalizar_ordem(string $valor): string
{
    $texto = trim($valor);
    if ($texto === '') {
        return '';
    }

    if (ctype_digit($texto)) {
        return ltrim($texto, '0') ?: '0';
    }

    return $texto;
}

function normalizar_nome_parada($valor): string
{
    return trim((string) ($valor ?? ''));
}

function prioridade_parada($nome): int
{
    $texto = strtoupper(normalizar_nome_parada($nome));
    if ($texto === 'TROCA DE KIT') {
        return 2;
    }
    if ($texto === 'TROCA DE LIQUIDO') {
        return 1;
    }
    return 0;
}

function eh_parada_alvo($nome): bool
{
    return prioridade_parada($nome) > 0;
}

function calcular_duracao_minutos($inicio, $fim): float
{
    try {
        if (!$inicio || !$fim) {
            return 0.0;
        }

        $dtInicio = new DateTimeImmutable((string) $inicio);
        $dtFim = new DateTimeImmutable((string) $fim);
        return max(0.0, ($dtFim->getTimestamp() - $dtInicio->getTimestamp()) / 60.0);
    } catch (Throwable) {
        return 0.0;
    }
}

function fetch_codi_day(string $dateStr, int $operacao): array
{
    $url = 'http://192.168.8.246:8080/action/ger/webservice/rest/relatorioEventoConsolidado?data='
        . rawurlencode($dateStr)
        . '&operacao='
        . $operacao;

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_USERPWD => 'marcos.brun:Eb035611!',
        CURLOPT_TIMEOUT => 60,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_HTTPHEADER => ['Accept: application/json'],
    ]);

    $body = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($body === false) {
        throw new RuntimeException('Curl failed: ' . $curlError);
    }

    if ($httpCode !== 200) {
        throw new RuntimeException('HTTP ' . $httpCode);
    }

    $json = json_decode(mb_convert_encoding($body, 'UTF-8', 'ISO-8859-1'), true);
    if (!is_array($json)) {
        throw new RuntimeException('Invalid JSON for ' . $dateStr);
    }

    return $json;
}

function usage(): void
{
    log_line('Usage: php tools/sync_codi_backfill.php --start=YYYY-MM-DD --end=YYYY-MM-DD [--op=20] [--dry-run=1]');
}

$opts = getopt('', ['start:', 'end:', 'op::', 'dry-run::']);
$start = trim((string) ($opts['start'] ?? ''));
$end = trim((string) ($opts['end'] ?? ''));
$operacao = isset($opts['op']) ? (int) $opts['op'] : 20;
$dryRun = array_key_exists('dry-run', $opts) && (string) $opts['dry-run'] !== '0';

if ($start === '' || $end === '') {
    usage();
    exit(1);
}

$startDt = DateTimeImmutable::createFromFormat('Y-m-d', $start);
$endDt = DateTimeImmutable::createFromFormat('Y-m-d', $end);

if (!$startDt || !$endDt) {
    log_line('Invalid date format. Use YYYY-MM-DD.');
    exit(1);
}

if ($startDt > $endDt) {
    [$startDt, $endDt] = [$endDt, $startDt];
}

$pdo = Connection::get();
$days = [];
for ($day = $startDt; $day <= $endDt; $day = $day->modify('+1 day')) {
    $days[] = $day->format('Y-m-d');
}

log_line('Backfill CODI from ' . $startDt->format('Y-m-d') . ' to ' . $endDt->format('Y-m-d') . ' | op=' . $operacao . ' | dry_run=' . ($dryRun ? 'yes' : 'no'));

$rawRows = [];
$apiDays = 0;
$apiEvents = 0;

foreach ($days as $index => $dateStr) {
    try {
        $json = fetch_codi_day($dateStr, $operacao);
    } catch (Throwable $e) {
        log_line($dateStr . ' fetch error: ' . $e->getMessage());
        continue;
    }

    $items = $json['data'] ?? [];
    if (!is_array($items) || empty($items)) {
        continue;
    }

    $apiDays++;
    $apiEvents += count($items);

    foreach ($items as $item) {
        $ordens = $item['ordens'] ?? [];
        if (!is_array($ordens) || empty($ordens)) {
            continue;
        }

        $inicio = $item['inicio'] ?? null;
        $fim = $item['fim'] ?? null;
        $paradaNome = '';
        if (isset($item['parada']) && is_array($item['parada'])) {
            $paradaNome = normalizar_nome_parada($item['parada']['nomeParada'] ?? '');
        }
        $isTarget = eh_parada_alvo($paradaNome);

        foreach ($ordens as $ordem) {
            $ordemProd = $ordem['ordemProducao'] ?? [];
            $ordemOp = is_array($ordemProd) ? normalizar_ordem((string) ($ordemProd['ordem'] ?? '')) : '';
            $sku = is_array($ordemProd) ? trim((string) ($ordemProd['item']['codItem'] ?? '')) : '';
            $identificador = $ordemOp !== '' ? $ordemOp : $sku;
            $quantidade = (float) ($ordem['quantidadeBoasItem'] ?? 0);

            if ($identificador === '' || (!$isTarget && $quantidade <= 0)) {
                continue;
            }

            $rawRows[] = [
                'data_evento' => $dateStr,
                'ordem_op' => $identificador,
                'quantidade' => $quantidade,
                'inicio_evento' => $inicio,
                'fim_evento' => $fim,
                'parada_nomeParada' => $paradaNome,
                'setup_duracao_minutos' => $isTarget ? calcular_duracao_minutos($inicio, $fim) : 0.0,
                'setup_eventos_count' => $isTarget ? 1 : 0,
            ];
        }
    }

    if ((($index + 1) % 10) === 0) {
        log_line('Processed ' . ($index + 1) . '/' . count($days) . ' days | raw rows=' . count($rawRows));
    }
}

$grouped = [];
foreach ($rawRows as $row) {
    $key = $row['data_evento'] . '|' . $row['ordem_op'];
    if (!isset($grouped[$key])) {
        $grouped[$key] = $row;
        continue;
    }

    $grouped[$key]['quantidade'] += $row['quantidade'];
    $grouped[$key]['setup_duracao_minutos'] += $row['setup_duracao_minutos'];
    $grouped[$key]['setup_eventos_count'] += $row['setup_eventos_count'];

    $nomeAtual = $grouped[$key]['parada_nomeParada'];
    $nomeNovo = normalizar_nome_parada($row['parada_nomeParada']);
    if (prioridade_parada($nomeNovo) > prioridade_parada($nomeAtual) || ($nomeAtual === '' && $nomeNovo !== '')) {
        $grouped[$key]['parada_nomeParada'] = $nomeNovo;
    }

    if (!$grouped[$key]['inicio_evento'] || ($row['inicio_evento'] && $row['inicio_evento'] < $grouped[$key]['inicio_evento'])) {
        $grouped[$key]['inicio_evento'] = $row['inicio_evento'];
    }
    if (!$grouped[$key]['fim_evento'] || ($row['fim_evento'] && $row['fim_evento'] > $grouped[$key]['fim_evento'])) {
        $grouped[$key]['fim_evento'] = $row['fim_evento'];
    }
}

log_line('Grouped rows=' . count($grouped));

if (!$dryRun) {
    $sql = "
        INSERT INTO realizado_2026_excel
            (data_evento, ordem_op, quantidade, inicio_evento, fim_evento, parada_nomeParada, setup_duracao_minutos, setup_eventos_count)
        VALUES
            (?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            quantidade = VALUES(quantidade),
            inicio_evento = VALUES(inicio_evento),
            fim_evento = VALUES(fim_evento),
            parada_nomeParada = COALESCE(NULLIF(VALUES(parada_nomeParada), ''), parada_nomeParada),
            setup_duracao_minutos = VALUES(setup_duracao_minutos),
            setup_eventos_count = VALUES(setup_eventos_count),
            imported_at = NOW()
    ";

    $stmt = $pdo->prepare($sql);
    $affected = 0;
    foreach ($grouped as $row) {
        $stmt->execute([
            $row['data_evento'],
            $row['ordem_op'],
            $row['quantidade'],
            $row['inicio_evento'],
            $row['fim_evento'],
            $row['parada_nomeParada'] !== '' ? $row['parada_nomeParada'] : null,
            $row['setup_duracao_minutos'],
            $row['setup_eventos_count'],
        ]);
        $affected++;
    }

    log_line('Upserted rows=' . $affected);
}

$diag = $pdo->query("
    SELECT
        COUNT(*) AS total,
        MIN(data_evento) AS min_data,
        MAX(data_evento) AS max_data,
        SUM(parada_nomeParada IS NOT NULL AND TRIM(parada_nomeParada) <> '') AS preenchidos,
        SUM(parada_nomeParada = 'TROCA DE KIT') AS kit,
        SUM(parada_nomeParada = 'TROCA DE LIQUIDO') AS liquido,
        SUM(COALESCE(setup_duracao_minutos, 0) > 0) AS setup_rows,
        SUM(COALESCE(setup_eventos_count, 0) > 0) AS setup_events,
        COUNT(DISTINCT CASE WHEN COALESCE(setup_duracao_minutos, 0) > 0 THEN ordem_op END) AS ops_com_setup
    FROM realizado_2026_excel
")->fetch(PDO::FETCH_ASSOC);

print_r($diag);
log_line('api_days=' . $apiDays . ' api_events=' . $apiEvents . ' raw_rows=' . count($rawRows) . ' grouped=' . count($grouped));
