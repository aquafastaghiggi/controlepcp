<?php

declare(strict_types=1);

require __DIR__ . '/src/bootstrap.php';

use App\Auth\Auth;
use App\Database\Connection;

Auth::startSession();

$pdo = Connection::get();

function h(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function formatMinutes(float $minutes): string
{
    $rounded = (int) round($minutes);
    $sign = $rounded < 0 ? '-' : '';
    $rounded = abs($rounded);
    $hours = intdiv($rounded, 60);
    $mins = $rounded % 60;

    if ($hours > 0) {
        return sprintf('%s%dh %02dm', $sign, $hours, $mins);
    }

    return sprintf('%s%dm', $sign, $mins);
}

function formatQty(float $value): string
{
    return number_format($value, 2, ',', '.');
}

function formatSignedMinutes(float $minutes): string
{
    $prefix = $minutes > 0 ? '+' : '';
    return $prefix . formatMinutes($minutes);
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

function lineLabel(?string $lineCode): string
{
    $lineCode = trim((string) $lineCode);
    return $lineCode !== '' ? $lineCode : 'S/linha';
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
    ORDER BY lin_codigo ASC
    "
)->fetchAll(PDO::FETCH_COLUMN);

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
        static fn(array $programacao): bool => lineLabel((string) ($programacao['lin_codigo'] ?? '')) === $selectedLine
    ));
}

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
            s.sch_data_inicio
        FROM sch_linhas s
        WHERE s.sch_programa_id = ?
        ORDER BY s.sch_data_inicio ASC, s.sch_sequencia ASC, s.sch_id ASC
        "
    );
    $scheduleStmt->execute([$selectedProgramId]);
    $scheduleRows = $scheduleStmt->fetchAll(PDO::FETCH_ASSOC);

    $pendingSetupMinutes = 0.0;

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
                'setup_eventos' => 0,
                'setup_realizado_eventos' => 0,
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
        $reportRows[$op]['setup_eventos'] += $pendingSetupMinutes > 0 ? 1 : 0;
        $pendingSetupMinutes = 0.0;
    }

    $summary['setup_pendente'] = $pendingSetupMinutes;

    $ops = array_values(array_unique(array_filter(array_map(static fn(array $row): string => trim((string) $row['op']), $reportRows), static fn(string $op): bool => $op !== '')));

    if (!empty($ops)) {
        $placeholders = implode(',', array_fill(0, count($ops), '?'));

        $periodStart = $selectedPeriodStartInput !== ''
            ? $selectedPeriodStartInput
            : (!empty($selectedProgram['data_inicio'])
                ? (new DateTimeImmutable((string) $selectedProgram['data_inicio']))->modify('-1 day')->format('Y-m-d')
                : date('Y-m-d'));
        $periodEnd = $selectedPeriodEndInput !== ''
            ? $selectedPeriodEndInput
            : (!empty($selectedProgram['data_fim'])
                ? (new DateTimeImmutable((string) $selectedProgram['data_fim']))->modify('+1 day')->format('Y-m-d')
                : date('Y-m-d'));

        if ($periodStart > $periodEnd) {
            [$periodStart, $periodEnd] = [$periodEnd, $periodStart];
        }

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
        $realizadoStmt->execute(array_merge([$periodStart, $periodEnd], $ops));
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
        $diagStmt->execute(array_merge([$periodStart, $periodEnd], $ops));
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
        $setupAbsA = abs((float) ($a['setup_row_diff'] ?? 0));
        $setupAbsB = abs((float) ($b['setup_row_diff'] ?? 0));
        if ($setupAbsA !== $setupAbsB) {
            return $setupAbsB <=> $setupAbsA;
        }

        $setupEventsA = (int) ($a['setup_realizado_eventos'] ?? 0);
        $setupEventsB = (int) ($b['setup_realizado_eventos'] ?? 0);
        if ($setupEventsA !== $setupEventsB) {
            return $setupEventsB <=> $setupEventsA;
        }

        $prodAbsA = abs((float) ($a['prod_row_diff'] ?? 0));
        $prodAbsB = abs((float) ($b['prod_row_diff'] ?? 0));
        if ($prodAbsA !== $prodAbsB) {
            return $prodAbsB <=> $prodAbsA;
        }

        return strnatcasecmp((string) ($a['op'] ?? ''), (string) ($b['op'] ?? ''));
    });

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
            gap: 14px;
            flex-wrap: wrap;
            margin-top: 18px;
            align-items: flex-end;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
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
            min-width: 320px;
            max-width: 100%;
            padding: 12px 14px;
            border-radius: 12px;
            border: 1px solid var(--border);
            background: #fff;
            color: var(--text);
            font-size: 14px;
        }

        input[type="date"] {
            min-width: 180px;
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

        @media (max-width: 1200px) {
            .summary-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .status-grid {
                grid-template-columns: repeat(3, minmax(0, 1fr));
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

            select,
            input[type="date"] {
                min-width: 100%;
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
                            <?= h((string) $linCodigo) ?>
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
                            $inicio = !empty($programacao['data_inicio']) ? date('d/m/Y', strtotime((string) $programacao['data_inicio'])) : 'S/data';
                            $fim = !empty($programacao['data_fim']) ? date('d/m/Y', strtotime((string) $programacao['data_fim'])) : 'S/data';
                        ?>
                        <option value="<?= $progId ?>" <?= $progId === $selectedProgramId ? 'selected' : '' ?>>
                            <?= h(sprintf('OP %s | Linha %s | %s a %s', $progNumero, $linha, $inicio, $fim)) ?>
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
                | Período base: <strong><?= h(date('d/m/Y', strtotime((string) $programStart))) ?></strong>
                até <strong><?= h(date('d/m/Y', strtotime((string) $programEnd))) ?></strong>
            <?php endif; ?>
            <?php if ($selectedLine !== '' || $selectedPeriodStartInput !== '' || $selectedPeriodEndInput !== ''): ?>
                <br>
                Filtros ativos:
                <?php if ($selectedLine !== ''): ?>
                    linha <strong><?= h($selectedLine) ?></strong>
                <?php endif; ?>
                <?php if ($selectedPeriodStartInput !== '' || $selectedPeriodEndInput !== ''): ?>
                    <?php if ($selectedLine !== ''): ?> | <?php endif; ?>
                    período <strong><?= h($selectedPeriodStartInput !== '' ? date('d/m/Y', strtotime($selectedPeriodStartInput)) : 'início livre') ?></strong>
                    até <strong><?= h($selectedPeriodEndInput !== '' ? date('d/m/Y', strtotime($selectedPeriodEndInput)) : 'fim livre') ?></strong>
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
            <div class="value"><?= h(formatQty($summary['producao_prevista'])) ?> / <?= h(formatQty($summary['producao_realizada'])) ?></div>
            <div class="sub">Desvio: <?= h(sprintf('%s%s', $prodDiff >= 0 ? '+' : '-', formatQty(abs($prodDiff)))) ?> | <?= h(number_format($prodPct, 1, ',', '.')) ?>%</div>
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
                <p>Ordenado por maior desvio absoluto de setup. O setup previsto vem de <code>sch_linhas.sch_duracao_minutos</code> e o realizado de <code>setup_duracao_minutos</code> para as paradas alvo.</p>
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
                                $setupClass = $setupRowDiff > 0.01 ? 'cell-danger' : ($setupRowDiff < -0.01 ? 'cell-positive' : 'cell-neutral');
                                if (!empty($row['is_critical'])) {
                                    $setupClass .= ' cell-critical';
                                }
                                $prodClass = $prodRowDiff > 0.01 ? 'cell-positive' : ($prodRowDiff < -0.01 ? 'cell-danger' : 'cell-neutral');
                                $setupStatus = getRowSetupStatus($row);
                                $setupRealClass = ((int) $row['setup_realizado_eventos'] > 0)
                                    ? 'cell-success'
                                    : ($setupPlan > 0.01 ? 'cell-warning' : 'cell-muted');
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
                                <td class="<?= h($setupRealClass) ?>"><?= h(formatMinutes($setupReal)) ?></td>
                                <td class="<?= $setupClass ?>"><?= h(sprintf('%s%s', $setupRowDiff >= 0 ? '+' : '-', formatMinutes(abs($setupRowDiff)))) ?></td>
                                <td><?= h(formatQty($prodPlan)) ?></td>
                                <td><?= h(formatQty($prodReal)) ?></td>
                                <td class="<?= $prodClass ?>"><?= h(sprintf('%s%s', $prodRowDiff >= 0 ? '+' : '-', formatQty(abs($prodRowDiff)))) ?></td>
                                <td>
                                    <span class="tag <?= h((string) ($row['tag_class'] ?? $setupStatus['tag_class'])) ?>" title="<?= h((string) ($row['tag_title'] ?? $setupStatus['title'])) ?>">
                                        <?= h((string) ($row['tag_label'] ?? $setupStatus['label'])) ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="10">
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
        <strong>Assunções desta primeira versão:</strong>
        o tempo de setup realizado é estimado pelo intervalo <code>inicio_evento</code> x <code>fim_evento</code>
        dos registros da tabela <code>realizado_2026_excel</code> que têm
        <code>parada_nomeParada</code> igual a <code>TROCA DE KIT</code> ou <code>TROCA DE LIQUIDO</code>.
        Para esta etapa, mantive a consolidação atual do import por <code>data_evento + ordem_op</code>.
        Se houver eventos mistos na mesma OP/dia, esta é uma aproximação inicial e a próxima evolução pode
        granular o import por evento do CODI.
        <br><br>
        <strong>Diagnóstico atual do sandbox:</strong>
        há <?= (int) $realizadoDiagnostics['rows'] ?> registros de realizado ligados às OPs desta programação,
        mas apenas <?= (int) $realizadoDiagnostics['classificados'] ?> estão classificados como setup alvo
        e <?= (int) $realizadoDiagnostics['ops_classificadas'] ?> OPs têm essa classificação.
        <?php if ($realizadoDiagnostics['classificados'] === 0): ?>
            No estado atual, o setup realizado permanece em 0 porque a coluna
            <code>parada_nomeParada</code> ainda não está populada com os nomes alvo no import.
        <?php endif; ?>
        <?php if ((float) $summary['setup_pendente'] > 0): ?>
            Há também <?= h(formatMinutes((float) $summary['setup_pendente'])) ?> de setup previsto sem OP de produção seguinte
            nesta programação; esse valor fica separado para não simular uma associação incorreta.
        <?php endif; ?>
    </div>
</div>
</body>
</html>
