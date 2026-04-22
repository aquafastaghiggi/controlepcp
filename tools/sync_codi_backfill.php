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

function normalizar_tipo_parada($valor): string
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

function chave_evento_externa($codigoEvento, string $dataEvento, ?string $inicioEvento, ?string $fimEvento, string $ordemOp, string $paradaNome): string
{
    $codigo = trim((string) ($codigoEvento ?? ''));
    $ordem = normalizar_ordem($ordemOp);

    if ($codigo !== '') {
        return $codigo . '|' . $ordem;
    }

    return implode('|', [
        trim($dataEvento),
        trim((string) ($inicioEvento ?? '')),
        trim((string) ($fimEvento ?? '')),
        $ordem,
        normalizar_nome_parada($paradaNome) ?: 'SEM_PARADA',
    ]);
}

function insert_eventos_detalhe(\PDO $pdo, array $rows): array
{
    if (empty($rows)) {
        return [0, 0];
    }

    $sql = "
        INSERT INTO realizado_2026_eventos
            (evt_chave_externa, evt_codigo_evento, data_evento, ordem_op, quantidade, inicio_evento, fim_evento, duracao_evento_minutos, estado_evento, parada_nomeParada, parada_tipo_nome, setup_duracao_minutos, setup_eventos_count, payload_json)
        VALUES
            (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            evt_codigo_evento = VALUES(evt_codigo_evento),
            data_evento = VALUES(data_evento),
            ordem_op = VALUES(ordem_op),
            quantidade = VALUES(quantidade),
            inicio_evento = VALUES(inicio_evento),
            fim_evento = VALUES(fim_evento),
            duracao_evento_minutos = VALUES(duracao_evento_minutos),
            estado_evento = VALUES(estado_evento),
            parada_nomeParada = COALESCE(NULLIF(VALUES(parada_nomeParada), ''), parada_nomeParada),
            parada_tipo_nome = COALESCE(NULLIF(VALUES(parada_tipo_nome), ''), parada_tipo_nome),
            setup_duracao_minutos = VALUES(setup_duracao_minutos),
            setup_eventos_count = VALUES(setup_eventos_count),
            payload_json = VALUES(payload_json),
            imported_at = NOW()
    ";

    $stmt = $pdo->prepare($sql);
    $inserted = 0;
    $errors = 0;

    foreach ($rows as $row) {
        try {
            $stmt->execute([
                $row['evt_chave_externa'],
                $row['codigo_evento'],
                $row['data_evento'],
                $row['ordem_op'],
                $row['quantidade'],
                $row['inicio_evento'],
                $row['fim_evento'],
                $row['duracao_evento_minutos'],
                $row['estado_evento'],
                $row['parada_nomeParada'] !== '' ? $row['parada_nomeParada'] : null,
                $row['parada_tipo_nome'] !== '' ? $row['parada_tipo_nome'] : null,
                $row['setup_duracao_minutos'],
                $row['setup_eventos_count'],
                $row['payload_json'],
            ]);
            $inserted++;
        } catch (Throwable $e) {
            $errors++;
        }
    }

    return [$inserted, $errors];
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

function ensure_sync_status_table(PDO $pdo): void
{
    $pdo->exec(
        "
        CREATE TABLE IF NOT EXISTS codi_sync_status (
            sync_key VARCHAR(32) NOT NULL,
            sync_date DATE NULL,
            is_running TINYINT(1) NOT NULL DEFAULT 0,
            stage_code VARCHAR(64) NOT NULL DEFAULT 'idle',
            stage_label VARCHAR(120) NOT NULL DEFAULT 'Idle',
            stage_detail TEXT NULL,
            stage_index INT NOT NULL DEFAULT 0,
            stage_total INT NOT NULL DEFAULT 0,
            backend VARCHAR(16) NULL,
            started_at DATETIME NULL,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            finished_at DATETIME NULL,
            last_error TEXT NULL,
            records_today INT NOT NULL DEFAULT 0,
            last_sync_at DATETIME NULL,
            PRIMARY KEY (sync_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        "
    );
}

function set_sync_status(PDO $pdo, array $data): void
{
    ensure_sync_status_table($pdo);

    $sql = "
        INSERT INTO codi_sync_status (
            sync_key, sync_date, is_running, stage_code, stage_label, stage_detail,
            stage_index, stage_total, backend, started_at, updated_at, finished_at,
            last_error, records_today, last_sync_at
        ) VALUES (
            :sync_key, :sync_date, :is_running, :stage_code, :stage_label, :stage_detail,
            :stage_index, :stage_total, :backend, :started_at, NOW(), :finished_at,
            :last_error, :records_today, :last_sync_at
        )
        ON DUPLICATE KEY UPDATE
            sync_date = VALUES(sync_date),
            is_running = VALUES(is_running),
            stage_code = VALUES(stage_code),
            stage_label = VALUES(stage_label),
            stage_detail = VALUES(stage_detail),
            stage_index = VALUES(stage_index),
            stage_total = VALUES(stage_total),
            backend = VALUES(backend),
            started_at = COALESCE(codi_sync_status.started_at, VALUES(started_at)),
            updated_at = NOW(),
            finished_at = VALUES(finished_at),
            last_error = VALUES(last_error),
            records_today = VALUES(records_today),
            last_sync_at = VALUES(last_sync_at)
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        'sync_key' => $data['sync_key'] ?? 'codi',
        'sync_date' => $data['sync_date'] ?? date('Y-m-d'),
        'is_running' => !empty($data['is_running']) ? 1 : 0,
        'stage_code' => (string) ($data['stage_code'] ?? 'idle'),
        'stage_label' => (string) ($data['stage_label'] ?? 'Idle'),
        'stage_detail' => $data['stage_detail'] ?? null,
        'stage_index' => (int) ($data['stage_index'] ?? 0),
        'stage_total' => (int) ($data['stage_total'] ?? 0),
        'backend' => $data['backend'] ?? null,
        'started_at' => $data['started_at'] ?? null,
        'finished_at' => $data['finished_at'] ?? null,
        'last_error' => $data['last_error'] ?? null,
        'records_today' => (int) ($data['records_today'] ?? 0),
        'last_sync_at' => $data['last_sync_at'] ?? null,
    ]);
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
$startedAt = date('Y-m-d H:i:s');
$stageTotal = 6;
set_sync_status($pdo, [
    'sync_key' => 'codi',
    'sync_date' => date('Y-m-d'),
    'is_running' => 1,
    'stage_code' => 'starting',
    'stage_label' => 'Iniciando',
    'stage_detail' => 'Preparando backfill CODI.',
    'stage_index' => 1,
    'stage_total' => $stageTotal,
    'backend' => 'php',
    'started_at' => $startedAt,
    'finished_at' => null,
    'last_error' => null,
    'records_today' => 0,
    'last_sync_at' => null,
]);
$days = [];
for ($day = $startDt; $day <= $endDt; $day = $day->modify('+1 day')) {
    $days[] = $day->format('Y-m-d');
}

log_line('Backfill CODI from ' . $startDt->format('Y-m-d') . ' to ' . $endDt->format('Y-m-d') . ' | op=' . $operacao . ' | dry_run=' . ($dryRun ? 'yes' : 'no'));

$mainRows = [];
$detailRows = [];
$apiDays = 0;
$apiEvents = 0;
$fetchedDays = [];

foreach ($days as $index => $dateStr) {
    set_sync_status($pdo, [
        'sync_key' => 'codi',
        'sync_date' => date('Y-m-d'),
        'is_running' => 1,
        'stage_code' => 'consulting_codi',
        'stage_label' => 'Consultando CODI',
        'stage_detail' => 'Buscando eventos de ' . $dateStr . ' (' . ($index + 1) . '/' . count($days) . ')',
        'stage_index' => 2,
        'stage_total' => $stageTotal,
        'backend' => 'php',
        'started_at' => $startedAt,
        'finished_at' => null,
        'last_error' => null,
        'records_today' => 0,
        'last_sync_at' => null,
    ]);
    try {
        $json = fetch_codi_day($dateStr, $operacao);
    } catch (Throwable $e) {
        log_line($dateStr . ' fetch error: ' . $e->getMessage());
        continue;
    }

    // Mark this day as successfully fetched, even if CODI returned an empty dataset,
    // so we can reconcile (delete + reinsert) the brute/aggregate for this day.
    $fetchedDays[$dateStr] = true;

    $items = $json['data'] ?? [];
    if (!is_array($items) || empty($items)) {
        continue;
    }

    $apiDays++;
    $apiEvents += count($items);

    foreach ($items as $item) {
        $codigoEvento = $item['codigoEvento'] ?? null;
        $ordens = $item['ordens'] ?? [];
        if (!is_array($ordens) || empty($ordens)) {
            continue;
        }

        $inicio = $item['inicio'] ?? null;
        $fim = $item['fim'] ?? null;
        $paradaNome = '';
        $paradaTipoNome = '';
        if (isset($item['parada']) && is_array($item['parada'])) {
            $paradaNome = normalizar_nome_parada($item['parada']['nomeParada'] ?? '');
            if (isset($item['parada']['tipoParada']) && is_array($item['parada']['tipoParada'])) {
                $paradaTipoNome = normalizar_tipo_parada($item['parada']['tipoParada']['nomeTipoParada'] ?? '');
            }
        }
        $isTarget = eh_parada_alvo($paradaNome);

        foreach ($ordens as $ordem) {
            $ordemProd = $ordem['ordemProducao'] ?? [];
            $ordemOp = is_array($ordemProd) ? normalizar_ordem((string) ($ordemProd['ordem'] ?? '')) : '';
            $sku = is_array($ordemProd) ? trim((string) ($ordemProd['item']['codItem'] ?? '')) : '';
            $identificador = $ordemOp !== '' ? $ordemOp : $sku;
            $quantidade = (float) ($ordem['quantidadeBoasItem'] ?? 0);

            if ($identificador === '') {
                continue;
            }

            $eventoDuracao = calcular_duracao_minutos($inicio, $fim);
            $detailRows[] = [
                'evt_chave_externa' => chave_evento_externa($codigoEvento, $dateStr, $inicio, $fim, $identificador, $paradaNome),
                'codigo_evento' => $codigoEvento,
                'data_evento' => $dateStr,
                'ordem_op' => $identificador,
                'quantidade' => $quantidade,
                'inicio_evento' => $inicio,
                'fim_evento' => $fim,
                'duracao_evento_minutos' => $eventoDuracao,
                'estado_evento' => (string) ($item['estado'] ?? ''),
                'parada_nomeParada' => $paradaNome,
                'parada_tipo_nome' => $paradaTipoNome,
                'setup_duracao_minutos' => $isTarget ? $eventoDuracao : 0.0,
                'setup_eventos_count' => $isTarget ? 1 : 0,
                'payload_json' => json_encode($item, JSON_UNESCAPED_UNICODE),
            ];

            if (!$isTarget && $quantidade <= 0) {
                continue;
            }

            $mainRows[] = [
                'data_evento' => $dateStr,
                'ordem_op' => $identificador,
                'quantidade' => $quantidade,
                'inicio_evento' => $inicio,
                'fim_evento' => $fim,
                'parada_nomeParada' => $paradaNome,
                'setup_duracao_minutos' => $isTarget ? $eventoDuracao : 0.0,
                'setup_eventos_count' => $isTarget ? 1 : 0,
            ];
        }
    }

    if ((($index + 1) % 10) === 0) {
        log_line('Processed ' . ($index + 1) . '/' . count($days) . ' days | main rows=' . count($mainRows) . ' | detail rows=' . count($detailRows));
    }
}

set_sync_status($pdo, [
    'sync_key' => 'codi',
    'sync_date' => date('Y-m-d'),
    'is_running' => 1,
    'stage_code' => 'processing_data',
    'stage_label' => 'Processando dados',
    'stage_detail' => 'Consolidando os registros baixados.',
    'stage_index' => 3,
    'stage_total' => $stageTotal,
    'backend' => 'php',
    'started_at' => $startedAt,
    'finished_at' => null,
    'last_error' => null,
    'records_today' => 0,
    'last_sync_at' => null,
]);

$grouped = [];
foreach ($mainRows as $row) {
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
    $reconcileDays = array_keys($fetchedDays);
    if (!empty($reconcileDays)) {
        $placeholders = implode(',', array_fill(0, count($reconcileDays), '?'));
    }

    set_sync_status($pdo, [
        'sync_key' => 'codi',
        'sync_date' => date('Y-m-d'),
        'is_running' => 1,
        'stage_code' => 'saving_aggregate',
        'stage_label' => 'Gravando agregado',
        'stage_detail' => 'Persistindo realizado_2026_excel.',
        'stage_index' => 4,
        'stage_total' => $stageTotal,
        'backend' => 'php',
        'started_at' => $startedAt,
        'finished_at' => null,
        'last_error' => null,
        'records_today' => 0,
        'last_sync_at' => null,
    ]);

    try {
        $pdo->beginTransaction();

        // Reconcile per reprocessed day: remove any stale rows for the days we fetched from CODI,
        // then insert only the current payload. This prevents old events from lingering when CODI changes.
        if (!empty($reconcileDays)) {
            $stmtDelEvents = $pdo->prepare("DELETE FROM realizado_2026_eventos WHERE data_evento IN ($placeholders)");
            $stmtDelEvents->execute($reconcileDays);

            $stmtDelExcel = $pdo->prepare("DELETE FROM realizado_2026_excel WHERE data_evento IN ($placeholders)");
            $stmtDelExcel->execute($reconcileDays);
        }

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
        [$detailInserted, $detailErrors] = insert_eventos_detalhe($pdo, $detailRows);
        log_line('Detail rows inserted=' . $detailInserted . ' | errors=' . $detailErrors);

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

set_sync_status($pdo, [
    'sync_key' => 'codi',
    'sync_date' => date('Y-m-d'),
    'is_running' => 0,
    'stage_code' => 'done',
    'stage_label' => 'Concluído',
    'stage_detail' => 'Backfill finalizado com sucesso.',
    'stage_index' => $stageTotal,
    'stage_total' => $stageTotal,
    'backend' => 'php',
    'started_at' => $startedAt,
    'finished_at' => date('Y-m-d H:i:s'),
    'last_error' => null,
    'records_today' => 0,
    'last_sync_at' => date('Y-m-d H:i:s'),
]);

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
log_line('api_days=' . $apiDays . ' api_events=' . $apiEvents . ' main_rows=' . count($mainRows) . ' detail_rows=' . count($detailRows) . ' grouped=' . count($grouped));
