<?php

declare(strict_types=1);

require __DIR__ . '/src/bootstrap.php';

use App\Auth\Auth;
use App\Data\DatabaseData;
use App\Database\Connection;
use App\Services\WorkCalendar;
use App\Support\DateTimeHelper;

Auth::startSession();

$pdo = Connection::get();

function h(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function formatMinutesClock(float $minutes, bool $signed = false): string
{
    $rounded = (int) round($minutes);
    $sign = '';
    if ($signed) {
        if ($rounded > 0) {
            $sign = '+';
        } elseif ($rounded < 0) {
            $sign = '-';
        }
    } elseif ($rounded < 0) {
        $sign = '-';
    }

    $rounded = abs($rounded);
    $hours = intdiv($rounded, 60);
    $mins = $rounded % 60;

    return sprintf('%s%02d:%02d', $sign, $hours, $mins);
}

function formatMinutes(float $minutes): string
{
    return formatMinutesClock($minutes, false);
}

function formatQty(float $value): string
{
    return number_format($value, 2, ',', '.');
}

function formatQtyRounded(float $value): string
{
    return number_format((float) round($value), 0, ',', '.');
}

function formatDurationClock(float $minutes): string
{
    return formatMinutesClock(max(0.0, $minutes), false);
}

function formatSetupReferenceLabel(string $tipoBloco): string
{
    return $tipoBloco === 'setup_principal' ? 'Setup principal' : 'Parada complementar';
}

function formatSignedMinutes(float $minutes): string
{
    return formatMinutesClock($minutes, true);
}

function normalizeDateInput(?string $value): string
{
    $value = trim((string) $value);
    if ($value === '') {
        return '';
    }

    $dt = DateTimeImmutable::createFromFormat('Y-m-d', $value);
    if ($dt instanceof DateTimeImmutable) {
        return $dt->format('Y-m-d');
    }

    return '';
}

function parseDateValue(?string $value): ?DateTimeImmutable
{
    $value = trim((string) $value);
    if ($value === '') {
        return null;
    }

    $normalized = str_replace('T', ' ', $value);
    $formats = [
        'Y-m-d H:i:s',
        'Y-m-d H:i',
        'Y-m-d',
        'd/m/Y H:i:s',
        'd/m/Y H:i',
        'd/m/Y',
    ];

    foreach ($formats as $format) {
        $dt = DateTimeImmutable::createFromFormat($format, $normalized);
        if ($dt instanceof DateTimeImmutable) {
            $errors = DateTimeImmutable::getLastErrors();
            $warningCount = is_array($errors) ? (int) ($errors['warning_count'] ?? 0) : 0;
            $errorCount = is_array($errors) ? (int) ($errors['error_count'] ?? 0) : 0;
            if ($warningCount === 0 && $errorCount === 0) {
                return $dt;
            }
        }
    }

    try {
        return new DateTimeImmutable($normalized);
    } catch (Throwable) {
        return null;
    }
}

function formatDateDisplay(?string $value, string $empty = 'S/data'): string
{
    $dt = parseDateValue($value);
    if (!$dt instanceof DateTimeImmutable) {
        return $empty;
    }

    return $dt->format('d/m/Y');
}

function formatTimeDisplay(?string $value, string $empty = '--'): string
{
    $dt = parseDateValue($value);
    if (!$dt instanceof DateTimeImmutable) {
        return $empty;
    }

    return $dt->format('H:i');
}

function formatDateTimeDisplay(?string $value, string $empty = 'S/data'): string
{
    $dt = parseDateValue($value);
    if (!$dt instanceof DateTimeImmutable) {
        return $empty;
    }

    if ($dt->format('H:i:s') === '00:00:00' && !preg_match('/\d{2}:\d{2}/', (string) $value)) {
        return $dt->format('d/m/Y');
    }

    return $dt->format('d/m/Y H:i');
}

function lineFilterKey(?string $lineCode): string
{
    $lineCode = trim((string) $lineCode);
    return strtoupper(preg_replace('/\s+/', '', $lineCode));
}

function lineLabel(?string $lineCode): string
{
    $lineCode = trim((string) $lineCode);
    if ($lineCode === '') {
        return 'S/linha';
    }

    $normalized = lineFilterKey($lineCode);
    if (preg_match('/^LN0*(\d+)$/', $normalized, $match)) {
        return sprintf('Linha %02d', (int) $match[1]);
    }

    return $lineCode;
}

function resolveProgramOp(array &$itemsBySku, string $sku, float $plannedQty): string
{
    $sku = trim($sku);
    if ($sku === '' || !isset($itemsBySku[$sku])) {
        return 'S/OP';
    }

    foreach ($itemsBySku[$sku] as $idx => $item) {
        if (!empty($item['used'])) {
            continue;
        }

        if (abs((float) $item['quantidade'] - $plannedQty) < 0.0001) {
            $itemsBySku[$sku][$idx]['used'] = true;
            return (string) $item['op'];
        }
    }

    foreach ($itemsBySku[$sku] as $idx => $item) {
        if (empty($item['used'])) {
            $itemsBySku[$sku][$idx]['used'] = true;
            return (string) $item['op'];
        }
    }

    return 'S/OP';
}

function buildWorkCalendarForLine(?string $lineCode): ?WorkCalendar
{
    $lineCode = trim((string) $lineCode);
    if ($lineCode === '') {
        return null;
    }

    $datasets = (new DatabaseData(null, $lineCode))->all();
    $calendarData = is_array($datasets['calendar'] ?? null) ? $datasets['calendar'] : [];
    $rawIntervals = array_values(is_array($calendarData['intervals'] ?? null) ? $calendarData['intervals'] : []);
    if ($rawIntervals === []) {
        return null;
    }

    $intervals = [];
    foreach ($rawIntervals as $index => $interval) {
        if (!is_array($interval)) {
            continue;
        }

        $intervals[] = $interval + ['order' => $index + 1];
    }

    if ($intervals === []) {
        return null;
    }

    return new WorkCalendar(
        $intervals,
        is_array($calendarData['working_days'] ?? null) ? $calendarData['working_days'] : [1, 2, 3, 4, 5],
        is_array($calendarData['holidays'] ?? null) ? $calendarData['holidays'] : [],
        []
    );
}

function getRowSetupStatus(array $row): array
{
    $setupPlan = (float) ($row['setup_previsto_min'] ?? 0);
    $setupReal = (float) ($row['setup_realizado_min'] ?? 0);
    $setupEvents = (int) ($row['setup_realizado_eventos'] ?? 0);
    $setupDiff = $setupReal - $setupPlan;
    $absSetupDiff = abs($setupDiff);
    $critical = $setupPlan > 0.01 && $absSetupDiff >= 30.0;

    if ($setupEvents > 0) {
        return [
            'row_class' => 'row-realized' . ($critical ? ' row-critical' : ''),
            'tag_class' => 'tag-success',
            'label' => $setupEvents === 1 ? '1 evento' : sprintf('%d eventos', $setupEvents),
            'title' => 'Setup realizado encontrado',
            'status_key' => $absSetupDiff <= 1.0 ? 'no_prazo' : ($setupDiff > 1.0 ? 'acima' : 'abaixo'),
            'critical' => $critical,
        ];
    }

    if ($setupPlan > 0.01) {
        return [
            'row_class' => 'row-missing' . ($critical ? ' row-critical' : ''),
            'tag_class' => 'tag-warning',
            'label' => 'Sem evento',
            'title' => 'Setup previsto sem apontamento alvo',
            'status_key' => 'sem_evento',
            'critical' => $critical,
        ];
    }

    return [
        'row_class' => 'row-neutral',
        'tag_class' => 'tag-muted',
        'label' => 'Sem setup',
        'title' => 'Sem setup previsto nesta OP',
        'status_key' => 'sem_setup',
        'critical' => false,
    ];
}

function isSetupTargetParada(?string $nomeParada): bool
{
    $nomeParada = strtoupper(trim((string) $nomeParada));
    return in_array($nomeParada, ['TROCA DE KIT', 'TROCA DE LIQUIDO'], true);
}

function formatNullableEventText(?string $value): string
{
    $value = trim((string) $value);
    return $value !== '' ? $value : 'Sem classificação';
}

function tableExists(PDO $pdo, string $tableName): bool
{
    static $cache = [];

    if (array_key_exists($tableName, $cache)) {
        return $cache[$tableName];
    }

    try {
        $stmt = $pdo->prepare(
            "SELECT 1
             FROM information_schema.tables
             WHERE table_schema = DATABASE()
               AND table_name = ?
             LIMIT 1"
        );
        $stmt->execute([$tableName]);
        $cache[$tableName] = (bool) $stmt->fetchColumn();
    } catch (Throwable) {
        $cache[$tableName] = false;
    }

    return $cache[$tableName];
}

function normalizeParadaLabel(?string $value): string
{
    $value = trim((string) $value);
    return $value !== '' ? $value : 'Sem classificação';
}

function paradaGroupKey(?string $value): string
{
    $value = trim((string) $value);
    return $value !== '' ? strtoupper($value) : '__NULL__';
}

function isVisibleComplementaryParada(?string $value): bool
{
    $label = trim((string) $value);
    if ($label === '') {
        return false;
    }

    return normalizeParadaLabel($label) !== 'Sem classificaÃ§Ã£o';
}

function isVisibleComplementaryParadaVisible(?string $value): bool
{
    $label = trim((string) $value);
    if ($label === '') {
        return false;
    }

    $upperLabel = function_exists('mb_strtoupper')
        ? mb_strtoupper($label, 'UTF-8')
        : strtoupper($label);

    $asciiLabel = preg_replace('/[^A-Z]/u', '', $upperLabel);

    if ($asciiLabel === 'DESCONEXO') {
        return false;
    }

    if ($upperLabel === 'DESCONEXÃO' || $upperLabel === 'DESCONEXAO') {
        return false;
    }

    return isVisibleComplementaryParada($label);
}

function fetchOpDetail(PDO $pdo, string $op, string $periodStart, string $periodEnd, ?float $setupReferenceMinutes = null): array
{
    $rawEventsTableAvailable = tableExists($pdo, 'realizado_2026_eventos');
    $setupWindowsByDate = [];

    $stmt = $pdo->prepare(
        "
        SELECT
            id,
            data_evento,
            ordem_op,
            quantidade,
            inicio_evento,
            fim_evento,
            parada_nomeParada,
            setup_duracao_minutos,
            setup_eventos_count
        FROM realizado_2026_excel
        WHERE ordem_op = ?
          AND data_evento BETWEEN ? AND ?
        ORDER BY data_evento ASC, COALESCE(inicio_evento, ''), COALESCE(fim_evento, ''), id ASC
        "
    );
    $stmt->execute([$op, $periodStart, $periodEnd]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $principal = [];
    $apoio = [];
    $supportNamedCounts = [];
    $groupedParadas = [];
    $summary = [
        'rows_total' => 0,
        'raw_rows_total' => 0,
        'principal_rows' => 0,
        'apoio_rows' => 0,
        'principal_events' => 0,
        'apoio_events' => 0,
        'principal_minutes' => 0.0,
        'apoio_minutes' => 0.0,
    ];
    $hasOtherNamedParadas = false;

    foreach ($rows as $row) {
        $nomeParada = (string) ($row['parada_nomeParada'] ?? '');
        if (!isSetupTargetParada($nomeParada)) {
            continue;
        }

        $duration = (float) ($row['setup_duracao_minutos'] ?? 0);
        $events = (int) ($row['setup_eventos_count'] ?? 0);
        $dataEvento = (string) ($row['data_evento'] ?? '');
        $setupWindow = $dataEvento !== '' ? ($setupWindowsByDate[$dataEvento] ?? null) : null;

        $principal[] = [
            'data_evento' => $dataEvento,
            'inicio_evento' => (string) ($setupWindow['inicio_evento'] ?? $row['inicio_evento'] ?? ''),
            'fim_evento' => (string) ($setupWindow['fim_evento'] ?? $row['fim_evento'] ?? ''),
            'parada_nomeParada' => formatNullableEventText($nomeParada),
            'parada_tipo_nome' => 'Setup principal',
            'setup_referencia' => formatDurationClock($setupReferenceMinutes ?? $duration),
            'setup_referencia_detail' => 'Setup previsto',
            'setup_duracao_minutos' => $duration,
            'setup_eventos_count' => $events,
            'quantidade' => (float) ($row['quantidade'] ?? 0),
            'tipo_bloco' => 'setup_principal',
            'origem' => 'agregado',
        ];
        $summary['principal_rows'] += 1;
        $summary['principal_events'] += $events > 0 ? $events : 1;
        $summary['principal_minutes'] += $duration;

        $groupKey = paradaGroupKey($nomeParada);
        if (!isset($groupedParadas[$groupKey])) {
            $groupedParadas[$groupKey] = [
                'parada_nomeParada' => normalizeParadaLabel($nomeParada),
                'eventos_count' => 0,
                'duracao_total_minutos' => 0.0,
                'is_principal' => true,
                'is_null' => trim($nomeParada) === '',
                'categoria_label' => 'Setup principal',
            ];
        }
        $groupedParadas[$groupKey]['eventos_count'] += 1;
        $groupedParadas[$groupKey]['duracao_total_minutos'] += $duration;
    }

    if ($rawEventsTableAvailable) {
        $rawStmt = $pdo->prepare(
            "
            SELECT
                evt_id,
                evt_codigo_evento,
                data_evento,
                ordem_op,
                quantidade,
                inicio_evento,
                fim_evento,
                duracao_evento_minutos,
                parada_nomeParada,
                parada_tipo_nome,
                setup_duracao_minutos,
                setup_eventos_count
            FROM realizado_2026_eventos
            WHERE ordem_op = ?
              AND data_evento BETWEEN ? AND ?
            ORDER BY data_evento ASC, COALESCE(inicio_evento, ''), COALESCE(fim_evento, ''), evt_id ASC
            "
        );
        $rawStmt->execute([$op, $periodStart, $periodEnd]);
        $rawRows = $rawStmt->fetchAll(PDO::FETCH_ASSOC);
        $summary['raw_rows_total'] = count($rawRows);

        foreach ($rawRows as $row) {
            $nomeParada = (string) ($row['parada_nomeParada'] ?? '');
            $dataEvento = (string) ($row['data_evento'] ?? '');
            if (isSetupTargetParada($nomeParada)) {
                if ($dataEvento !== '') {
                    if (!isset($setupWindowsByDate[$dataEvento])) {
                        $setupWindowsByDate[$dataEvento] = [
                            'inicio_evento' => (string) ($row['inicio_evento'] ?? ''),
                            'fim_evento' => (string) ($row['fim_evento'] ?? ''),
                        ];
                    } else {
                        $currentInicio = $setupWindowsByDate[$dataEvento]['inicio_evento'] ?? '';
                        $currentFim = $setupWindowsByDate[$dataEvento]['fim_evento'] ?? '';
                        $rowInicio = (string) ($row['inicio_evento'] ?? '');
                        $rowFim = (string) ($row['fim_evento'] ?? '');

                        if ($rowInicio !== '' && ($currentInicio === '' || $rowInicio < $currentInicio)) {
                            $setupWindowsByDate[$dataEvento]['inicio_evento'] = $rowInicio;
                        }
                        if ($rowFim !== '' && ($currentFim === '' || $rowFim > $currentFim)) {
                            $setupWindowsByDate[$dataEvento]['fim_evento'] = $rowFim;
                        }
                    }
                }
                continue;
            }

            if (!isVisibleComplementaryParadaVisible($nomeParada)) {
                continue;
            }

            $duracao = (float) ($row['duracao_evento_minutos'] ?? 0);
            $apoio[] = [
                'data_evento' => (string) ($row['data_evento'] ?? ''),
                'inicio_evento' => (string) ($row['inicio_evento'] ?? ''),
                'fim_evento' => (string) ($row['fim_evento'] ?? ''),
                'parada_nomeParada' => formatNullableEventText($nomeParada),
                'parada_tipo_nome' => formatNullableEventText((string) ($row['parada_tipo_nome'] ?? '')),
                'setup_referencia' => 'Contexto complementar',
                'setup_referencia_detail' => 'Somente contexto complementar',
                'setup_duracao_minutos' => 0.0,
                'duracao_evento_minutos' => $duracao,
                'setup_eventos_count' => (int) ($row['setup_eventos_count'] ?? 0),
                'quantidade' => (float) ($row['quantidade'] ?? 0),
                'tipo_bloco' => 'apoio',
                'origem' => 'bruto',
                'codigo_evento' => (string) ($row['evt_codigo_evento'] ?? ''),
            ];
            $summary['apoio_rows'] += 1;
            $summary['apoio_events'] += 1;
            $summary['apoio_minutes'] += $duracao;

            $groupKey = paradaGroupKey($nomeParada);
            if (!isset($groupedParadas[$groupKey])) {
                $groupedParadas[$groupKey] = [
                    'parada_nomeParada' => normalizeParadaLabel($nomeParada),
                    'eventos_count' => 0,
                    'duracao_total_minutos' => 0.0,
                    'is_principal' => false,
                    'is_null' => trim($nomeParada) === '',
                    'categoria_label' => trim($nomeParada) === '' ? 'Sem classificação' : 'Paradas complementares',
                ];
            }
            $groupedParadas[$groupKey]['eventos_count'] += 1;
            $groupedParadas[$groupKey]['duracao_total_minutos'] += $duracao;

            if (trim($nomeParada) !== '') {
                $hasOtherNamedParadas = true;
                $supportNamedCounts[$nomeParada] = ($supportNamedCounts[$nomeParada] ?? 0) + 1;
            }
        }
    } else {
        $summary['raw_rows_total'] = 0;
        foreach ($rows as $row) {
            $nomeParada = (string) ($row['parada_nomeParada'] ?? '');
            if (isSetupTargetParada($nomeParada)) {
                continue;
            }

            if (!isVisibleComplementaryParadaVisible($nomeParada)) {
                continue;
            }

            $duration = (float) ($row['setup_duracao_minutos'] ?? 0);
            $events = (int) ($row['setup_eventos_count'] ?? 0);
            $apoio[] = [
                'data_evento' => (string) ($row['data_evento'] ?? ''),
                'inicio_evento' => (string) ($row['inicio_evento'] ?? ''),
                'fim_evento' => (string) ($row['fim_evento'] ?? ''),
                'parada_nomeParada' => formatNullableEventText($nomeParada),
                'parada_tipo_nome' => 'Sem detalhe bruto',
                'setup_referencia' => 'Contexto complementar',
                'setup_referencia_detail' => 'Somente contexto complementar',
                'setup_duracao_minutos' => $duration,
                'setup_eventos_count' => $events,
                'quantidade' => (float) ($row['quantidade'] ?? 0),
                'tipo_bloco' => 'apoio',
                'origem' => 'agregado',
            ];
            $summary['apoio_rows'] += 1;
            $summary['apoio_events'] += $events > 0 ? $events : 0;
            $summary['apoio_minutes'] += $duration;

            $groupKey = paradaGroupKey($nomeParada);
            if (!isset($groupedParadas[$groupKey])) {
                $groupedParadas[$groupKey] = [
                    'parada_nomeParada' => normalizeParadaLabel($nomeParada),
                    'eventos_count' => 0,
                    'duracao_total_minutos' => 0.0,
                    'is_principal' => false,
                    'is_null' => trim($nomeParada) === '',
                    'categoria_label' => trim($nomeParada) === '' ? 'Sem classificação' : 'Paradas complementares',
                ];
            }
            $groupedParadas[$groupKey]['eventos_count'] += 1;
            $groupedParadas[$groupKey]['duracao_total_minutos'] += $duration;

            if (trim($nomeParada) !== '') {
                $hasOtherNamedParadas = true;
                $supportNamedCounts[$nomeParada] = ($supportNamedCounts[$nomeParada] ?? 0) + 1;
            }
        }
    }

    $summary['rows_total'] = $summary['principal_rows'] + $summary['apoio_rows'];

    $groupedParadas = array_values($groupedParadas);
    usort($groupedParadas, static function (array $a, array $b): int {
        $minutesA = (float) ($a['duracao_total_minutos'] ?? 0);
        $minutesB = (float) ($b['duracao_total_minutos'] ?? 0);
        if ($minutesA !== $minutesB) {
            return $minutesB <=> $minutesA;
        }

        $countA = (int) ($a['eventos_count'] ?? 0);
        $countB = (int) ($b['eventos_count'] ?? 0);
        if ($countA !== $countB) {
            return $countB <=> $countA;
        }

        return strnatcasecmp((string) ($a['parada_nomeParada'] ?? ''), (string) ($b['parada_nomeParada'] ?? ''));
    });

    return [
        'op' => $op,
        'period_start' => $periodStart,
        'period_end' => $periodEnd,
        'summary' => $summary,
        'paradas_agrupadas' => $groupedParadas,
        'principal' => $principal,
        'apoio' => $apoio,
        'has_other_named_paradas' => $hasOtherNamedParadas,
        'support_named_paradas' => $supportNamedCounts,
        'detail_source' => $rawEventsTableAvailable ? 'realizado_2026_eventos' : 'realizado_2026_excel',
    ];
}

if (($_GET['action'] ?? '') === 'op_detail') {
    header('Content-Type: application/json; charset=utf-8');

    $op = trim((string) ($_GET['op'] ?? ''));
    $periodStart = normalizeDateInput($_GET['period_start'] ?? null);
    $periodEnd = normalizeDateInput($_GET['period_end'] ?? null);
    $setupReferenceMinutes = isset($_GET['setup_plan_min']) ? (float) $_GET['setup_plan_min'] : null;

    if ($op === '' || $periodStart === '' || $periodEnd === '') {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Parâmetros inválidos para o detalhe da OP.',
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    if ($periodStart > $periodEnd) {
        [$periodStart, $periodEnd] = [$periodEnd, $periodStart];
    }

    $payload = fetchOpDetail($pdo, $op, $periodStart, $periodEnd, $setupReferenceMinutes);
    $payload['success'] = true;
    $payload['main_rule'] = 'TROCA DE KIT / TROCA DE LIQUIDO';

    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$programacoes = $pdo->query(
    "
    SELECT
        p.prg_id,
        p.prg_numero_op,
        p.prg_base_inicio,
        p.prg_eficiencia,
        l.lin_codigo,
        MIN(s.sch_data_inicio) AS data_inicio,
        MAX(s.sch_data_inicio) AS data_fim,
        MIN(s.sch_criado_em) AS programacao_criada_em,
        COUNT(*) AS total_linhas
    FROM prg_programas p
    INNER JOIN sch_linhas s ON s.sch_programa_id = p.prg_id
    LEFT JOIN lin_linhas l ON l.lin_id = p.prg_linha_id
    GROUP BY p.prg_id, p.prg_numero_op, p.prg_base_inicio, p.prg_eficiencia, l.lin_codigo
    ORDER BY MAX(s.sch_data_inicio) DESC, p.prg_id DESC
    LIMIT 40
    "
)->fetchAll(PDO::FETCH_ASSOC);

$linhasDisponiveis = $pdo->query(
    "
    SELECT DISTINCT
        COALESCE(NULLIF(TRIM(l.lin_codigo), ''), 'S/linha') AS lin_codigo
    FROM prg_programas p
    LEFT JOIN lin_linhas l ON l.lin_id = p.prg_linha_id
    WHERE p.prg_id IN (
        SELECT DISTINCT sch_programa_id
        FROM sch_linhas
    )
    "
)->fetchAll(PDO::FETCH_COLUMN);

usort($linhasDisponiveis, static function (string $a, string $b): int {
    $aKey = lineFilterKey($a);
    $bKey = lineFilterKey($b);
    $aNumeric = preg_match('/^LN0*(\d+)$/', $aKey, $aMatch) === 1;
    $bNumeric = preg_match('/^LN0*(\d+)$/', $bKey, $bMatch) === 1;

    if ($aNumeric && $bNumeric) {
        $aNumber = (int) $aMatch[1];
        $bNumber = (int) $bMatch[1];
        if ($aNumber !== $bNumber) {
            return $aNumber <=> $bNumber;
        }

        return strcasecmp($a, $b);
    }

    if ($aNumeric !== $bNumeric) {
        return $aNumeric ? -1 : 1;
    }

    return strcasecmp($a, $b);
});

$selectedLine = trim((string) ($_GET['linha'] ?? ''));
$selectedLine = $selectedLine !== '' ? $selectedLine : '';
$selectedPeriodStartInput = normalizeDateInput($_GET['data_inicio'] ?? null);
$selectedPeriodEndInput = normalizeDateInput($_GET['data_fim'] ?? null);
$selectedStatus = trim((string) ($_GET['status'] ?? 'all'));
if (!in_array($selectedStatus, ['all', 'realizado', 'positivo', 'negativo', 'sem_evento'], true)) {
    $selectedStatus = 'all';
}

$filteredProgramacoes = $programacoes;
if ($selectedLine !== '') {
    $filteredProgramacoes = array_values(array_filter(
        $programacoes,
        static fn(array $programacao): bool => lineFilterKey((string) ($programacao['lin_codigo'] ?? '')) === lineFilterKey($selectedLine)
    ));
}

usort($filteredProgramacoes, static function (array $a, array $b): int {
    $lineA = trim((string) ($a['lin_codigo'] ?? ''));
    $lineB = trim((string) ($b['lin_codigo'] ?? ''));
    $keyA = lineFilterKey($lineA);
    $keyB = lineFilterKey($lineB);
    $isNumericA = preg_match('/^LN0*(\d+)$/', $keyA, $matchA) === 1;
    $isNumericB = preg_match('/^LN0*(\d+)$/', $keyB, $matchB) === 1;

    if ($isNumericA && $isNumericB) {
        $numA = (int) $matchA[1];
        $numB = (int) $matchB[1];
        if ($numA !== $numB) {
            return $numA <=> $numB;
        }
    } elseif ($isNumericA !== $isNumericB) {
        return $isNumericA ? -1 : 1;
    } else {
        $cmpLine = strcasecmp($lineA, $lineB);
        if ($cmpLine !== 0) {
            return $cmpLine;
        }
    }

    $dataInicioA = (string) ($a['data_inicio'] ?? '');
    $dataInicioB = (string) ($b['data_inicio'] ?? '');
    if ($dataInicioA !== $dataInicioB) {
        return strcmp($dataInicioA, $dataInicioB);
    }

    $criadaEmA = (string) ($a['programacao_criada_em'] ?? '');
    $criadaEmB = (string) ($b['programacao_criada_em'] ?? '');
    if ($criadaEmA !== $criadaEmB) {
        return strcmp($criadaEmA, $criadaEmB);
    }

    return ((int) ($a['prg_id'] ?? 0)) <=> ((int) ($b['prg_id'] ?? 0));
});

$selectedProgramId = (int) ($_GET['programacao_id'] ?? 0);
if ($selectedProgramId <= 0 && !empty($filteredProgramacoes)) {
    $selectedProgramId = (int) $filteredProgramacoes[0]['prg_id'];
}

$selectedProgram = null;
foreach ($filteredProgramacoes as $programacao) {
    if ((int) $programacao['prg_id'] === $selectedProgramId) {
        $selectedProgram = $programacao;
        break;
    }
}

if ($selectedProgram === null && !empty($filteredProgramacoes)) {
    $selectedProgram = $filteredProgramacoes[0];
    $selectedProgramId = (int) $selectedProgram['prg_id'];
}

if ($selectedProgram === null && !empty($programacoes)) {
    $selectedProgram = $programacoes[0];
    $selectedProgramId = (int) $selectedProgram['prg_id'];
}

$reportPeriodStart = $selectedPeriodStartInput !== ''
    ? $selectedPeriodStartInput
    : (!empty($selectedProgram['data_inicio'])
        ? (new DateTimeImmutable((string) $selectedProgram['data_inicio']))->modify('-1 day')->format('Y-m-d')
        : date('Y-m-d'));
$reportPeriodEnd = $selectedPeriodEndInput !== ''
    ? $selectedPeriodEndInput
    : (!empty($selectedProgram['data_fim'])
        ? (new DateTimeImmutable((string) $selectedProgram['data_fim']))->modify('+1 day')->format('Y-m-d')
        : date('Y-m-d'));

if ($reportPeriodStart > $reportPeriodEnd) {
    [$reportPeriodStart, $reportPeriodEnd] = [$reportPeriodEnd, $reportPeriodStart];
}

$reportRows = [];
$summary = [
    'ops' => 0,
    'setup_previsto' => 0.0,
    'setup_realizado' => 0.0,
    'producao_prevista' => 0.0,
    'producao_realizada' => 0.0,
    'setup_pendente' => 0.0,
    'ops_com_setup_realizado' => 0,
    'setup_eventos_total' => 0,
    'maior_desvio_positivo' => 0.0,
    'maior_desvio_negativo' => 0.0,
    'maior_desvio_positivo_op' => '',
    'maior_desvio_negativo_op' => '',
];
$operationalSummary = [
    'no_prazo' => 0,
    'acima' => 0,
    'abaixo' => 0,
    'sem_evento' => 0,
    'sem_setup' => 0,
];
$realizadoDiagnostics = [
    'rows' => 0,
    'classificados' => 0,
    'ops_classificadas' => 0,
    'kit' => 0,
    'liquido' => 0,
];

if ($selectedProgramId > 0) {
    $itemsStmt = $pdo->prepare(
        "
        SELECT
            pi.prg_id_item,
            pi.prg_sku,
            pi.prg_quantidade,
            pi.prg_sequencia,
            pi.prg_itens_op,
            pp.prd_descricao
        FROM prg_itens pi
        LEFT JOIN prd_produtos pp ON pp.prd_sku = pi.prg_sku
        WHERE pi.prg_programa_id = ?
        ORDER BY pi.prg_sequencia ASC, pi.prg_id_item ASC
        "
    );
    $itemsStmt->execute([$selectedProgramId]);
    $itemsRows = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

    $itemsBySku = [];
    foreach ($itemsRows as $item) {
        $sku = trim((string) ($item['prg_sku'] ?? ''));
        if ($sku === '') {
            continue;
        }

        $itemsBySku[$sku] ??= [];
        $itemsBySku[$sku][] = [
            'op' => (string) ($item['prg_itens_op'] ?? 'S/OP'),
            'quantidade' => (float) ($item['prg_quantidade'] ?? 0),
            'descricao' => trim((string) ($item['prd_descricao'] ?? '')),
            'used' => false,
        ];
    }

    $workCalendar = buildWorkCalendarForLine((string) ($selectedProgram['lin_codigo'] ?? ''));

    $scheduleStmt = $pdo->prepare(
        "
        SELECT
            s.sch_id,
            s.sch_sequencia,
            s.sch_tipo,
            s.sch_descricao,
            s.sch_quantidade,
            s.sch_sku,
            s.sch_duracao_minutos,
            s.sch_data_inicio,
            s.sch_inicio_producao,
            s.sch_fim_producao
        FROM sch_linhas s
        WHERE s.sch_programa_id = ?
        ORDER BY s.sch_data_inicio ASC, s.sch_sequencia ASC, s.sch_id ASC
        "
    );
    $scheduleStmt->execute([$selectedProgramId]);
    $scheduleRows = $scheduleStmt->fetchAll(PDO::FETCH_ASSOC);

    $pendingSetupMinutes = 0.0;
    $programSortIndex = 0;

    foreach ($scheduleRows as $row) {
        $tipo = strtolower(trim((string) ($row['sch_tipo'] ?? '')));
        $durationMinutes = (float) ($row['sch_duracao_minutos'] ?? 0);

        if ($tipo === 'setup') {
            $pendingSetupMinutes += max(0.0, $durationMinutes);
            continue;
        }

        $sku = trim((string) ($row['sch_sku'] ?? ''));
        $plannedQty = (float) ($row['sch_quantidade'] ?? 0);
        $op = resolveProgramOp($itemsBySku, $sku, $plannedQty);

        if (!isset($reportRows[$op])) {
            $reportRows[$op] = [
                'op' => $op,
                'sku' => $sku,
                'descricao' => trim((string) ($row['sch_descricao'] ?? '')),
                'setup_previsto_min' => 0.0,
                'setup_realizado_min' => 0.0,
                'producao_prevista' => 0.0,
                'producao_realizada' => 0.0,
                'tempo_previsto_min' => 0.0,
                'tempo_realizado_min' => 0.0,
                'setup_eventos' => 0,
                'setup_realizado_eventos' => 0,
                'program_order' => $programSortIndex++,
                'program_seq' => (int) ($row['sch_sequencia'] ?? 0),
                'program_data_inicio' => (string) ($row['sch_data_inicio'] ?? ''),
            ];
        }

        if ($reportRows[$op]['descricao'] === '') {
            $descFromSku = '';
            if ($sku !== '' && isset($itemsBySku[$sku][0]['descricao'])) {
                $descFromSku = trim((string) $itemsBySku[$sku][0]['descricao']);
            }
            $reportRows[$op]['descricao'] = trim((string) ($row['sch_descricao'] ?? '')) ?: $descFromSku ?: $sku;
        }

        $reportRows[$op]['sku'] = $reportRows[$op]['sku'] !== '' ? $reportRows[$op]['sku'] : $sku;
        $reportRows[$op]['setup_previsto_min'] += $pendingSetupMinutes;
        $reportRows[$op]['producao_prevista'] += $plannedQty;
        $reportRows[$op]['tempo_previsto_min'] += $durationMinutes;
        $tempoRealizadoMin = 0.0;
        if ($workCalendar instanceof WorkCalendar) {
            $inicioProducao = DateTimeHelper::fromLocalInput((string) ($row['sch_inicio_producao'] ?? ''));
            $fimProducao = DateTimeHelper::fromLocalInput((string) ($row['sch_fim_producao'] ?? ''));

            if ($inicioProducao instanceof DateTimeImmutable && $fimProducao instanceof DateTimeImmutable) {
                $tempoRealizadoMin = (float) $workCalendar->workingMinutesBetween($inicioProducao, $fimProducao);
            }
        }

        $reportRows[$op]['tempo_realizado_min'] += max(0.0, $tempoRealizadoMin);
        $reportRows[$op]['setup_eventos'] += $pendingSetupMinutes > 0 ? 1 : 0;
        $pendingSetupMinutes = 0.0;
    }

    $summary['setup_pendente'] = $pendingSetupMinutes;

    $ops = array_values(array_unique(array_filter(array_map(static fn(array $row): string => trim((string) $row['op']), $reportRows), static fn(string $op): bool => $op !== '')));

    if (!empty($ops)) {
        $placeholders = implode(',', array_fill(0, count($ops), '?'));

        $realizadoStmt = $pdo->prepare(
            "
            SELECT
                ordem_op,
                SUM(quantidade) AS total_realizado,
                SUM(
                    CASE
                        WHEN parada_nomeParada IN ('TROCA DE KIT', 'TROCA DE LIQUIDO')
                        THEN COALESCE(setup_duracao_minutos, 0)
                        ELSE 0
                    END
                ) AS setup_realizado_min,
                SUM(
                    CASE
                        WHEN parada_nomeParada IN ('TROCA DE KIT', 'TROCA DE LIQUIDO')
                        THEN COALESCE(setup_eventos_count, 0)
                        ELSE 0
                    END
                ) AS setup_realizado_eventos
            FROM realizado_2026_excel
            WHERE data_evento BETWEEN ? AND ?
              AND ordem_op IN ($placeholders)
            GROUP BY ordem_op
            "
        );
        $realizadoStmt->execute(array_merge([$reportPeriodStart, $reportPeriodEnd], $ops));
        $realizadoRows = $realizadoStmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($realizadoRows as $row) {
            $op = trim((string) ($row['ordem_op'] ?? ''));
            if ($op === '' || !isset($reportRows[$op])) {
                continue;
            }

            $reportRows[$op]['producao_realizada'] = (float) ($row['total_realizado'] ?? 0);
            $reportRows[$op]['setup_realizado_min'] = (float) ($row['setup_realizado_min'] ?? 0);
            $reportRows[$op]['setup_realizado_eventos'] = (int) ($row['setup_realizado_eventos'] ?? 0);
        }

        $diagStmt = $pdo->prepare(
            "
            SELECT
                COUNT(*) AS rows_total,
                SUM(CASE WHEN parada_nomeParada IN ('TROCA DE KIT', 'TROCA DE LIQUIDO') THEN 1 ELSE 0 END) AS rows_classified,
                COUNT(DISTINCT CASE WHEN parada_nomeParada IN ('TROCA DE KIT', 'TROCA DE LIQUIDO') THEN ordem_op END) AS ops_classificadas,
                SUM(CASE WHEN parada_nomeParada = 'TROCA DE KIT' THEN 1 ELSE 0 END) AS kit,
                SUM(CASE WHEN parada_nomeParada = 'TROCA DE LIQUIDO' THEN 1 ELSE 0 END) AS liquido
            FROM realizado_2026_excel
            WHERE data_evento BETWEEN ? AND ?
              AND ordem_op IN ($placeholders)
            "
        );
        $diagStmt->execute(array_merge([$reportPeriodStart, $reportPeriodEnd], $ops));
        $diagRow = $diagStmt->fetch(PDO::FETCH_ASSOC) ?: [];
        $realizadoDiagnostics = [
            'rows' => (int) ($diagRow['rows_total'] ?? 0),
            'classificados' => (int) ($diagRow['rows_classified'] ?? 0),
            'ops_classificadas' => (int) ($diagRow['ops_classificadas'] ?? 0),
            'kit' => (int) ($diagRow['kit'] ?? 0),
            'liquido' => (int) ($diagRow['liquido'] ?? 0),
        ];
    }

    ksort($reportRows, SORT_NATURAL);

    foreach ($reportRows as $row) {
        $summary['ops'] += 1;
        $summary['setup_previsto'] += (float) $row['setup_previsto_min'];
        $summary['setup_realizado'] += (float) $row['setup_realizado_min'];
        $summary['producao_prevista'] += (float) $row['producao_prevista'];
        $summary['producao_realizada'] += (float) $row['producao_realizada'];
        $summary['setup_eventos_total'] += (int) $row['setup_realizado_eventos'];

        if ((float) $row['setup_realizado_min'] > 0.01) {
            $summary['ops_com_setup_realizado'] += 1;
        }

        $setupRowDiff = (float) $row['setup_realizado_min'] - (float) $row['setup_previsto_min'];
        if ($setupRowDiff > $summary['maior_desvio_positivo']) {
            $summary['maior_desvio_positivo'] = $setupRowDiff;
            $summary['maior_desvio_positivo_op'] = (string) $row['op'];
        }

        if ($setupRowDiff < $summary['maior_desvio_negativo']) {
            $summary['maior_desvio_negativo'] = $setupRowDiff;
            $summary['maior_desvio_negativo_op'] = (string) $row['op'];
        }
    }

    $sortableRows = [];
    foreach ($reportRows as $row) {
        $setupPlan = (float) ($row['setup_previsto_min'] ?? 0);
        $setupReal = (float) ($row['setup_realizado_min'] ?? 0);
        $setupEvents = (int) ($row['setup_realizado_eventos'] ?? 0);
        $setupRowDiff = $setupReal - $setupPlan;
        $prodRowDiff = (float) ($row['producao_realizada'] ?? 0) - (float) ($row['producao_prevista'] ?? 0);
        $setupStatus = getRowSetupStatus($row);
        $statusBucket = (string) ($setupStatus['status_key'] ?? 'sem_setup');

        if ($setupPlan > 0.01) {
            if ($setupEvents <= 0) {
                $operationalSummary['sem_evento'] += 1;
            } elseif (abs($setupRowDiff) <= 1.0) {
                $operationalSummary['no_prazo'] += 1;
            } elseif ($setupRowDiff > 1.0) {
                $operationalSummary['acima'] += 1;
            } else {
                $operationalSummary['abaixo'] += 1;
            }
        } else {
            $operationalSummary['sem_setup'] += 1;
        }

        $sortableRows[] = array_merge($row, [
            'setup_row_diff' => $setupRowDiff,
            'prod_row_diff' => $prodRowDiff,
            'setup_status_key' => $statusBucket,
            'row_class' => $setupStatus['row_class'] ?? 'row-neutral',
            'tag_class' => $setupStatus['tag_class'] ?? 'tag-muted',
            'tag_label' => $setupStatus['label'] ?? 'Sem setup',
            'tag_title' => $setupStatus['title'] ?? '',
            'is_critical' => !empty($setupStatus['critical']),
        ]);
    }

    usort($sortableRows, static function (array $a, array $b): int {
        $orderA = (int) ($a['program_order'] ?? PHP_INT_MAX);
        $orderB = (int) ($b['program_order'] ?? PHP_INT_MAX);
        if ($orderA !== $orderB) {
            return $orderA <=> $orderB;
        }

        $seqA = (int) ($a['program_seq'] ?? PHP_INT_MAX);
        $seqB = (int) ($b['program_seq'] ?? PHP_INT_MAX);
        if ($seqA !== $seqB) {
            return $seqA <=> $seqB;
        }

        $dateA = (string) ($a['program_data_inicio'] ?? '');
        $dateB = (string) ($b['program_data_inicio'] ?? '');
        if ($dateA !== $dateB) {
            return strcmp($dateA, $dateB);
        }

        return strnatcasecmp((string) ($a['op'] ?? ''), (string) ($b['op'] ?? ''));
    });

    // Regra operacional: a primeira OP da sequência real não herda setup anterior.
    if (!empty($sortableRows)) {
        $sortableRows[0]['is_first_sequence_op'] = true;
    }

    $reportRows = array_values(array_filter(
        $sortableRows,
        static function (array $row) use ($selectedStatus): bool {
            $plan = (float) ($row['setup_previsto_min'] ?? 0);
            $realEvents = (int) ($row['setup_realizado_eventos'] ?? 0);
            $diff = (float) ($row['setup_row_diff'] ?? 0);

            return match ($selectedStatus) {
                'realizado' => $realEvents > 0,
                'positivo' => $plan > 0.01 && $diff > 1.0,
                'negativo' => $plan > 0.01 && $diff < -1.0,
                'sem_evento' => $plan > 0.01 && $realEvents <= 0,
                default => true,
            };
        }
    ));

}

$setupDiff = $summary['setup_realizado'] - $summary['setup_previsto'];
$prodDiff = $summary['producao_realizada'] - $summary['producao_prevista'];
$setupPct = $summary['setup_previsto'] > 0 ? ($summary['setup_realizado'] / $summary['setup_previsto']) * 100 : 0.0;
$prodPct = $summary['producao_prevista'] > 0 ? ($summary['producao_realizada'] / $summary['producao_prevista']) * 100 : 0.0;

$programStart = $selectedProgram['data_inicio'] ?? null;
$programEnd = $selectedProgram['data_fim'] ?? null;
$programLabel = $selectedProgram
    ? sprintf(
        'OP da programação %s | Linha %s',
        (string) ($selectedProgram['prg_numero_op'] ?? 'S/OP'),
        trim((string) ($selectedProgram['lin_codigo'] ?? 'S/linha'))
    )
    : 'Programação não encontrada';

$setupRuleLabel = 'TROCA DE KIT / TROCA DE LIQUIDO';
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Relatório setup previsto x realizado</title>
    <style>
        :root {
            --bg: #f4f7fb;
            --card: #ffffff;
            --border: #d8e0ea;
            --text: #10243e;
            --muted: #64748b;
            --accent: #2563eb;
            --accent-2: #0f172a;
            --soft: #edf4ff;
            --warn: #f59e0b;
            --danger: #dc2626;
            --success: #059669;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            font-family: Arial, Helvetica, sans-serif;
            background:
                radial-gradient(circle at top left, rgba(37, 99, 235, 0.12), transparent 28%),
                linear-gradient(180deg, #f9fbff 0%, var(--bg) 100%);
            color: var(--text);
        }

        .page {
            max-width: 1500px;
            margin: 0 auto;
            padding: 28px 20px 40px;
        }

        .header-card,
        .report-card,
        .note-card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 20px;
            box-shadow: 0 20px 50px rgba(15, 23, 42, 0.08);
        }

        .header-card {
            padding: 24px;
            margin-bottom: 18px;
        }

        .top-row {
            display: flex;
            justify-content: space-between;
            gap: 18px;
            align-items: flex-start;
            flex-wrap: wrap;
        }

        .eyebrow {
            margin: 0 0 8px;
            text-transform: uppercase;
            letter-spacing: 0.12em;
            color: var(--accent);
            font-size: 12px;
            font-weight: 700;
        }

        h1 {
            margin: 0;
            font-size: 30px;
            line-height: 1.1;
            color: var(--accent-2);
        }

        .subtitle {
            margin: 10px 0 0;
            color: var(--muted);
            font-size: 14px;
            line-height: 1.5;
        }

        .actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 11px 16px;
            border-radius: 12px;
            border: 1px solid transparent;
            text-decoration: none;
            font-weight: 700;
            transition: transform 0.15s ease, box-shadow 0.15s ease, border-color 0.15s ease;
        }

        .btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 8px 18px rgba(15, 23, 42, 0.1);
        }

        .btn:disabled {
            opacity: 0.55;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        .btn-primary {
            background: var(--accent);
            color: white;
        }

        .btn-soft {
            background: var(--soft);
            color: var(--accent-2);
            border-color: #cfe0ff;
        }

        .filter-row {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-top: 18px;
            align-items: flex-end;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
            flex: 1 1 240px;
            min-width: 220px;
        }

        .filter-group label {
            color: var(--muted);
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
        }

        select,
        input[type="date"] {
            width: 100%;
            min-width: 0;
            max-width: 100%;
            padding: 12px 14px;
            border-radius: 12px;
            border: 1px solid var(--border);
            background: #fff;
            color: var(--text);
            font-size: 14px;
        }

        input[type="date"] {
            min-width: 0;
        }

        .meta-line {
            margin-top: 14px;
            color: var(--muted);
            font-size: 14px;
        }

        .summary-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 14px;
            margin-bottom: 18px;
        }

        .status-grid {
            display: grid;
            grid-template-columns: repeat(5, minmax(0, 1fr));
            gap: 14px;
            margin: 0 0 18px;
        }

        .summary-card {
            background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
            border: 1px solid var(--border);
            border-radius: 18px;
            padding: 18px;
            box-shadow: 0 14px 34px rgba(15, 23, 42, 0.06);
        }

        .summary-card .label {
            font-size: 12px;
            font-weight: 700;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 0.07em;
            margin-bottom: 10px;
        }

        .summary-card .value {
            font-size: 28px;
            font-weight: 800;
            color: var(--accent-2);
            line-height: 1.05;
        }

        .summary-card .sub {
            margin-top: 8px;
            color: var(--muted);
            font-size: 13px;
        }

        .summary-card.is-positive .value { color: var(--success); }
        .summary-card.is-warning .value { color: var(--warn); }
        .summary-card.is-danger .value { color: var(--danger); }
        .summary-card.is-muted .value { color: var(--muted); }

        .status-card {
            padding: 16px 18px;
        }

        .status-card .value {
            font-size: 24px;
        }

        .status-card.is-neutral .value { color: var(--accent-2); }

        .report-card {
            overflow: hidden;
        }

        .report-head {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            align-items: flex-start;
            padding: 18px 20px;
            border-bottom: 1px solid var(--border);
            background: linear-gradient(180deg, #fbfdff 0%, #f7faff 100%);
        }

        .report-head h2 {
            margin: 0;
            font-size: 18px;
        }

        .report-head p {
            margin: 6px 0 0;
            color: var(--muted);
            font-size: 13px;
        }

        .table-wrap {
            overflow: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 1220px;
        }

        thead th {
            position: sticky;
            top: 0;
            background: #f8fbff;
            z-index: 1;
            text-align: left;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: var(--muted);
            padding: 14px 16px;
            border-bottom: 1px solid var(--border);
        }

        tbody td {
            padding: 14px 16px;
            border-bottom: 1px solid #edf2f7;
            vertical-align: top;
            font-size: 14px;
        }

        tbody tr:hover {
            background: #fbfdff;
        }

        .op-cell {
            font-weight: 800;
            color: var(--accent-2);
            white-space: nowrap;
        }

        .desc-cell {
            color: var(--text);
            line-height: 1.35;
        }

        .sku-cell,
        .muted {
            color: var(--muted);
            font-size: 13px;
        }

        .tag {
            display: inline-flex;
            align-items: center;
            padding: 5px 9px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 700;
            background: #eef2ff;
            color: #4338ca;
        }

        .tag-success {
            background: #dcfce7;
            color: #166534;
        }

        .tag-warning {
            background: #fef3c7;
            color: #92400e;
        }

        .tag-muted {
            background: #e2e8f0;
            color: #475569;
        }

        .detail-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-top: 8px;
            padding: 6px 10px;
            border: 1px solid #cbd5e1;
            border-radius: 10px;
            background: #fff;
            color: var(--accent-2);
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
            transition: transform 0.15s ease, box-shadow 0.15s ease, border-color 0.15s ease;
        }

        .detail-btn:hover {
            transform: translateY(-1px);
            border-color: #94a3b8;
            box-shadow: 0 6px 14px rgba(15, 23, 42, 0.08);
        }

        .detail-btn:focus-visible {
            outline: 2px solid var(--accent);
            outline-offset: 2px;
        }

        .cell-positive { color: var(--success); font-weight: 800; }
        .cell-success { color: var(--success); font-weight: 800; }
        .cell-warning { color: var(--warn); font-weight: 800; }
        .cell-danger { color: var(--danger); font-weight: 800; }
        .cell-neutral { color: var(--text); font-weight: 700; }
        .cell-muted { color: var(--muted); font-weight: 700; }
        .cell-critical {
            box-shadow: inset 0 0 0 1px currentColor;
            border-radius: 8px;
            background: rgba(255, 255, 255, 0.5);
            font-weight: 900;
        }

        tbody tr.row-realized {
            background: linear-gradient(90deg, rgba(5, 150, 105, 0.07), rgba(5, 150, 105, 0.015) 22%, transparent 48%);
        }

        tbody tr.row-missing {
            background: linear-gradient(90deg, rgba(245, 158, 11, 0.08), rgba(245, 158, 11, 0.02) 22%, transparent 48%);
        }

        tbody tr.row-neutral {
            background: #fff;
        }

        tbody tr.row-critical {
            background: linear-gradient(90deg, rgba(220, 38, 38, 0.08), rgba(220, 38, 38, 0.02) 22%, transparent 48%);
        }

        tbody tr.row-realized td:first-child {
            box-shadow: inset 4px 0 0 var(--success);
        }

        tbody tr.row-missing td:first-child {
            box-shadow: inset 4px 0 0 var(--warn);
        }

        tbody tr.row-neutral td:first-child {
            box-shadow: inset 4px 0 0 #cbd5e1;
        }

        tbody tr.row-critical td:first-child {
            box-shadow: inset 4px 0 0 var(--danger);
        }

        .empty-state {
            padding: 30px 20px;
            color: var(--muted);
            text-align: center;
        }

        .note-card {
            margin-top: 16px;
            padding: 18px 20px;
            color: var(--muted);
            font-size: 14px;
            line-height: 1.55;
        }

        .note-card strong {
            color: var(--text);
        }

        .detail-modal {
            position: fixed;
            inset: 0;
            z-index: 1000;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 18px;
        }

        .detail-modal.is-open {
            display: flex;
        }

        .detail-modal__backdrop {
            position: absolute;
            inset: 0;
            background: rgba(15, 23, 42, 0.55);
            backdrop-filter: blur(3px);
        }

        .detail-modal__panel {
            position: relative;
            z-index: 1;
            width: min(1200px, 100%);
            max-height: min(90vh, 980px);
            overflow: hidden;
            background: var(--card);
            border-radius: 24px;
            border: 1px solid var(--border);
            box-shadow: 0 28px 80px rgba(15, 23, 42, 0.28);
            display: flex;
            flex-direction: column;
        }

        .detail-modal__head {
            padding: 18px 20px 14px;
            border-bottom: 1px solid var(--border);
            background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
        }

        .detail-modal__topline {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            align-items: flex-start;
            flex-wrap: wrap;
        }

        .detail-modal__title {
            margin: 0;
            font-size: 22px;
            line-height: 1.15;
            color: var(--accent-2);
        }

        .detail-modal__subtitle {
            margin: 6px 0 0;
            color: var(--muted);
            font-size: 13px;
        }

        .detail-modal__close {
            border: 1px solid #cbd5e1;
            background: #fff;
            color: var(--accent-2);
            border-radius: 12px;
            width: 38px;
            height: 38px;
            cursor: pointer;
            font-size: 18px;
            line-height: 1;
        }

        .detail-modal__content {
            padding: 14px 16px 16px;
            overflow: auto;
        }

        .detail-kpis {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 10px;
            margin-bottom: 12px;
        }

        .detail-kpi {
            background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 10px 12px;
        }

        .detail-kpi .label {
            font-size: 10px;
            font-weight: 700;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 0.07em;
            margin-bottom: 6px;
        }

        .detail-kpi .value {
            font-size: 18px;
            font-weight: 800;
            color: var(--accent-2);
        }

        .detail-section {
            margin-top: 10px;
            border: 1px solid var(--border);
            border-radius: 14px;
            overflow: hidden;
            background: #fff;
        }

        .detail-section__head {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
            padding: 10px 14px;
            background: #f8fbff;
            border-bottom: 1px solid var(--border);
        }

        .detail-section__head h3 {
            margin: 0;
            font-size: 14px;
            color: var(--accent-2);
        }

        .detail-section__head p {
            margin: 2px 0 0;
            color: var(--muted);
            font-size: 12px;
        }

        .detail-section__body {
            overflow: auto;
        }

        .detail-table {
            min-width: 980px;
        }

        .detail-modal .detail-table thead th {
            padding: 10px 12px;
            font-size: 11px;
        }

        .detail-modal .detail-table tbody td {
            padding: 9px 12px;
            font-size: 13px;
        }

        .detail-section--summary .detail-table {
            min-width: 0;
            width: 100%;
        }

        .detail-section--summary .detail-table thead th,
        .detail-section--summary .detail-table tbody td {
            padding: 8px 10px;
        }

        .detail-table__meta {
            margin-top: 2px;
            font-size: 11px;
            color: var(--muted);
        }

        .detail-duration-highlight {
            color: #b91c1c;
            font-weight: 700;
            background: rgba(239, 68, 68, 0.05);
        }

        .detail-duration-highlight .detail-table__meta {
            color: #b91c1c;
        }

        .badge-main {
            background: #dcfce7;
            color: #166534;
        }

        .badge-secondary {
            background: #e2e8f0;
            color: #475569;
        }

        .badge-summary {
            background: #dbeafe;
            color: #1d4ed8;
        }

        .detail-toggle {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            justify-content: flex-end;
            text-align: right;
        }

        .detail-toggle__control {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 11px;
            font-weight: 700;
            color: var(--accent-2);
            cursor: pointer;
            user-select: none;
        }

        .detail-toggle__control input {
            width: 16px;
            height: 16px;
            accent-color: var(--accent);
        }

        .detail-toggle__hint {
            font-size: 10px;
            color: var(--muted);
            max-width: 320px;
        }

        .detail-group-total {
            background: #eef2ff;
            font-weight: 700;
        }

        .detail-group-total td {
            border-top: 2px solid #c7d2fe;
        }

        .group-row--top {
            background: #eff6ff;
        }

        .group-row--main {
            background: #f0fdf4;
        }

        .group-row--null {
            background: #fafafa;
        }

        .detail-note {
            margin-top: 16px;
            padding: 14px 16px;
            border-radius: 16px;
            background: #f8fbff;
            border: 1px dashed #cbd5e1;
            color: var(--muted);
            font-size: 13px;
            line-height: 1.5;
        }

        @media (max-width: 1200px) {
            .summary-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .status-grid {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }

            .detail-kpis {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 720px) {
            .page {
                padding: 14px 12px 30px;
            }

            h1 {
                font-size: 24px;
            }

            .summary-grid {
                grid-template-columns: 1fr;
            }

            .status-grid {
                grid-template-columns: 1fr;
            }

            .detail-kpis {
                grid-template-columns: 1fr;
            }

            select,
            input[type="date"] {
                min-width: 100%;
            }

            .filter-group {
                flex: 1 1 100%;
            }
        }
    </style>
</head>
<body>
<div class="page">
    <div class="header-card">
        <div class="top-row">
            <div>
                <div class="eyebrow">Relatório de setup</div>
                <h1>Previsto x realizado</h1>
                <p class="subtitle">
                    Comparação da programação com os apontamentos de parada para
                    <strong><?= h($setupRuleLabel) ?></strong>.
                </p>
            </div>
            <div class="actions">
                <a class="btn btn-soft" href="gantt.php">Voltar ao Gantt</a>
                <button class="btn btn-soft" type="button" disabled title="Preparado para a próxima etapa de exportação">Exportar CSV</button>
            </div>
        </div>

        <form class="filter-row" method="get">
            <div class="filter-group">
                <label for="linha">Linha</label>
                <select id="linha" name="linha" onchange="this.form.submit()">
                    <option value="">Todas</option>
                    <?php foreach ($linhasDisponiveis as $linCodigo): ?>
                        <option value="<?= h((string) $linCodigo) ?>" <?= (string) $linCodigo === $selectedLine ? 'selected' : '' ?>>
                            <?= h(lineLabel((string) $linCodigo)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-group">
                <label for="programacao_id">Programação</label>
                <select id="programacao_id" name="programacao_id" onchange="this.form.submit()">
                        <?php foreach ($filteredProgramacoes as $programacao): ?>
                            <?php
                                $progId = (int) $programacao['prg_id'];
                                $progNumero = (string) ($programacao['prg_numero_op'] ?? 'S/OP');
                                $linha = lineLabel((string) ($programacao['lin_codigo'] ?? ''));
                                $inicio = formatDateTimeDisplay($programacao['data_inicio'] ?? null);
                                $programacaoCriadaEm = formatDateTimeDisplay($programacao['programacao_criada_em'] ?? null);
                                $eficiencia = number_format((float) ($programacao['prg_eficiencia'] ?? 0), 0, ',', '.');
                            ?>
                            <option value="<?= $progId ?>" <?= $progId === $selectedProgramId ? 'selected' : '' ?>>
                                <?= h(sprintf('%s | Início: %s | Data da Programação: %s | Eficiência: %s%%', $linha, $inicio, $programacaoCriadaEm, $eficiencia)) ?>
                            </option>
                        <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-group">
                <label for="data_inicio">Período inicial</label>
                <input id="data_inicio" name="data_inicio" type="date" value="<?= h($selectedPeriodStartInput) ?>">
            </div>
            <div class="filter-group">
                <label for="data_fim">Período final</label>
                <input id="data_fim" name="data_fim" type="date" value="<?= h($selectedPeriodEndInput) ?>">
            </div>
            <div class="filter-group">
                <label for="status">Leitura</label>
                <select id="status" name="status" onchange="this.form.submit()">
                    <option value="all" <?= $selectedStatus === 'all' ? 'selected' : '' ?>>Todas as OPs</option>
                    <option value="realizado" <?= $selectedStatus === 'realizado' ? 'selected' : '' ?>>Somente com setup realizado</option>
                    <option value="positivo" <?= $selectedStatus === 'positivo' ? 'selected' : '' ?>>Somente desvio positivo</option>
                    <option value="negativo" <?= $selectedStatus === 'negativo' ? 'selected' : '' ?>>Somente desvio negativo</option>
                    <option value="sem_evento" <?= $selectedStatus === 'sem_evento' ? 'selected' : '' ?>>Somente sem evento</option>
                </select>
            </div>
            <button class="btn btn-primary" type="submit">Aplicar filtros</button>
        </form>

        <div class="meta-line">
            <?= h($programLabel) ?>
            <?php if ($programStart && $programEnd): ?>
                | Período base: <strong><?= h(formatDateDisplay($programStart)) ?></strong>
                até <strong><?= h(formatDateDisplay($programEnd)) ?></strong>
            <?php endif; ?>
            <?php if ($selectedLine !== '' || $selectedPeriodStartInput !== '' || $selectedPeriodEndInput !== ''): ?>
                <br>
                Filtros ativos:
                <?php if ($selectedLine !== ''): ?>
                    linha <strong><?= h(lineLabel($selectedLine)) ?></strong>
                <?php endif; ?>
                <?php if ($selectedPeriodStartInput !== '' || $selectedPeriodEndInput !== ''): ?>
                    <?php if ($selectedLine !== ''): ?> | <?php endif; ?>
                    período <strong><?= h($selectedPeriodStartInput !== '' ? formatDateDisplay($selectedPeriodStartInput, 'início livre') : 'início livre') ?></strong>
                    até <strong><?= h($selectedPeriodEndInput !== '' ? formatDateDisplay($selectedPeriodEndInput, 'fim livre') : 'fim livre') ?></strong>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <div class="summary-grid">
        <div class="summary-card">
            <div class="label">OPs analisadas</div>
            <div class="value"><?= (int) $summary['ops'] ?></div>
            <div class="sub">Linhas de produção com OP resolvida</div>
        </div>
        <div class="summary-card is-positive">
            <div class="label">OPs c/ setup real</div>
            <div class="value"><?= (int) $summary['ops_com_setup_realizado'] ?></div>
            <div class="sub">OPs com pelo menos um evento alvo</div>
        </div>
        <div class="summary-card">
            <div class="label">Eventos de setup</div>
            <div class="value"><?= (int) $summary['setup_eventos_total'] ?></div>
            <div class="sub">Soma dos eventos classificados</div>
        </div>
        <div class="summary-card">
            <div class="label">Setup previsto</div>
            <div class="value"><?= h(formatMinutes($summary['setup_previsto'])) ?></div>
            <div class="sub">Soma do setup planejado</div>
        </div>
        <div class="summary-card is-warning">
            <div class="label">Setup realizado</div>
            <div class="value"><?= h(formatMinutes($summary['setup_realizado'])) ?></div>
            <div class="sub">Somente paradas alvo</div>
        </div>
        <div class="summary-card is-positive">
            <div class="label">Maior desvio positivo</div>
            <div class="value"><?= h(formatSignedMinutes((float) $summary['maior_desvio_positivo'])) ?></div>
            <div class="sub"><?php if (!empty($summary['maior_desvio_positivo_op'])): ?>OP <?= h((string) $summary['maior_desvio_positivo_op']) ?><?php else: ?>Sem caso positivo relevante<?php endif; ?></div>
        </div>
        <div class="summary-card is-danger">
            <div class="label">Maior desvio negativo</div>
            <div class="value"><?= h(formatSignedMinutes((float) $summary['maior_desvio_negativo'])) ?></div>
            <div class="sub"><?php if (!empty($summary['maior_desvio_negativo_op'])): ?>OP <?= h((string) $summary['maior_desvio_negativo_op']) ?><?php else: ?>Sem caso negativo relevante<?php endif; ?></div>
        </div>
        <div class="summary-card <?= $setupDiff > 0 ? 'is-danger' : 'is-positive' ?>">
            <div class="label">Desvio do setup</div>
            <div class="value"><?= h(formatSignedMinutes($setupDiff)) ?></div>
            <div class="sub"><?= h(number_format($setupPct, 1, ',', '.')) ?>% do previsto</div>
        </div>
        <div class="summary-card">
            <div class="label">Produção prev. x real.</div>
            <div class="value"><?= h(formatQtyRounded($summary['producao_prevista'])) ?> / <?= h(formatQtyRounded($summary['producao_realizada'])) ?></div>
            <div class="sub">Desvio: <?= h(sprintf('%s%s', $prodDiff >= 0 ? '+' : '-', formatQtyRounded(abs($prodDiff)))) ?> | <?= h(number_format($prodPct, 1, ',', '.')) ?>%</div>
        </div>
    </div>

    <div class="status-grid">
        <div class="summary-card status-card is-positive">
            <div class="label">No prazo</div>
            <div class="value"><?= (int) $operationalSummary['no_prazo'] ?></div>
            <div class="sub">Desvio de atÃ© 1 minuto</div>
        </div>
        <div class="summary-card status-card is-danger">
            <div class="label">Acima do previsto</div>
            <div class="value"><?= (int) $operationalSummary['acima'] ?></div>
            <div class="sub">Realizado maior que o planejado</div>
        </div>
        <div class="summary-card status-card is-warning">
            <div class="label">Abaixo do previsto</div>
            <div class="value"><?= (int) $operationalSummary['abaixo'] ?></div>
            <div class="sub">Realizado menor que o planejado</div>
        </div>
        <div class="summary-card status-card is-muted">
            <div class="label">Sem evento</div>
            <div class="value"><?= (int) $operationalSummary['sem_evento'] ?></div>
            <div class="sub">Setup previsto sem apontamento alvo</div>
        </div>
        <div class="summary-card status-card is-neutral">
            <div class="label">Sem setup previsto</div>
            <div class="value"><?= (int) $operationalSummary['sem_setup'] ?></div>
            <div class="sub">Fora do recorte de setup</div>
        </div>
    </div>

    <div class="report-card">
        <div class="report-head">
            <div>
                <h2>Detalhe por OP</h2>
                <p>Ordenado pela sequência da programação/histórico. O setup previsto vem de <code>sch_linhas.sch_duracao_minutos</code> e o realizado de <code>setup_duracao_minutos</code> para as paradas alvo.</p>
            </div>
            <div class="tag"><?= h($setupRuleLabel) ?></div>
        </div>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>OP</th>
                        <th>Produto / descrição</th>
                        <th>SKU</th>
                        <th>Setup previsto</th>
                        <th>Setup realizado</th>
                        <th>Diferença</th>
                        <th>Prod. previsto</th>
                        <th>Prod. realizado</th>
                        <th>Diferença</th>
                        <th>Tempo previsto</th>
                        <th>Tempo realizado</th>
                        <th>Eventos / situação</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($reportRows)): ?>
                        <?php foreach ($reportRows as $row): ?>
                            <?php
                                $setupPlan = (float) $row['setup_previsto_min'];
                                $setupReal = (float) $row['setup_realizado_min'];
                                $setupRowDiff = $setupReal - $setupPlan;
                                $prodPlan = (float) $row['producao_prevista'];
                                $prodReal = (float) $row['producao_realizada'];
                                $prodRowDiff = $prodReal - $prodPlan;
                                $tempoPrevisto = (float) ($row['tempo_previsto_min'] ?? 0);
                                $tempoRealizado = (float) ($row['tempo_realizado_min'] ?? 0);
                                $isFirstSequenceOp = !empty($row['is_first_sequence_op']);
                                // Regra operacional: a primeira OP da sequência não herda setup anterior.
                                $setupRealDisplay = $isFirstSequenceOp ? 0.0 : $setupReal;
                                $setupDiffDisplay = $isFirstSequenceOp ? 0.0 : $setupRowDiff;
                                $setupClass = $setupRowDiff > 0.01 ? 'cell-danger' : ($setupRowDiff < -0.01 ? 'cell-positive' : 'cell-neutral');
                                if (!empty($row['is_critical'])) {
                                    $setupClass .= ' cell-critical';
                                }
                                $prodClass = $prodRowDiff > 0.01 ? 'cell-positive' : ($prodRowDiff < -0.01 ? 'cell-danger' : 'cell-neutral');
                                $setupStatus = getRowSetupStatus($row);
                                $setupRealClass = ((int) $row['setup_realizado_eventos'] > 0)
                                    ? 'cell-success'
                                    : ($setupPlan > 0.01 ? 'cell-warning' : 'cell-muted');
                                $setupRealClassDisplay = $isFirstSequenceOp ? 'cell-muted' : $setupRealClass;
                                $setupClassDisplay = $isFirstSequenceOp ? 'cell-neutral' : $setupClass;
                                $desc = trim((string) ($row['descricao'] ?? ''));
                                if ($desc === '') {
                                    $desc = 'Sem descrição';
                                }
                            ?>
                            <tr class="<?= h((string) ($row['row_class'] ?? $setupStatus['row_class'])) ?>">
                                <td class="op-cell">OP <?= h((string) $row['op']) ?></td>
                                <td class="desc-cell"><?= h($desc) ?></td>
                                <td class="sku-cell"><?= h((string) ($row['sku'] ?? '')) ?></td>
                                <td><?= h(formatMinutes($setupPlan)) ?></td>
                                <td class="<?= h($setupRealClassDisplay) ?>"><?= h(formatMinutes($setupRealDisplay)) ?></td>
                                <td class="<?= $setupClassDisplay ?>"><?= h(formatSignedMinutes($setupDiffDisplay)) ?></td>
                                <td><?= h(formatQtyRounded($prodPlan)) ?></td>
                                <td><?= h(formatQtyRounded($prodReal)) ?></td>
                                <td class="<?= $prodClass ?>"><?= h(sprintf('%s%s', $prodRowDiff >= 0 ? '+' : '-', formatQtyRounded(abs($prodRowDiff)))) ?></td>
                                <td><?= h(formatDurationClock(max(0.0, $tempoPrevisto))) ?></td>
                                <td><?= h(formatDurationClock(max(0.0, $tempoRealizado))) ?></td>
                                <td>
                                    <span class="tag <?= h((string) ($row['tag_class'] ?? $setupStatus['tag_class'])) ?>" title="<?= h((string) ($row['tag_title'] ?? $setupStatus['title'])) ?>">
                                        <?= h((string) ($row['tag_label'] ?? $setupStatus['label'])) ?>
                                    </span>
                                    <button
                                        class="detail-btn"
                                        type="button"
                                        data-op="<?= h((string) $row['op']) ?>"
                                        data-period-start="<?= h($reportPeriodStart) ?>"
                                        data-period-end="<?= h($reportPeriodEnd) ?>"
                                        data-setup-plan-min="<?= h((string) $setupPlan) ?>"
                                        data-setup-plan="<?= h(formatMinutes($setupPlan)) ?>"
                                        data-setup-real="<?= h(formatMinutes($setupReal)) ?>"
                                        title="Abrir detalhe complementar da OP"
                                    >
                                        Ver detalhe
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="12">
                                <div class="empty-state">
                                    Nenhuma OP encontrada para a programação selecionada.
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="note-card">
        <strong>Nota operacional:</strong>
        nesta versão o relatório já lê o setup realizado de <code>setup_duracao_minutos</code>
        e diferencia claramente OPs com evento alvo, OPs previstas sem evento e OPs sem setup previsto.
        Os filtros de linha e período só refinam a leitura da tela; eles não alteram a regra de cálculo validada.
    </div>
    <div class="note-card">
        <strong>Visão complementar:</strong>
        o cálculo principal continua restrito a <code>TROCA DE KIT</code> e <code>TROCA DE LIQUIDO</code>
        na tabela <code>realizado_2026_excel</code>. Para investigação, o sandbox preserva o evento bruto do CODI
        em <code>realizado_2026_eventos</code>, incluindo outras paradas nomeadas e seus intervalos originais.
        <br><br>
        <strong>Diagnóstico atual do sandbox:</strong>
        há <?= (int) $realizadoDiagnostics['rows'] ?> registros de realizado ligados às OPs desta programação,
        mas apenas <?= (int) $realizadoDiagnostics['classificados'] ?> estão classificados como setup alvo
        e <?= (int) $realizadoDiagnostics['ops_classificadas'] ?> OPs têm essa classificação.
        <?php if ($realizadoDiagnostics['classificados'] === 0): ?>
            No estado atual, o setup realizado permanece em 0 porque a coluna
            <code>parada_nomeParada</code> ainda não está populada com os nomes alvo no import.
        <?php endif; ?>
    </div>

    <div id="op-detail-modal" class="detail-modal" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="op-detail-title">
        <div class="detail-modal__backdrop" data-close-detail="1"></div>
        <div class="detail-modal__panel">
            <div class="detail-modal__head">
                <div class="detail-modal__topline">
                    <div>
                        <h2 id="op-detail-title" class="detail-modal__title">Detalhe complementar da OP</h2>
                        <p id="op-detail-subtitle" class="detail-modal__subtitle">
                            Cálculo principal preservado: somente <strong><?= h($setupRuleLabel) ?></strong>.
                        </p>
                    </div>
                    <button class="detail-modal__close" type="button" data-close-detail="1" aria-label="Fechar detalhe">×</button>
                </div>
            </div>
            <div class="detail-modal__content">
                <div id="op-detail-kpis" class="detail-kpis">
                    <div class="detail-kpi">
                        <div class="label">Carregando</div>
                        <div class="value">...</div>
                    </div>
                </div>

                <div class="detail-section">
                    <div class="detail-section__head">
                        <div>
                            <h3>Setup principal</h3>
                            <p>Somente registros com <code>TROCA DE KIT</code> ou <code>TROCA DE LIQUIDO</code>.</p>
                        </div>
                        <span class="tag badge-main">Regra principal</span>
                    </div>
                    <div class="detail-section__body">
                        <table class="detail-table">
                            <thead>
                                <tr>
                                    <th>Data</th>
                                    <th>Início</th>
                                    <th>Fim</th>
                                    <th>Parada</th>
                                    <th>Referência</th>
                                    <th>Duração</th>
                                </tr>
                            </thead>
                            <tbody id="detail-principal-body">
                                <tr>
                                    <td colspan="6" class="empty-state">Selecione uma OP para ver o detalhe.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="detail-section">
                    <div class="detail-section__head">
                        <div>
                            <h3>Paradas complementares</h3>
                            <p>Eventos do mesmo contexto que ajudam a explicar o desvio, sem entrar no cálculo principal.</p>
                        </div>
                        <span class="tag badge-secondary">Contexto complementar</span>
                    </div>
                    <div class="detail-section__body">
                        <table class="detail-table">
                            <thead>
                                <tr>
                                    <th>Data</th>
                                    <th>Início</th>
                                    <th>Fim</th>
                                    <th>Parada</th>
                                    <th>Duração</th>
                                </tr>
                            </thead>
                            <tbody id="detail-apoio-body">
                                <tr>
                                    <td colspan="5" class="empty-state">Selecione uma OP para ver o detalhe.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="detail-section detail-section--summary">
                    <div class="detail-section__head">
                        <div>
                            <h3>Resumo por parada</h3>
                            <p>Agrupado por <code>parada_nomeParada</code> e ordenado pelo maior impacto em minutos.</p>
                        </div>
                        <div class="detail-toggle">
                            <label class="detail-toggle__control" for="detail-main-only-toggle">
                                <input type="checkbox" id="detail-main-only-toggle" checked>
                                <span>Mostrar somente a regra principal</span>
                            </label>
                            <div class="detail-toggle__hint">
                                Desmarque para exibir as paradas complementares disponíveis para a OP/período.
                            </div>
                        </div>
                    </div>
                    <div class="detail-section__body">
                        <table class="detail-table">
                            <thead>
                                <tr>
                                    <th>Parada</th>
                                    <th>Duração total</th>
                                </tr>
                            </thead>
                            <tbody id="detail-grouped-body">
                                <tr>
                                    <td colspan="2" class="empty-state">Selecione uma OP para ver o resumo.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div id="op-detail-note" class="detail-note">
                    O detalhe complementar será carregado ao abrir uma OP.
                </div>
            </div>
        </div>
    </div>
</div>
<script>
(function () {
    const modal = document.getElementById('op-detail-modal');
    if (!modal) {
        return;
    }

    const titleEl = document.getElementById('op-detail-title');
    const subtitleEl = document.getElementById('op-detail-subtitle');
    const kpisEl = document.getElementById('op-detail-kpis');
    const groupedBody = document.getElementById('detail-grouped-body');
    const principalBody = document.getElementById('detail-principal-body');
    const apoioBody = document.getElementById('detail-apoio-body');
    const noteEl = document.getElementById('op-detail-note');
    const mainOnlyToggle = document.getElementById('detail-main-only-toggle');
    const closeTargets = modal.querySelectorAll('[data-close-detail]');
    const detailButtons = document.querySelectorAll('.detail-btn[data-op]');
    const detailState = {
        mainOnly: true,
        data: null
    };

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function isVisibleComplementaryParada(value) {
        const label = String(value ?? '').trim();
        if (!label) {
            return false;
        }

        return label !== 'Sem classificaÃ§Ã£o';
    }

    function isVisibleComplementaryParadaVisible(value) {
        const label = String(value ?? '').trim();
        if (!label) {
            return false;
        }

        const upperLabel = label.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toUpperCase();
        if (upperLabel === 'DESCONEXAO') {
            return false;
        }

        const normalized = isVisibleComplementaryParada(value);
        return normalized;
    }

    function formatMinutesLabel(minutes) {
        const rounded = Math.round(Number(minutes) || 0);
        const sign = rounded < 0 ? '-' : '';
        const abs = Math.abs(rounded);
        const hours = Math.floor(abs / 60);
        const mins = abs % 60;
        return `${sign}${String(hours).padStart(2, '0')}:${String(mins).padStart(2, '0')}`;
    }

    function parseDateParts(value) {
        const text = String(value ?? '').trim().replace('T', ' ');
        if (!text) {
            return null;
        }

        const match = text.match(/^(\d{4})-(\d{2})-(\d{2})(?:\s+(\d{2}):(\d{2})(?::\d{2})?)?$/);
        if (!match) {
            return null;
        }

        return {
            year: match[1],
            month: match[2],
            day: match[3],
            hour: match[4] || '',
            minute: match[5] || ''
        };
    }

    function formatDateDisplay(value) {
        const parts = parseDateParts(value);
        if (!parts) {
            return String(value ?? '--') || '--';
        }

        return `${parts.day}/${parts.month}/${parts.year}`;
    }

    function formatTimeDisplay(value) {
        const parts = parseDateParts(value);
        if (!parts || !parts.hour || !parts.minute) {
            return '--';
        }

        return `${parts.hour}:${parts.minute}`;
    }

    function formatDateTimeDisplay(value) {
        const parts = parseDateParts(value);
        if (!parts) {
            return String(value ?? '--') || '--';
        }

        if (!parts.hour || !parts.minute) {
            return `${parts.day}/${parts.month}/${parts.year}`;
        }

        return `${parts.day}/${parts.month}/${parts.year} ${parts.hour}:${parts.minute}`;
    }

    function rowHtml(row, badgeClass, showReference = true) {
        const dataEvento = formatDateTimeDisplay(row.data_evento || '--');
        const inicio = formatDateTimeDisplay(row.inicio_evento || '--');
        const fim = formatDateTimeDisplay(row.fim_evento || '--');
        const duration = formatMinutesLabel(row.duracao_evento_minutos ?? row.setup_duracao_minutos ?? 0);
        const durationClass = row.tipo_bloco === 'setup_principal' ? 'detail-duration-highlight' : '';
        const rowClass = row.tipo_bloco === 'setup_principal' ? 'row-realized' : 'row-neutral';

        return `
            <tr class="${rowClass}">
                <td>${escapeHtml(dataEvento)}</td>
                <td>${escapeHtml(inicio)}</td>
                <td>${escapeHtml(fim)}</td>
                <td>
                    <span class="tag ${badgeClass}">${escapeHtml(row.parada_nomeParada || 'Sem classificação')}</span>
                </td>
                ${showReference ? `
                <td>
                    <span class="tag ${row.tipo_bloco === 'setup_principal' ? 'badge-main' : 'badge-secondary'}">${escapeHtml(row.setup_referencia || (row.tipo_bloco === 'setup_principal' ? '00:00' : 'Contexto complementar'))}</span>
                </td>` : ''}
                <td class="${durationClass}">${escapeHtml(duration)}</td>
            </tr>
        `;
    }

    function matchesMainOnly(row, mainOnly) {
        if (!mainOnly) {
            return true;
        }

        return row.tipo_bloco === 'setup_principal' || row.is_principal;
    }

    function filterVisibleComplementaryRows(rows) {
        return rows.filter((row) => {
            if (row && row.tipo_bloco === 'setup_principal') {
                return true;
            }

            return isVisibleComplementaryParadaVisible(row && row.parada_nomeParada);
        });
    }

    function formatShareLabel(minutes, totalMinutes) {
        if (totalMinutes <= 0) {
            return '0,0';
        }

        return ((minutes / totalMinutes) * 100).toFixed(1).replace('.', ',');
    }

    function renderRows(target, rows, emptyText, badgeClass, mainOnly, showReference = true) {
        const filteredRows = filterVisibleComplementaryRows(rows).filter((row) => matchesMainOnly(row, mainOnly));

        if (!filteredRows.length) {
            target.innerHTML = `<tr><td colspan="${showReference ? 6 : 5}" class="empty-state">${escapeHtml(emptyText)}</td></tr>`;
            return;
        }

        target.innerHTML = filteredRows.map((row) => rowHtml(row, badgeClass, showReference)).join('');
    }

    function renderGroupedRows(target, rows, emptyText, totalMinutes, mainOnly) {
        const filteredRows = filterVisibleComplementaryRows(rows).filter((row) => matchesMainOnly(row, mainOnly));

        if (!filteredRows.length) {
            target.innerHTML = `<tr><td colspan="2" class="empty-state">${escapeHtml(emptyText)}</td></tr>`;
            return;
        }

        const visibleTotalMinutes = filteredRows.reduce((sum, row) => sum + Number(row.duracao_total_minutos || 0), 0);

        const summaryRows = filteredRows.map((row, index) => {
            const minutes = Number(row.duracao_total_minutos || 0);
            const rowClass = [
                index === 0 ? 'group-row--top' : '',
                row.is_principal ? 'group-row--main' : '',
                row.is_null ? 'group-row--null' : ''
            ].filter(Boolean).join(' ');
            const badgeClass = row.is_principal ? 'badge-main' : 'badge-secondary';
            const label = row.parada_nomeParada || 'Sem classificação';
            const duration = formatMinutesLabel(minutes);

            return `
                <tr class="${rowClass}">
                    <td>
                        <span class="tag ${badgeClass}">${escapeHtml(label)}</span>
                    </td>
            <td>${escapeHtml(duration)}</td>
        </tr>
            `;
        }).join('');

        target.innerHTML = summaryRows;
    }

    function getGroupedLabel(rows) {
        return rows
            .slice(0, 4)
            .map((row) => `${row.parada_nomeParada || 'Sem classificação'} (${Number(row.eventos_count || 0)})`)
            .join(', ');
    }

    function setMainOnly(checked) {
        detailState.mainOnly = Boolean(checked);
        if (mainOnlyToggle) {
            mainOnlyToggle.checked = detailState.mainOnly;
        }
        if (detailState.data) {
            renderDetailViews(detailState.data);
        }
    }

    function renderDetailViews(data) {
        const summary = data.summary || {};
        const groupedRows = data.paradas_agrupadas || [];
        const mainOnly = detailState.mainOnly;
        const filteredGroupedRows = filterVisibleComplementaryRows(groupedRows).filter((row) => matchesMainOnly(row, mainOnly));
        const principalRows = data.principal || [];
        const apoioRows = data.apoio || [];
        const namedCounts = data.support_named_paradas || {};
        const visibleTotalMinutes = filteredGroupedRows.reduce((sum, row) => sum + Number(row.duracao_total_minutos || 0), 0);
        const periodLabel = `${formatDateDisplay(data.period_start)} a ${formatDateDisplay(data.period_end)}`;

        titleEl.textContent = `OP ${data.op} - detalhe complementar`;
        subtitleEl.innerHTML = `Cálculo principal preservado: somente <strong><?= h($setupRuleLabel) ?></strong>. Período: <strong>${escapeHtml(periodLabel)}</strong>.`;
        kpisEl.innerHTML = `
            <div class="detail-kpi">
                <div class="label">Registros</div>
                <div class="value">${escapeHtml(summary.rows_total || 0)}</div>
            </div>
            <div class="detail-kpi">
                <div class="label">Setup principal</div>
                <div class="value">${escapeHtml(summary.principal_rows || 0)} linhas</div>
            </div>
            <div class="detail-kpi">
                <div class="label">Paradas complementares</div>
                <div class="value">${escapeHtml(summary.apoio_rows || 0)} linhas</div>
            </div>
            <div class="detail-kpi">
                <div class="label">Minutos principais</div>
                <div class="value">${escapeHtml(formatMinutesLabel(summary.principal_minutes || 0))}</div>
            </div>
        `;

        renderRows(principalBody, principalRows, 'Nenhum setup principal encontrado neste período.', 'badge-main', mainOnly);
        renderRows(
            apoioBody,
            mainOnly ? [] : apoioRows,
            mainOnly
                ? 'Controle ativo: desmarque para exibir as paradas complementares.'
                : 'Nenhuma parada complementar encontrada neste período.',
            'badge-secondary',
            mainOnly,
            false
        );
        renderGroupedRows(
            groupedBody,
            groupedRows,
            mainOnly
                ? 'Somente a regra principal está visível neste momento.'
                : 'Nenhuma parada encontrada para este período.',
            visibleTotalMinutes,
            mainOnly
        );

        const groupedLabel = getGroupedLabel(filteredGroupedRows);

        if ((summary.raw_rows_total || 0) === 0 && data.detail_source === 'realizado_2026_eventos') {
            noteEl.textContent = 'Este período/OP ainda não tem eventos brutos no sandbox. Sem bruto não há como exibir paradas complementares reais para este caso.';
        } else if (mainOnly) {
            noteEl.textContent = filteredGroupedRows.length > 0
                ? `Controle ativo: mostrando somente a regra principal. Destaques: ${groupedLabel}. Desmarque para liberar as paradas complementares disponíveis no banco.`
                : 'Controle ativo: somente a regra principal está visível neste momento.';
        } else if (filteredGroupedRows.length > 0) {
            noteEl.textContent = `Controle desmarcado: exibindo regra principal e paradas complementares. Destaques: ${groupedLabel}.`;
        } else if (Object.keys(namedCounts).length > 0) {
            noteEl.textContent = 'O detalhe bruto foi carregado, mas não há paradas complementares nomeadas para este caso.';
        } else if (data.detail_source === 'realizado_2026_eventos') {
            noteEl.textContent = 'O detalhe bruto foi carregado, mas este período/OP não trouxe paradas complementares nomeadas além do setup principal.';
        } else {
            noteEl.textContent = 'A visão complementar caiu no agregado principal porque a tabela de detalhe bruto ainda não está disponível neste sandbox.';
        }
    }

    function openModal() {
        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
    }

    function closeModal() {
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
    }

    async function loadDetail(button) {
        const op = button.getAttribute('data-op') || '';
        const periodStart = button.getAttribute('data-period-start') || '';
        const periodEnd = button.getAttribute('data-period-end') || '';
        const setupPlanMin = button.getAttribute('data-setup-plan-min') || '';
        const setupPlan = button.getAttribute('data-setup-plan') || '';
        const setupReal = button.getAttribute('data-setup-real') || '';

        titleEl.textContent = `OP ${op} - detalhe complementar`;
        subtitleEl.innerHTML = `Cálculo principal preservado: somente <strong><?= h($setupRuleLabel) ?></strong>. Período: <strong>${escapeHtml(formatDateDisplay(periodStart))} a ${escapeHtml(formatDateDisplay(periodEnd))}</strong>.`;
        detailState.data = null;
        if (mainOnlyToggle) {
            mainOnlyToggle.checked = true;
        }
        detailState.mainOnly = true;
        kpisEl.innerHTML = `
            <div class="detail-kpi">
                <div class="label">OP</div>
                <div class="value">${escapeHtml(op)}</div>
            </div>
            <div class="detail-kpi">
                <div class="label">Setup previsto</div>
                <div class="value">${escapeHtml(setupPlan)}</div>
            </div>
            <div class="detail-kpi">
                <div class="label">Setup realizado</div>
                <div class="value">${escapeHtml(setupReal)}</div>
            </div>
            <div class="detail-kpi">
                <div class="label">Período</div>
                <div class="value">${escapeHtml(formatDateDisplay(periodStart))} a ${escapeHtml(formatDateDisplay(periodEnd))}</div>
            </div>
        `;
        principalBody.innerHTML = '<tr><td colspan="6" class="empty-state">Carregando detalhe da OP...</td></tr>';
        apoioBody.innerHTML = '<tr><td colspan="5" class="empty-state">Carregando detalhe da OP...</td></tr>';
        groupedBody.innerHTML = '<tr><td colspan="2" class="empty-state">Carregando resumo por parada...</td></tr>';
        noteEl.textContent = 'Carregando dados do sandbox...';
        setMainOnly(true);

        const url = new URL(window.location.href);
        url.searchParams.set('action', 'op_detail');
        url.searchParams.set('op', op);
        url.searchParams.set('period_start', periodStart);
        url.searchParams.set('period_end', periodEnd);
        url.searchParams.set('setup_plan_min', setupPlanMin);

        try {
            const response = await fetch(url.toString(), { headers: { 'Accept': 'application/json' } });
            const data = await response.json();

            if (!response.ok || !data.success) {
                throw new Error(data.message || 'Não foi possível carregar o detalhe.');
            }

            detailState.data = data;
            renderDetailViews(data);
            openModal();
        } catch (error) {
            principalBody.innerHTML = `<tr><td colspan="6" class="empty-state">${escapeHtml(error.message || 'Erro ao carregar')}</td></tr>`;
            apoioBody.innerHTML = '<tr><td colspan="5" class="empty-state">Nenhum dado disponível.</td></tr>';
            noteEl.textContent = 'Falha ao carregar o detalhe complementar.';
            openModal();
        }
    }

    detailButtons.forEach((button) => {
        button.addEventListener('click', () => loadDetail(button));
    });

    if (mainOnlyToggle) {
        mainOnlyToggle.addEventListener('change', () => {
            setMainOnly(mainOnlyToggle.checked);
        });
    }

    closeTargets.forEach((el) => {
        el.addEventListener('click', closeModal);
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && modal.classList.contains('is-open')) {
            closeModal();
        }
    });
})();
</script>
</body>
</html>
