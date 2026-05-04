<?php

declare(strict_types=1);

require __DIR__ . '/src/bootstrap.php';

use App\Auth\Auth;
use App\Data\DatabaseData;
use App\Database\Connection;
use App\Services\WorkCalendar;
use App\Support\DateTimeHelper;

if (!headers_sent()) {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Cache-Control: post-check=0, pre-check=0', false);
    header('Pragma: no-cache');
    header('Expires: 0');
}

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

function formatPercentDisplay(float $value): string
{
    $formatted = number_format($value, 2, ',', '.');
    $formatted = rtrim(rtrim($formatted, '0'), ',');
    return $formatted . '%';
}

function normalizePerformanceResourceKey(?string $value): string
{
    $value = strtoupper(trim((string) $value));
    if ($value === '') {
        return '';
    }

    if (preg_match('/\d+/', $value, $match)) {
        $digits = (string) ((int) $match[0]);
        $prefix = preg_replace('/\d+/', '', $value);
        $prefix = preg_replace('/\s+/', '', (string) $prefix);
        return $prefix . $digits;
    }

    return lineFilterKey($value);
}

function performanceLineKeyAliases(?string $value): array
{
    $value = trim((string) $value);
    if ($value === '') {
        return [];
    }

    $aliases = [
        normalizePerformanceResourceKey($value),
        normalizePerformanceResourceKey(lineLabel($value)),
        lineFilterKey($value),
    ];

    if (preg_match('/(\d+)/', $value, $match)) {
        $lineNumber = (int) $match[1];
        $aliases[] = (string) $lineNumber;
        $aliases[] = sprintf('%02d', $lineNumber);
        $aliases[] = 'LINHA' . $lineNumber;
        $aliases[] = 'LINHA' . sprintf('%02d', $lineNumber);
    }

    return array_values(array_unique(array_filter($aliases, static fn(string $alias): bool => $alias !== '')));
}

function loadCodiPerformanceCatalog(): array
{
    static $catalog = null;
    if (is_array($catalog)) {
        return $catalog;
    }

    $catalog = [
        'by_key' => [],
        'by_sku' => [],
    ];
    if (!class_exists(\Codi\CodiClient::class)) {
        require_once __DIR__ . '/src/Codi/CodiClient.php';
    }

    $baseUrl = getenv('CODI_URL') ?: 'http://192.168.8.246:8080';
    $username = getenv('CODI_USER') ?: 'Aghiggi';
    $password = getenv('CODI_PASS') ?: '@Ag0351@';
    $companyCode = getenv('CODI_COMPANY') ?: 'matriz';

    try {
        $client = new \Codi\CodiClient($baseUrl, $username, $password, $companyCode);
        $performance = $client->getPerformance(['pageSize' => 1000]);
    } catch (Throwable $e) {
        return $catalog;
    }

    $rows = $performance['data'] ?? [];
    if (!is_array($rows)) {
        return $catalog;
    }

    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }

        $sku = trim((string) ($row['item']['codItem'] ?? ''));
        $resourceAliases = performanceLineKeyAliases((string) ($row['grandeza']['recurso']['nomeRecurso'] ?? ''));
        $resourceCode = trim((string) ($row['grandeza']['recurso']['codigoRecurso'] ?? $row['grandeza']['recurso']['codigo'] ?? ''));
        if ($resourceCode !== '') {
            $resourceAliases[] = $resourceCode;
        }
        $performanceValue = isset($row['performance']) ? (float) $row['performance'] : null;

        if ($sku === '' || empty($resourceAliases) || $performanceValue === null) {
            continue;
        }

        foreach (array_unique($resourceAliases) as $resourceAlias) {
            $catalog['by_key'][$resourceAlias . '|' . $sku] = $performanceValue;
        }

        $catalog['by_sku'][$sku] ??= [];
        $catalog['by_sku'][$sku][] = $performanceValue;
    }

    return $catalog;
}

function resolveCodiNominalPerformance(array $catalog, string $resourceKey, string $sku): ?float
{
    $sku = trim($sku);
    if ($sku === '') {
        return null;
    }

    $resourceKey = trim($resourceKey);
    if ($resourceKey !== '' && isset($catalog['by_key'][$resourceKey . '|' . $sku])) {
        return (float) $catalog['by_key'][$resourceKey . '|' . $sku];
    }

    $skuValues = $catalog['by_sku'][$sku] ?? [];
    if (!is_array($skuValues) || $skuValues === []) {
        return null;
    }

    $uniqueValues = array_values(array_unique(array_map(static fn($value): string => (string) $value, $skuValues)));
    if (count($uniqueValues) === 1) {
        return (float) $uniqueValues[0];
    }

    return null;
}

function calculateCodiEfficiencyPct(?float $rawEfficiencyPct, ?float $nominalPerformance, float $producaoEventos, float $tempoProducao): ?float
{
    if ($nominalPerformance !== null && $nominalPerformance > 0.0001 && $tempoProducao > 0.0001) {
        return ($producaoEventos / $tempoProducao / $nominalPerformance) * 100;
    }

    return $rawEfficiencyPct;
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

function consolidateIntervals(array $intervals): array
{
    if ($intervals === []) {
        return [];
    }

    usort($intervals, static function (array $a, array $b): int {
        $aStart = $a['start'] instanceof DateTimeImmutable ? $a['start']->getTimestamp() : 0;
        $bStart = $b['start'] instanceof DateTimeImmutable ? $b['start']->getTimestamp() : 0;
        if ($aStart !== $bStart) {
            return $aStart <=> $bStart;
        }

        $aEnd = $a['end'] instanceof DateTimeImmutable ? $a['end']->getTimestamp() : 0;
        $bEnd = $b['end'] instanceof DateTimeImmutable ? $b['end']->getTimestamp() : 0;
        return $aEnd <=> $bEnd;
    });

    $merged = [];
    $current = $intervals[0];

    foreach (array_slice($intervals, 1) as $interval) {
        $currEnd = $current['end'];
        $nextStart = $interval['start'];
        $nextEnd = $interval['end'];

        if (!$currEnd instanceof DateTimeImmutable || !$nextStart instanceof DateTimeImmutable || !$nextEnd instanceof DateTimeImmutable) {
            continue;
        }

        // Unir quando encosta ou sobrepoe.
        if ($nextStart <= $currEnd) {
            if ($nextEnd > $currEnd) {
                $current['end'] = $nextEnd;
            }
            continue;
        }

        $merged[] = $current;
        $current = $interval;
    }

    $merged[] = $current;

    return $merged;
}

function workingMinutesByShift(WorkCalendar $calendar, DateTimeImmutable $start, DateTimeImmutable $end): array
{
    $adm = 0;
    $noite = 0;

    $startTs = $start->getTimestamp();
    $endTs = $end->getTimestamp();
    $day = $start->setTime(0, 0)->modify('-1 day');
    $endDay = $end->setTime(0, 0);
    $endDayTs = $endDay->getTimestamp();

    while ($day->getTimestamp() <= $endDayTs) {
        $windows = [
            ['bucket' => 'adm', 'start' => $day->setTime(7, 5, 0), 'end' => $day->setTime(11, 30, 0)],
            ['bucket' => 'adm', 'start' => $day->setTime(13, 27, 0), 'end' => $day->setTime(17, 45, 0)],
            ['bucket' => 'noite', 'start' => $day->setTime(17, 45, 0), 'end' => $day->setTime(22, 0, 0)],
            ['bucket' => 'noite', 'start' => $day->setTime(23, 0, 0), 'end' => $day->modify('+1 day')->setTime(3, 0, 0)],
        ];

        foreach ($windows as $window) {
            $winStart = $window['start'];
            $winEnd = $window['end'];
            if (!$winStart instanceof DateTimeImmutable || !$winEnd instanceof DateTimeImmutable) {
                continue;
            }

            $segStart = ($startTs > $winStart->getTimestamp()) ? $start : $winStart;
            $segEnd = ($endTs < $winEnd->getTimestamp()) ? $end : $winEnd;

            if ($segEnd <= $segStart) {
                continue;
            }

            $minutes = (int) $calendar->workingMinutesBetween($segStart, $segEnd);
            if (($window['bucket'] ?? '') === 'adm') {
                $adm += $minutes;
            } else {
                $noite += $minutes;
            }
        }

        $day = $day->modify('+1 day');
    }

    // Garantir que nao retorne negativos em casos de intervalo vazio.
    return [
        'adm' => max(0, $adm),
        'noite' => max(0, $noite),
    ];
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

function formatDateTimeShortDisplay(?string $value, string $empty = '--'): string
{
    $dt = parseDateValue($value);
    if (!$dt instanceof DateTimeImmutable) {
        return $empty;
    }

    if ($dt->format('H:i:s') === '00:00:00' && !preg_match('/\d{2}:\d{2}/', (string) $value)) {
        return $dt->format('d/m');
    }

    return $dt->format('d/m H:i');
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

function deviationIntensityLevel(float $absValue, float $t1, float $t2, float $t3): int
{
    if ($absValue >= $t3) {
        return 3;
    }
    if ($absValue >= $t2) {
        return 2;
    }
    if ($absValue >= $t1) {
        return 1;
    }
    return 0;
}

function deviationIcon(float $value, float $epsilon = 0.0001): string
{
    if ($value > $epsilon) {
        return '↑';
    }
    if ($value < -$epsilon) {
        return '↓';
    }
    return '';
}

function heatClassFromDiff(float $diff, int $level, bool $goodWhenPositive): string
{
    if ($level <= 0) {
        return '';
    }

    $isPositive = $diff > 0.0001;
    $isNegative = $diff < -0.0001;
    if (!$isPositive && !$isNegative) {
        return '';
    }

    $isGood = ($isPositive && $goodWhenPositive) || ($isNegative && !$goodWhenPositive);
    return sprintf('heat-%s-%d', $isGood ? 'good' : 'bad', $level);
}

function classifyAnalyticBadge(
    float $prodPlan,
    float $prodDiff,
    float $tempoPrevMin,
    float $tempoRealMin,
    float $setupPlanMin,
    int $setupEvents,
    bool $setupCritical
): array {
    $prodRatio = ($prodPlan > 0.0001) ? ($prodDiff / $prodPlan) : 0.0;
    $tempoDiff = $tempoRealMin - $tempoPrevMin;
    $tempoRatio = ($tempoPrevMin > 0.0001) ? ($tempoDiff / $tempoPrevMin) : 0.0;

    $isCritical = $setupCritical
        || ($prodPlan > 0.0001 && $prodRatio <= -0.10)
        || ($tempoPrevMin > 0.0001 && $tempoDiff >= 60.0 && $tempoRatio >= 0.25);

    if ($isCritical) {
        return ['label' => 'Crítico', 'class' => 'tag-danger'];
    }

    $isWarning = ($setupPlanMin > 0.01 && $setupEvents <= 0)
        || ($prodPlan > 0.0001 && $prodRatio <= -0.03)
        || ($tempoPrevMin > 0.0001 && $tempoDiff >= 20.0 && $tempoRatio >= 0.10);

    if ($isWarning) {
        return ['label' => 'Atenção', 'class' => 'tag-warning'];
    }

    return ['label' => 'OK', 'class' => 'tag-ok'];
}

function calcBarPct(float $value, float $denominator, float $capRatio = 1.0): array
{
    if ($denominator <= 0.0001 || $value <= 0.0001) {
        return ['pct' => 0, 'exceed' => false];
    }

    $ratio = $value / $denominator;
    $exceed = $ratio > 1.0001;
    $clamped = min(max(0.0, $ratio), max(0.0, $capRatio));
    $pct = (int) round($clamped * 100);

    return ['pct' => max(0, min(100, $pct)), 'exceed' => $exceed];
}

function calcShiftComparePct(float $adm, float $noite, float $total): array
{
    if ($total <= 0.0001) {
        return ['adm' => 0, 'noite' => 0];
    }

    $admPct = (int) round(max(0.0, ($adm / $total)) * 100);
    $noitePct = (int) round(max(0.0, ($noite / $total)) * 100);
    $admPct = max(0, min(100, $admPct));
    $noitePct = max(0, min(100, $noitePct));

    // Garantir que a barra nao "estoure" visualmente por arredondamento.
    if (($admPct + $noitePct) > 100) {
        $noitePct = max(0, 100 - $admPct);
    }

    return ['adm' => $admPct, 'noite' => $noitePct];
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
    'producao_realizada_adm' => 0.0,
    'producao_realizada_noite' => 0.0,
    'tempo_previsto_min' => 0.0,
    'tempo_realizado_min' => 0.0,
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
            pi.prg_inicio_planejado,
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
    $plannedStartByOp = [];
    foreach ($itemsRows as $item) {
        $sku = trim((string) ($item['prg_sku'] ?? ''));
        if ($sku === '') {
            continue;
        }

        $itemsBySku[$sku] ??= [];
        $op = trim((string) ($item['prg_itens_op'] ?? ''));
        $inicioPlanejado = trim((string) ($item['prg_inicio_planejado'] ?? ''));
        if ($op !== '' && $inicioPlanejado !== '') {
            $dt = parseDateValue($inicioPlanejado);
            if ($dt instanceof DateTimeImmutable) {
                $normalized = $dt->format('Y-m-d H:i:s');
                if (!isset($plannedStartByOp[$op]) || $normalized < $plannedStartByOp[$op]) {
                    $plannedStartByOp[$op] = $normalized;
                }
            }
        }
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
                'producao_realizada_adm' => 0.0,
                'producao_realizada_noite' => 0.0,
                'tempo_previsto_min' => 0.0,
                'tempo_realizado_min' => 0.0,
                'inicio_planejado_item' => $plannedStartByOp[trim((string) $op)] ?? '',
                'inicio_planejado_schedule' => '',
                'inicio_real' => '',
                'fim_real' => '',
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

        $inicioSchedule = trim((string) ($row['sch_inicio_producao'] ?? ''));
        if ($inicioSchedule !== '') {
            $dtInicioSchedule = parseDateValue($inicioSchedule);
            if ($dtInicioSchedule instanceof DateTimeImmutable) {
                $normalized = $dtInicioSchedule->format('Y-m-d H:i:s');
                $current = (string) ($reportRows[$op]['inicio_planejado_schedule'] ?? '');
                if ($current === '' || $normalized < $current) {
                    $reportRows[$op]['inicio_planejado_schedule'] = $normalized;
                }
            }
        }

        $reportRows[$op]['sku'] = $reportRows[$op]['sku'] !== '' ? $reportRows[$op]['sku'] : $sku;
        $reportRows[$op]['setup_previsto_min'] += $pendingSetupMinutes;
        $reportRows[$op]['producao_prevista'] += $plannedQty;
        $reportRows[$op]['tempo_previsto_min'] += $durationMinutes;
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
                ) AS setup_realizado_eventos,
                SUM(
                    CASE
                        WHEN inicio_evento IS NOT NULL
                             AND fim_evento IS NOT NULL
                             AND LENGTH(TRIM(inicio_evento)) > 0
                             AND LENGTH(TRIM(fim_evento)) > 0
                        THEN GREATEST(0, TIMESTAMPDIFF(MINUTE, inicio_evento, fim_evento))
                        ELSE 0
                    END
                ) AS tempo_realizado_min,
                MIN(
                    CASE
                        WHEN inicio_evento IS NOT NULL AND LENGTH(TRIM(inicio_evento)) > 0
                        THEN inicio_evento
                        ELSE NULL
                    END
                ) AS inicio_real,
                MAX(
                    CASE
                        WHEN fim_evento IS NOT NULL AND LENGTH(TRIM(fim_evento)) > 0
                        THEN fim_evento
                        ELSE NULL
                    END
                ) AS fim_real
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
            $reportRows[$op]['tempo_realizado_min'] = (float) ($row['tempo_realizado_min'] ?? 0);
        $reportRows[$op]['inicio_real'] = (string) ($row['inicio_real'] ?? '');
        $reportRows[$op]['fim_real'] = (string) ($row['fim_real'] ?? '');
    }

    $tempoProducaoByOp = [];
    if (tableExists($pdo, 'realizado_2026_eventos')) {
        $tempoProdStmt = $pdo->prepare(
            "
            SELECT
                ordem_op,
                SUM(
                    CASE
                        WHEN estado_evento = 'PRODUCAO'
                        THEN quantidade
                        ELSE 0
                    END
                ) AS producao_eventos,
                SUM(
                    CASE
                        WHEN estado_evento = 'PRODUCAO'
                        THEN duracao_evento_minutos
                        ELSE 0
                    END
                ) AS tempo_producao_min
            FROM realizado_2026_eventos
            WHERE data_evento BETWEEN ? AND ?
              AND ordem_op IN ($placeholders)
            GROUP BY ordem_op
            "
        );
        $tempoProdStmt->execute(array_merge([$reportPeriodStart, $reportPeriodEnd], $ops));
        foreach ($tempoProdStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $op = trim((string) ($row['ordem_op'] ?? ''));
            if ($op === '') {
                continue;
            }

            $tempoProducaoByOp[$op] = [
                'producao_eventos' => (float) ($row['producao_eventos'] ?? 0),
                'tempo_producao_min' => (float) ($row['tempo_producao_min'] ?? 0),
            ];
        }
    }

    // Quando o filtro `linha` vem vazio (linha=), devemos usar a linha do programa selecionado.
    // Caso contrario, a chave vira "S/linha" e o nominal do CODI nao e encontrado, caindo no calculo bruto.
    $lineForPerformance = $selectedLine !== '' ? $selectedLine : (string) ($selectedProgram['lin_codigo'] ?? '');
    $selectedLinePerformanceKey = normalizePerformanceResourceKey(lineLabel($lineForPerformance));
    $codiPerformanceCatalog = loadCodiPerformanceCatalog();

    $codiEfficiencyByOp = [];
    if (tableExists($pdo, 'realizado_2026_eventos')) {
        $codiPerfStmt = $pdo->prepare(
            "
            SELECT
                ordem_op,
                CASE
                    WHEN SUM(
                        CASE
                            WHEN estado_evento = 'PRODUCAO'
                                 AND JSON_EXTRACT(payload_json, '$.performancePeriodo') IS NOT NULL
                            THEN duracao_evento_minutos
                            ELSE 0
                        END
                    ) > 0
                    THEN (
                        SUM(
                            CASE
                                WHEN estado_evento = 'PRODUCAO'
                                     AND JSON_EXTRACT(payload_json, '$.performancePeriodo') IS NOT NULL
                                THEN JSON_EXTRACT(payload_json, '$.performancePeriodo') * duracao_evento_minutos
                                ELSE 0
                            END
                        ) /
                        SUM(
                            CASE
                                WHEN estado_evento = 'PRODUCAO'
                                     AND JSON_EXTRACT(payload_json, '$.performancePeriodo') IS NOT NULL
                                THEN duracao_evento_minutos
                                ELSE 0
                            END
                        )
                    ) * 100
                    ELSE NULL
                END AS eficiencia_codi_pct
            FROM realizado_2026_eventos
            WHERE data_evento BETWEEN ? AND ?
              AND ordem_op IN ($placeholders)
            GROUP BY ordem_op
            "
        );
        $codiPerfStmt->execute(array_merge([$reportPeriodStart, $reportPeriodEnd], $ops));
        foreach ($codiPerfStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $op = trim((string) ($row['ordem_op'] ?? ''));
            if ($op === '') {
                continue;
            }
            $codiEfficiencyByOp[$op] = isset($row['eficiencia_codi_pct']) ? (float) $row['eficiencia_codi_pct'] : null;
        }
    }

    // Recalcular tempo realizado aplicando calendario produtivo (turnos) sobre os intervalos reais do CODI,
    // com consolidacao de intervalos sem sobreposicao.
    if ($workCalendar instanceof WorkCalendar) {
        $intervalStmt = $pdo->prepare(
                "
                SELECT
                    ordem_op,
                    inicio_evento,
                    fim_evento,
                    quantidade
                FROM realizado_2026_excel
                WHERE data_evento BETWEEN ? AND ?
                  AND ordem_op IN ($placeholders)
                  AND inicio_evento IS NOT NULL
                  AND fim_evento IS NOT NULL
                  AND LENGTH(TRIM(inicio_evento)) > 0
                  AND LENGTH(TRIM(fim_evento)) > 0
                ORDER BY ordem_op ASC, inicio_evento ASC, fim_evento ASC
                "
            );
            $intervalStmt->execute(array_merge([$reportPeriodStart, $reportPeriodEnd], $ops));
            $intervalRows = $intervalStmt->fetchAll(PDO::FETCH_ASSOC);

            $intervalsByOp = [];
            $eventsByOp = [];
            foreach ($intervalRows as $evt) {
                $op = trim((string) ($evt['ordem_op'] ?? ''));
                if ($op === '' || !isset($reportRows[$op])) {
                    continue;
                }

                $start = parseDateValue($evt['inicio_evento'] ?? null);
                $end = parseDateValue($evt['fim_evento'] ?? null);
                if (!$start instanceof DateTimeImmutable || !$end instanceof DateTimeImmutable) {
                    continue;
                }
                if ($end <= $start) {
                    continue;
                }

                $intervalsByOp[$op] ??= [];
                $intervalsByOp[$op][] = ['start' => $start, 'end' => $end];

                $eventsByOp[$op] ??= [];
                $eventsByOp[$op][] = [
                    'start' => $start,
                    'end' => $end,
                    'qty' => (float) ($evt['quantidade'] ?? 0),
                ];
            }

            foreach ($intervalsByOp as $op => $intervals) {
                $merged = consolidateIntervals($intervals);
                $minutes = 0;
                foreach ($merged as $segment) {
                    $segStart = $segment['start'] ?? null;
                    $segEnd = $segment['end'] ?? null;
                    if ($segStart instanceof DateTimeImmutable && $segEnd instanceof DateTimeImmutable && $segEnd > $segStart) {
                        $minutes += (int) $workCalendar->workingMinutesBetween($segStart, $segEnd);
                    }
                }

                $reportRows[$op]['tempo_realizado_min'] = (float) max(0, $minutes);
            }

            // Classificar a producao realizada por turno usando o mesmo calendario produtivo.
            foreach ($eventsByOp as $op => $events) {
                $admQty = 0.0;
                $noiteQty = 0.0;

                foreach ($events as $event) {
                    $segStart = $event['start'] ?? null;
                    $segEnd = $event['end'] ?? null;
                    $qty = (float) ($event['qty'] ?? 0);
                    if ($qty <= 0) {
                        continue;
                    }

                    if (!$segStart instanceof DateTimeImmutable || !$segEnd instanceof DateTimeImmutable || $segEnd <= $segStart) {
                        continue;
                    }

                    $shiftMinutes = workingMinutesByShift($workCalendar, $segStart, $segEnd);
                    $admMinutes = (int) ($shiftMinutes['adm'] ?? 0);
                    $noiteMinutes = (int) ($shiftMinutes['noite'] ?? 0);
                    $denom = $admMinutes + $noiteMinutes;
                    if ($denom <= 0) {
                        continue;
                    }

                    $admQty += $qty * ($admMinutes / $denom);
                    $noiteQty += $qty * ($noiteMinutes / $denom);
                }

                $reportRows[$op]['producao_realizada_adm'] = $admQty;
                $reportRows[$op]['producao_realizada_noite'] = $noiteQty;
            }
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
        $summary['producao_realizada_adm'] += (float) ($row['producao_realizada_adm'] ?? 0);
        $summary['producao_realizada_noite'] += (float) ($row['producao_realizada_noite'] ?? 0);
        $summary['tempo_previsto_min'] += (float) ($row['tempo_previsto_min'] ?? 0);
        $summary['tempo_realizado_min'] += (float) ($row['tempo_realizado_min'] ?? 0);
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
        $op = trim((string) ($row['op'] ?? ''));
        $sku = trim((string) ($row['sku'] ?? ''));
        $eficienciaCodiRawPct = $op !== '' && isset($codiEfficiencyByOp[$op]) ? (float) $codiEfficiencyByOp[$op] : null;
        $eficienciaCodiPct = $eficienciaCodiRawPct;

        if ($op !== '' && $sku !== '') {
            $nominalPerformance = resolveCodiNominalPerformance($codiPerformanceCatalog, $selectedLinePerformanceKey, $sku);
            $tempoProducao = (float) ($tempoProducaoByOp[$op]['tempo_producao_min'] ?? 0);
            $producaoEventos = (float) ($tempoProducaoByOp[$op]['producao_eventos'] ?? ($row['producao_realizada'] ?? 0));
            $eficienciaCodiPct = calculateCodiEfficiencyPct($eficienciaCodiRawPct, $nominalPerformance, $producaoEventos, $tempoProducao);
        }

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
            'eficiencia_proj_pct' => (float) ($selectedProgram['prg_eficiencia'] ?? 0),
            'eficiencia_codi_pct' => $eficienciaCodiPct,
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

        .app-build-badge {
            display: inline-flex;
            align-items: center;
            margin-left: 10px;
            padding: 2px 8px;
            border-radius: 999px;
            border: 1px solid rgba(12, 33, 58, 0.14);
            background: rgba(12, 33, 58, 0.04);
            color: #5a6778;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: .08em;
            text-transform: uppercase;
            line-height: 1;
            white-space: nowrap;
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

        /* Oculta somente visualmente, mantendo o espaço e o submit do form. */
        .filtros-hidden {
            visibility: hidden;
            pointer-events: none;
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

        .kpi-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 14px;
            padding: 18px 20px;
            border-bottom: 1px solid var(--border);
            background: linear-gradient(180deg, #ffffff 0%, #fbfdff 100%);
        }

        .kpi-card {
            padding: 14px 16px;
        }

        .kpi-card .value {
            font-size: 24px;
        }

        .status-grid {
            display: grid;
            grid-template-columns: repeat(5, minmax(0, 1fr));
            gap: 14px;
            margin: 0 0 18px;
        }

        .top-kpis--hidden {
            display: none;
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
            overflow: visible;
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
            overflow: visible;
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

        tbody tr.row-expandable {
            cursor: pointer;
        }

        tbody tr.row-expand-content {
            display: none;
        }

        tbody tr.row-expand-content.is-open {
            display: table-row;
        }

        tbody tr.row-expand-content td {
            padding: 0;
            border-bottom: 1px solid #e2e8f0;
            background: #f8fafc;
        }

        .inline-detail {
            padding: 14px 16px 18px;
        }

        .inline-detail__grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 14px;
            align-items: start;
        }

        .inline-detail__panel {
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 10px 20px rgba(15, 23, 42, 0.05);
        }

        .inline-detail__panel--spaced {
            margin-bottom: 14px;
        }

        .inline-detail__panel h4 {
            margin: 0;
            padding: 10px 12px;
            font-size: 11px;
            font-weight: 800;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 0.06em;
            background: #fbfdff;
            border-bottom: 1px solid var(--border);
        }

        .inline-detail__panel .body {
            padding: 10px 12px 12px;
        }

        .inline-detail__loading,
        .inline-detail__error {
            color: var(--muted);
            font-size: 13px;
            padding: 10px 12px;
        }

        .inline-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 0;
        }

        .inline-table th,
        .inline-table td {
            padding: 8px 10px;
            border-bottom: 1px solid #edf2f7;
            font-size: 13px;
            vertical-align: top;
        }

        .inline-table th {
            background: #f8fbff;
            color: var(--muted);
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.06em;
        }

        .inline-table tr:last-child td {
            border-bottom: none;
        }

        .op-cell {
            font-weight: 800;
            color: var(--accent-2);
            white-space: nowrap;
        }

        .cell-sub {
            display: block;
            margin-top: 4px;
            font-size: 12px;
            font-weight: 600;
            color: var(--muted);
            white-space: nowrap;
        }

        td.cell-bar {
            position: relative;
            overflow: hidden;
        }

        td.cell-bar::before {
            content: "";
            position: absolute;
            left: 8px;
            top: 8px;
            bottom: 8px;
            width: var(--bar-pct, 0%);
            max-width: calc(100% - 16px);
            border-radius: 12px;
            background: rgba(148, 163, 184, 0.14);
            pointer-events: none;
            z-index: 0;
        }

        td.cell-bar .cell-bar__value {
            position: relative;
            z-index: 1;
            display: inline-block;
        }

        td.cell-bar--prod::before {
            background: rgba(5, 150, 105, 0.10);
        }

        td.cell-bar--shift::before {
            background: rgba(99, 102, 241, 0.10);
        }

        td.cell-bar.is-exceed::before {
            background: rgba(5, 150, 105, 0.16);
        }

        td.cell-bar.is-exceed {
            box-shadow: inset 0 0 0 1px rgba(5, 150, 105, 0.22);
            border-radius: 10px;
        }

        .shift-compare {
            margin-top: 8px;
            height: 8px;
            background: rgba(148, 163, 184, 0.18);
            border-radius: 999px;
            overflow: hidden;
            display: flex;
            gap: 0;
        }

        .shift-compare__adm {
            background: rgba(59, 130, 246, 0.65);
        }

        .shift-compare__noite {
            background: rgba(168, 85, 247, 0.65);
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

        .tag-danger {
            background: #fee2e2;
            color: #991b1b;
        }

        .tag-muted {
            background: #e2e8f0;
            color: #475569;
        }

        .tag-ok {
            background: #dcfce7;
            color: #166534;
        }

        .diff-icon {
            display: inline-block;
            width: 16px;
            text-align: center;
            font-weight: 900;
            opacity: 0.9;
        }

        td.heat-good-1 { background: rgba(5, 150, 105, 0.05); }
        td.heat-good-2 { background: rgba(5, 150, 105, 0.08); }
        td.heat-good-3 { background: rgba(5, 150, 105, 0.12); }
        td.heat-bad-1 { background: rgba(220, 38, 38, 0.05); }
        td.heat-bad-2 { background: rgba(220, 38, 38, 0.085); }
        td.heat-bad-3 { background: rgba(220, 38, 38, 0.13); }

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

        /* Mantem destaque mesmo em linhas com gradiente de status. */
        tbody tr.row-expandable.is-expanded {
            background: #eef2ff;
        }

        tbody tr.row-expandable.is-expanded:hover {
            background: #e0e7ff;
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

        .detail-summary-check {
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .detail-summary-check input {
            width: 16px;
            height: 16px;
            accent-color: var(--accent);
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

            .kpi-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
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

            .kpi-grid {
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
                    <strong><?= h($setupRuleLabel) ?></strong> <?= render_app_build_badge() ?>.
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
            <div class="filter-group filtros-hidden">
                <label for="data_inicio">Período inicial</label>
                <input id="data_inicio" name="data_inicio" type="date" value="<?= h($selectedPeriodStartInput) ?>">
            </div>
            <div class="filter-group filtros-hidden">
                <label for="data_fim">Período final</label>
                <input id="data_fim" name="data_fim" type="date" value="<?= h($selectedPeriodEndInput) ?>">
            </div>
            <div class="filter-group filtros-hidden">
                <label for="status">Leitura</label>
                <select id="status" name="status" onchange="this.form.submit()">
                    <option value="all" <?= $selectedStatus === 'all' ? 'selected' : '' ?>>Todas as OPs</option>
                    <option value="realizado" <?= $selectedStatus === 'realizado' ? 'selected' : '' ?>>Somente com setup realizado</option>
                    <option value="positivo" <?= $selectedStatus === 'positivo' ? 'selected' : '' ?>>Somente desvio positivo</option>
                    <option value="negativo" <?= $selectedStatus === 'negativo' ? 'selected' : '' ?>>Somente desvio negativo</option>
                    <option value="sem_evento" <?= $selectedStatus === 'sem_evento' ? 'selected' : '' ?>>Somente sem evento</option>
                </select>
            </div>
            <button class="btn btn-primary filtros-hidden" type="submit">Aplicar filtros</button>
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

    <div class="top-kpis top-kpis--hidden">
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
    </div>

    <div class="report-card">
        <div class="report-head">
            <div>
                <h2>Detalhe por Linha</h2>
                <p>Ordenado pela sequência da programação/histórico. O setup previsto vem de <code>sch_linhas.sch_duracao_minutos</code> e o realizado de <code>setup_duracao_minutos</code> para as paradas alvo.</p>
            </div>
            <div class="tag"><?= h($setupRuleLabel) ?></div>
        </div>

        <?php
            $kpiProdPlan = (float) ($summary['producao_prevista'] ?? 0);
            $kpiProdReal = (float) ($summary['producao_realizada'] ?? 0);
            $kpiProdDiff = $kpiProdReal - $kpiProdPlan;
            $kpiTempoPrev = (float) ($summary['tempo_previsto_min'] ?? 0);
            $kpiTempoReal = (float) ($summary['tempo_realizado_min'] ?? 0);
            $kpiProdAdm = (float) ($summary['producao_realizada_adm'] ?? 0);
            $kpiProdNoite = (float) ($summary['producao_realizada_noite'] ?? 0);
            $kpiProdDenom = $kpiProdReal > 0.0001 ? $kpiProdReal : 0.0;
            $kpiAdmPct = $kpiProdDenom > 0 ? (int) round(($kpiProdAdm / $kpiProdDenom) * 100) : 0;
            $kpiNoitePct = $kpiProdDenom > 0 ? (int) round(($kpiProdNoite / $kpiProdDenom) * 100) : 0;
            $kpiDiffClass = $kpiProdDiff >= 0 ? 'is-positive' : 'is-danger';
        ?>
        <div class="kpi-grid">
            <div class="summary-card kpi-card">
                <div class="label">Produção prevista</div>
                <div class="value"><?= h(formatQtyRounded($kpiProdPlan)) ?></div>
            </div>
            <div class="summary-card kpi-card">
                <div class="label">Produção realizada</div>
                <div class="value"><?= h(formatQtyRounded($kpiProdReal)) ?></div>
            </div>
            <div class="summary-card kpi-card <?= $kpiDiffClass ?>">
                <div class="label">Diferença de produção</div>
                <div class="value"><?= h(sprintf('%s%s', $kpiProdDiff >= 0 ? '+' : '-', formatQtyRounded(abs($kpiProdDiff)))) ?></div>
            </div>
            <div class="summary-card kpi-card">
                <div class="label">Tempo previsto</div>
                <div class="value"><?= h(formatDurationClock(max(0.0, $kpiTempoPrev))) ?></div>
            </div>
            <div class="summary-card kpi-card">
                <div class="label">Tempo realizado</div>
                <div class="value"><?= h(formatDurationClock(max(0.0, $kpiTempoReal))) ?></div>
            </div>
            <div class="summary-card kpi-card">
                <div class="label">Participação turno</div>
                <div class="value"><?= h(sprintf('ADM %d%% | Noite %d%%', $kpiAdmPct, $kpiNoitePct)) ?></div>
            </div>
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
                        <th>Turno ADM</th>
                        <th>Turno noite</th>
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
                                $prodRealAdm = (float) ($row['producao_realizada_adm'] ?? 0);
                                $prodRealNoite = (float) ($row['producao_realizada_noite'] ?? 0);
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
                                $setupDiffIcon = deviationIcon((float) $setupDiffDisplay);
                                $prodDiffIcon = deviationIcon((float) $prodRowDiff);
                                $setupHeatLevel = deviationIntensityLevel(abs((float) $setupDiffDisplay), 1.0, 10.0, 30.0);
                                $setupHeatClass = heatClassFromDiff((float) $setupDiffDisplay, $setupHeatLevel, false);
                                $prodHeatLevel = 0;
                                if ($prodPlan > 0.0001) {
                                    $prodHeatLevel = deviationIntensityLevel(abs((float) $prodRowDiff) / $prodPlan, 0.02, 0.05, 0.10);
                                } else {
                                    $prodHeatLevel = deviationIntensityLevel(abs((float) $prodRowDiff), 5.0, 20.0, 50.0);
                                }
                                $prodHeatClass = heatClassFromDiff((float) $prodRowDiff, $prodHeatLevel, true);
                                $analyticBadge = classifyAnalyticBadge(
                                    $prodPlan,
                                    (float) $prodRowDiff,
                                    $tempoPrevisto,
                                    $tempoRealizado,
                                    $setupPlan,
                                    (int) ($row['setup_realizado_eventos'] ?? 0),
                                    !empty($row['is_critical'])
                                );
                                $barProdReal = calcBarPct($prodReal, $prodPlan);
                                $barProdAdm = calcBarPct($prodRealAdm, $prodReal);
                                $barProdNoite = calcBarPct($prodRealNoite, $prodReal);
                                $shiftCompare = calcShiftComparePct($prodRealAdm, $prodRealNoite, $prodReal);
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
                            <tr
                                class="<?= h((string) ($row['row_class'] ?? $setupStatus['row_class'])) ?> row-expandable"
                                data-op="<?= h((string) $row['op']) ?>"
                                data-period-start="<?= h($reportPeriodStart) ?>"
                                data-period-end="<?= h($reportPeriodEnd) ?>"
                                data-setup-plan-min="<?= h((string) $setupPlan) ?>"
                                data-prod-adm-label="<?= h(formatQtyRounded($prodRealAdm)) ?>"
                                data-prod-noite-label="<?= h(formatQtyRounded($prodRealNoite)) ?>"
                            >
                                <td class="op-cell">OP <?= h((string) $row['op']) ?></td>
                                <td class="desc-cell"><?= h($desc) ?></td>
                                <td class="sku-cell"><?= h((string) ($row['sku'] ?? '')) ?></td>
                                <td><?= h(formatMinutes($setupPlan)) ?></td>
                                <td class="<?= h($setupRealClassDisplay) ?>"><?= h(formatMinutes($setupRealDisplay)) ?></td>
                                <td class="<?= h(trim($setupClassDisplay . ' ' . $setupHeatClass)) ?>">
                                    <span class="diff-icon"><?= h($setupDiffIcon) ?></span><?= h(formatSignedMinutes($setupDiffDisplay)) ?>
                                </td>
                                <td><?= h(formatQtyRounded($prodPlan)) ?></td>
                                <td
                                    class="<?= h(trim('cell-bar cell-bar--prod ' . (!empty($barProdReal['exceed']) ? 'is-exceed' : ''))) ?>"
                                    style="--bar-pct: <?= (int) ($barProdReal['pct'] ?? 0) ?>%;"
                                >
                                    <span class="cell-bar__value"><?= h(formatQtyRounded($prodReal)) ?></span>
                                </td>
                                <td class="cell-bar cell-bar--shift" style="--bar-pct: <?= (int) ($barProdAdm['pct'] ?? 0) ?>%;">
                                    <span class="cell-bar__value"><?= h(formatQtyRounded($prodRealAdm)) ?></span>
                                </td>
                                <td class="cell-bar cell-bar--shift" style="--bar-pct: <?= (int) ($barProdNoite['pct'] ?? 0) ?>%;">
                                    <span class="cell-bar__value"><?= h(formatQtyRounded($prodRealNoite)) ?></span>
                                </td>
                                <td class="<?= h(trim($prodClass . ' ' . $prodHeatClass)) ?>">
                                    <span class="diff-icon"><?= h($prodDiffIcon) ?></span><?= h(sprintf('%s%s', $prodRowDiff >= 0 ? '+' : '-', formatQtyRounded(abs($prodRowDiff)))) ?>
                                </td>
                                <td>
                                    <?= h(formatDurationClock(max(0.0, $tempoPrevisto))) ?>
                                    <?php
                                        $inicioRealLabel = formatDateTimeShortDisplay((string) ($row['inicio_real'] ?? ''), '');
                                    ?>
                                    <?php if ($inicioRealLabel !== ''): ?>
                                        <span class="cell-sub"><?= h($inicioRealLabel) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?= h(formatDurationClock(max(0.0, $tempoRealizado))) ?>
                                    <?php
                                        $fimRealLabel = formatDateTimeShortDisplay((string) ($row['fim_real'] ?? ''), '');
                                    ?>
                                    <?php if ($fimRealLabel !== ''): ?>
                                        <span class="cell-sub"><?= h($fimRealLabel) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="tag <?= h((string) ($row['tag_class'] ?? $setupStatus['tag_class'])) ?>" title="<?= h((string) ($row['tag_title'] ?? $setupStatus['title'])) ?>">
                                        <?= h((string) ($row['tag_label'] ?? $setupStatus['label'])) ?>
                                    </span>
                                    <span class="tag <?= h((string) ($analyticBadge['class'] ?? 'tag-muted')) ?>">
                                        <?= h((string) ($analyticBadge['label'] ?? 'OK')) ?>
                                    </span>
                                    <div
                                        class="shift-compare"
                                        title="<?= h(sprintf('ADM %d%% | Noite %d%%', (int) ($shiftCompare['adm'] ?? 0), (int) ($shiftCompare['noite'] ?? 0))) ?>"
                                    >
                                        <span class="shift-compare__adm" style="width: <?= (int) ($shiftCompare['adm'] ?? 0) ?>%;"></span>
                                        <span class="shift-compare__noite" style="width: <?= (int) ($shiftCompare['noite'] ?? 0) ?>%;"></span>
                                    </div>
                                    <button
                                        class="detail-btn"
                                        type="button"
                                        data-op="<?= h((string) $row['op']) ?>"
                                        data-period-start="<?= h($reportPeriodStart) ?>"
                                        data-period-end="<?= h($reportPeriodEnd) ?>"
                                        data-setup-plan-min="<?= h((string) $setupPlan) ?>"
                                        data-setup-plan="<?= h(formatMinutes($setupPlan)) ?>"
                                        data-setup-real="<?= h(formatMinutes($setupReal)) ?>"
                                        data-eff-proj="<?= h(formatPercentDisplay((float) ($row['eficiencia_proj_pct'] ?? 0))) ?>"
                                        data-eff-codi="<?= h(isset($row['eficiencia_codi_pct']) ? formatPercentDisplay((float) $row['eficiencia_codi_pct']) : '--') ?>"
                                        title="Abrir detalhe complementar da OP"
                                    >
                                        Ver detalhe
                                    </button>
                                </td>
                            </tr>
                            <tr class="row-expand-content" data-op="<?= h((string) $row['op']) ?>">
                                <td colspan="14">
                                    <div class="inline-detail">
                                        <div class="inline-detail__loading">Clique na linha para carregar o detalhe desta OP.</div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="14">
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
                            <tfoot>
                                <tr class="detail-group-total">
                                    <td>Total selecionado</td>
                                    <td id="detail-grouped-total">00:00</td>
                                </tr>
                            </tfoot>
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
    const groupedTotalEl = document.getElementById('detail-grouped-total');
    const principalBody = document.getElementById('detail-principal-body');
    const apoioBody = document.getElementById('detail-apoio-body');
    const noteEl = document.getElementById('op-detail-note');
    const mainOnlyToggle = document.getElementById('detail-main-only-toggle');
    const closeTargets = modal.querySelectorAll('[data-close-detail]');
    const detailButtons = document.querySelectorAll('.detail-btn[data-op]');
    const detailState = {
        mainOnly: true,
        data: null,
        meta: null
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

    function updateGroupedTotalLabel(minutes) {
        if (!groupedTotalEl) {
            return;
        }

        groupedTotalEl.textContent = formatMinutesLabel(Number(minutes) || 0);
    }

    function syncGroupedTotalLabel(fallbackMinutes) {
        if (!groupedBody) {
            updateGroupedTotalLabel(fallbackMinutes);
            return;
        }

        const checkboxes = groupedBody.querySelectorAll('input.detail-summary-checkbox');
        if (!checkboxes.length) {
            updateGroupedTotalLabel(fallbackMinutes);
            return;
        }

        let sum = 0;
        checkboxes.forEach((checkbox) => {
            if (!checkbox.checked) {
                return;
            }

            sum += Number(checkbox.getAttribute('data-minutes') || 0);
        });

        updateGroupedTotalLabel(sum);
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
            syncGroupedTotalLabel(0);
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
                        <label class="detail-summary-check">
                            <input type="checkbox" class="detail-summary-checkbox" checked data-minutes="${escapeHtml(String(minutes))}">
                            <span class="tag ${badgeClass}">${escapeHtml(label)}</span>
                        </label>
                    </td>
            <td>${escapeHtml(duration)}</td>
        </tr>
            `;
        }).join('');

        target.innerHTML = summaryRows;
        syncGroupedTotalLabel(Number(totalMinutes) || visibleTotalMinutes || 0);
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
        const meta = detailState.meta || {};
        const effProjLabel = meta.effProj || '--';
        const effCodiLabel = meta.effCodi || '--';

        titleEl.textContent = `OP ${data.op} - detalhe complementar`;
        subtitleEl.innerHTML = `Cálculo principal preservado: somente <strong><?= h($setupRuleLabel) ?></strong>. Período: <strong>${escapeHtml(periodLabel)}</strong>.`;
        kpisEl.innerHTML = `
            <div class="detail-kpi">
                <div class="label">Eficiência projetada</div>
                <div class="value">${escapeHtml(effProjLabel)}</div>
            </div>
            <div class="detail-kpi">
                <div class="label">Eficiência CODI</div>
                <div class="value">${escapeHtml(effCodiLabel)}</div>
            </div>
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
        const effProj = button.getAttribute('data-eff-proj') || '--';
        const effCodi = button.getAttribute('data-eff-codi') || '--';

        titleEl.textContent = `OP ${op} - detalhe complementar`;
        subtitleEl.innerHTML = `Cálculo principal preservado: somente <strong><?= h($setupRuleLabel) ?></strong>. Período: <strong>${escapeHtml(formatDateDisplay(periodStart))} a ${escapeHtml(formatDateDisplay(periodEnd))}</strong>.`;
        detailState.data = null;
        detailState.meta = { effProj, effCodi };
        if (mainOnlyToggle) {
            mainOnlyToggle.checked = true;
        }
        detailState.mainOnly = true;
        updateGroupedTotalLabel(0);
        kpisEl.innerHTML = `
            <div class="detail-kpi">
                <div class="label">OP</div>
                <div class="value">${escapeHtml(op)}</div>
            </div>
            <div class="detail-kpi">
                <div class="label">Eficiência projetada</div>
                <div class="value">${escapeHtml(effProj)}</div>
            </div>
            <div class="detail-kpi">
                <div class="label">Eficiência CODI</div>
                <div class="value">${escapeHtml(effCodi)}</div>
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

    if (groupedBody) {
        groupedBody.addEventListener('change', (event) => {
            const target = event.target;
            if (target && target.matches && target.matches('input.detail-summary-checkbox')) {
                syncGroupedTotalLabel(0);
            }
        });
    }

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
<script>
(function () {
    const table = document.querySelector('.report-card .table-wrap table');
    if (!table) {
        return;
    }

    const cache = new Map();
    let openRow = null;
    let openContentRow = null;

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function parseDateParts(value) {
        if (!value) {
            return null;
        }

        const input = String(value).trim();
        if (!input) {
            return null;
        }

        const normalized = input.replace('T', ' ');
        const match = normalized.match(/^(\d{4})-(\d{2})-(\d{2})(?:\s+(\d{2}):(\d{2})(?::(\d{2}))?)?/);
        if (!match) {
            return null;
        }

        return {
            year: match[1],
            month: match[2],
            day: match[3],
            hour: match[4] || '',
            minute: match[5] || '',
        };
    }

    function formatDateTimeCompact(value) {
        const parts = parseDateParts(value);
        if (!parts) {
            return String(value ?? '--') || '--';
        }

        if (!parts.hour || !parts.minute) {
            return `${parts.day}/${parts.month}`;
        }

        return `${parts.day}/${parts.month} ${parts.hour}:${parts.minute}`;
    }

    function formatMinutesLabel(minutes) {
        const rounded = Math.round(Number(minutes) || 0);
        const clamped = rounded < 0 ? 0 : rounded;
        const hours = Math.floor(clamped / 60);
        const mins = clamped % 60;
        return `${String(hours).padStart(2, '0')}:${String(mins).padStart(2, '0')}`;
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

        return upperLabel !== 'SEM CLASSIFICACAO';
    }

    function filterTimelineRows(rows) {
        return (rows || []).filter((row) => {
            if (row && row.tipo_bloco === 'setup_principal') {
                return true;
            }

            return isVisibleComplementaryParadaVisible(row && row.parada_nomeParada);
        });
    }

    function getContentRowFor(row) {
        const next = row.nextElementSibling;
        if (next && next.classList && next.classList.contains('row-expand-content')) {
            return next;
        }

        const op = row.getAttribute('data-op') || '';
        if (!op) {
            return null;
        }

        return table.querySelector(`tr.row-expand-content[data-op="${CSS.escape(op)}"]`);
    }

    function setContentHtml(contentRow, html) {
        const container = contentRow.querySelector('.inline-detail');
        if (!container) {
                    contentRow.innerHTML = `<td colspan="14"><div class="inline-detail">${html}</div></td>`;
            return;
        }

        container.innerHTML = html;
    }

    function buildInlineHtml(row, data) {
        const prodAdmLabel = row.getAttribute('data-prod-adm-label') || '0';
        const prodNoiteLabel = row.getAttribute('data-prod-noite-label') || '0';

        const timelineRows = filterTimelineRows([...(data.principal || []), ...(data.apoio || [])]);
        const groupedRows = (data.paradas_agrupadas || []).filter((groupRow) => {
            if (groupRow && groupRow.is_principal) {
                return true;
            }

            return isVisibleComplementaryParadaVisible(groupRow && groupRow.parada_nomeParada);
        });

        const timelineHtml = timelineRows.length
            ? `
                <table class="inline-table">
                    <thead>
                        <tr>
                            <th>Início</th>
                            <th>Fim</th>
                            <th>Duração</th>
                            <th>Tipo</th>
                            <th>Evento</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${timelineRows.map((evt) => {
                            const inicio = formatDateTimeCompact(evt.inicio_evento || '--');
                            const fim = formatDateTimeCompact(evt.fim_evento || '--');
                            const minutes = Number(evt.duracao_evento_minutos ?? evt.setup_duracao_minutos ?? 0);
                            const duration = formatMinutesLabel(minutes);
                            const tipo = String(evt.parada_tipo_nome || (evt.tipo_bloco === 'setup_principal' ? 'Setup principal' : 'Parada')).trim();
                            const label = String(evt.parada_nomeParada || 'Sem classificação').trim();
                            return `
                                <tr>
                                    <td>${escapeHtml(inicio)}</td>
                                    <td>${escapeHtml(fim)}</td>
                                    <td>${escapeHtml(duration)}</td>
                                    <td>${escapeHtml(tipo)}</td>
                                    <td>${escapeHtml(label)}</td>
                                </tr>
                            `;
                        }).join('')}
                    </tbody>
                </table>
            `
            : '<div class="inline-detail__loading">Nenhum evento encontrado para esta OP/período.</div>';

        const paradasHtml = groupedRows.length
            ? `
                <table class="inline-table">
                    <thead>
                        <tr>
                            <th>Parada</th>
                            <th>Duração</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${groupedRows.map((groupRow) => {
                            const minutes = Number(groupRow.duracao_total_minutos || 0);
                            const duration = formatMinutesLabel(minutes);
                            const badgeClass = groupRow.is_principal ? 'badge-main' : 'badge-secondary';
                            const label = String(groupRow.parada_nomeParada || 'Sem classificação').trim();
                            return `
                                <tr>
                                    <td><span class="tag ${badgeClass}">${escapeHtml(label)}</span></td>
                                    <td>${escapeHtml(duration)}</td>
                                </tr>
                            `;
                        }).join('')}
                    </tbody>
                </table>
            `
            : '<div class="inline-detail__loading">Nenhuma parada encontrada para esta OP/período.</div>';

        return `
            <div class="inline-detail__grid">
                <div class="inline-detail__panel">
                    <h4>Timeline dos eventos (CODI)</h4>
                    <div class="body">${timelineHtml}</div>
                </div>
                <div>
                    <div class="inline-detail__panel inline-detail__panel--spaced">
                        <h4>Distribuição por turno</h4>
                        <div class="body">
                            <table class="inline-table">
                                <thead>
                                    <tr><th>Turno</th><th>Produção</th></tr>
                                </thead>
                                <tbody>
                                    <tr><td>ADM</td><td>${escapeHtml(prodAdmLabel)}</td></tr>
                                    <tr><td>Noite</td><td>${escapeHtml(prodNoiteLabel)}</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="inline-detail__panel">
                        <h4>Paradas da OP</h4>
                        <div class="body">${paradasHtml}</div>
                    </div>
                </div>
            </div>
        `;
    }

    async function fetchOpDetailFromRow(row) {
        const op = row.getAttribute('data-op') || '';
        if (!op) {
            throw new Error('OP inválida para o detalhe.');
        }

        if (cache.has(op)) {
            return cache.get(op);
        }

        const periodStart = row.getAttribute('data-period-start') || '';
        const periodEnd = row.getAttribute('data-period-end') || '';
        const setupPlanMin = row.getAttribute('data-setup-plan-min') || '';

        const url = new URL(window.location.href);
        url.searchParams.set('action', 'op_detail');
        url.searchParams.set('op', op);
        url.searchParams.set('period_start', periodStart);
        url.searchParams.set('period_end', periodEnd);
        url.searchParams.set('setup_plan_min', setupPlanMin);

        const response = await fetch(url.toString(), { headers: { 'Accept': 'application/json' } });
        const data = await response.json();
        if (!response.ok || !data.success) {
            throw new Error(data.message || 'Não foi possível carregar o detalhe.');
        }

        cache.set(op, data);
        return data;
    }

    function closeExpandedRow() {
        if (openRow) {
            openRow.classList.remove('is-expanded');
        }
        if (openContentRow) {
            openContentRow.classList.remove('is-open');
        }
        openRow = null;
        openContentRow = null;
    }

    function openExpandedRow(row, contentRow) {
        openRow = row;
        openContentRow = contentRow;
        row.classList.add('is-expanded');
        contentRow.classList.add('is-open');
    }

    table.addEventListener('click', (event) => {
        const target = event.target;
        const row = target && target.closest ? target.closest('tr.row-expandable[data-op]') : null;
        if (!row) {
            return;
        }

        // Mantem interacoes existentes (ex.: botao "Ver detalhe") como fallback.
        if (target && target.closest && target.closest('button, a, input, select, textarea, label')) {
            return;
        }

        const contentRow = getContentRowFor(row);
        if (!contentRow) {
            return;
        }

        if (openRow === row) {
            closeExpandedRow();
            return;
        }

        closeExpandedRow();
        openExpandedRow(row, contentRow);
        setContentHtml(contentRow, '<div class="inline-detail__loading">Carregando detalhe da OP...</div>');

        (async () => {
            try {
                const data = await fetchOpDetailFromRow(row);
                setContentHtml(contentRow, buildInlineHtml(row, data));
            } catch (error) {
                setContentHtml(contentRow, `<div class="inline-detail__error">${escapeHtml(error.message || 'Erro ao carregar')}</div>`);
            }
        })();
    });
})();
</script>
</body>
</html>
