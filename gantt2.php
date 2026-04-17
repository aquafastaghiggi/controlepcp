<?php
// encoding: UTF-8
/**
 * Gráfico de Gantt PCP - Versão 2 (Design Moderno)
 * Fiel ao gantt.php em dados e lógica, redesenhado visualmente.
 */

declare(strict_types=1);

require __DIR__ . '/src/bootstrap.php';

use App\Auth\Auth;
use App\Repository\ProgramacaoRepository;
use App\Database\Connection;

Auth::startSession();
$repo = new ProgramacaoRepository();
$pdo = Connection::get();

// ── Paleta visual centralizada (idêntica ao gantt.php) ──────────────────────
$pcpPalette = [
    'prod'                       => '#3b82f6',
    'setup'                      => '#f59e0b',
    'realizado'                  => '#ef4444',
    'realizado_neutral'          => '#64748b',
    'status_ok'                  => '#10b981',
    'status_warn'                => '#f59e0b',
    'status_bad'                 => '#ef4444',
    'status_none'                => '#6b7280',
    'neutral_light'              => '#d1d5db',
    'realizado_border'           => 'rgba(185,28,28,0.7)',
    'realizado_neutral_border'   => 'rgba(51,65,85,0.55)',
    'setup_border'               => 'rgba(154, 52, 18, 0.35)',
    'overlay_text'               => 'rgba(255,255,255,0.92)',
    'overlay_muted'              => 'rgba(255,255,255,0.75)',
    'overlay_divider'            => 'rgba(255,255,255,0.55)',
];

// ── Programações (idêntico ao gantt.php) ────────────────────────────────────
$programacoes = $repo->getAllProgramacoes(100, 0);

$ganttNormalizeLineLabel = static function (?string $value): string {
    $raw = trim((string) $value);
    if ($raw === '') return 'S/Linha';
    if (preg_match('/^(?:linha|ln)\s*0*(\d+)$/iu', $raw, $match) === 1)
        return 'Linha ' . str_pad((string)(int)$match[1], 2, '0', STR_PAD_LEFT);
    if (preg_match('/^0*(\d+)$/u', $raw, $match) === 1)
        return 'Linha ' . str_pad((string)(int)$match[1], 2, '0', STR_PAD_LEFT);
    return $raw;
};

$ganttExtractLineSortInfo = static function (array $prg) use ($ganttNormalizeLineLabel): array {
    $candidates = [(string)($prg['linha_excel_dominante'] ?? ''), (string)($prg['lin_codigo'] ?? '')];
    foreach ($candidates as $candidate) {
        $raw = trim($candidate);
        if ($raw === '') continue;
        if (preg_match('/^(?:linha|ln)\s*0*(\d+)$/iu', $raw, $match) === 1 || preg_match('/^0*(\d+)$/u', $raw, $match) === 1)
            return ['group' => 0, 'numeric' => (int)$match[1], 'label' => $ganttNormalizeLineLabel($raw), 'raw' => $raw];
        return ['group' => 1, 'numeric' => null, 'label' => $ganttNormalizeLineLabel($raw), 'raw' => $raw];
    }
    return ['group' => 2, 'numeric' => null, 'label' => 'S/Linha', 'raw' => 'S/Linha'];
};

usort($programacoes, static function (array $a, array $b) use ($ganttExtractLineSortInfo): int {
    $lineA = $ganttExtractLineSortInfo($a);
    $lineB = $ganttExtractLineSortInfo($b);
    if ($lineA['group'] !== $lineB['group']) return $lineA['group'] <=> $lineB['group'];
    if ($lineA['group'] === 0 && $lineA['numeric'] !== $lineB['numeric']) return $lineA['numeric'] <=> $lineB['numeric'];
    if ($lineA['label'] !== $lineB['label']) return strcasecmp($lineA['label'], $lineB['label']);
    $inicioA = (string)($a['inicio_base_cronograma'] ?? '');
    $inicioB = (string)($b['inicio_base_cronograma'] ?? '');
    if ($inicioA !== $inicioB) return strcmp($inicioA, $inicioB);
    $progA = (string)($a['programacao_criada_em'] ?? '');
    $progB = (string)($b['programacao_criada_em'] ?? '');
    if ($progA !== $progB) return strcmp($progA, $progB);
    return ((int)($a['prg_id'] ?? 0)) <=> ((int)($b['prg_id'] ?? 0));
});

// ── Seleção de programação + filtros (idêntico ao gantt.php) ─────────────────
$selectedProgramId  = (int)($_GET['programacao_id'] ?? $_GET['id'] ?? 0);
$periodStartInput   = isset($_GET['data_inicio']) ? trim((string)$_GET['data_inicio']) : '';
$periodEndInput     = isset($_GET['data_fim'])    ? trim((string)$_GET['data_fim'])    : '';
$hasValidPeriodFilter = preg_match('/^\d{4}-\d{2}-\d{2}$/', $periodStartInput) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $periodEndInput);
$schedule           = [];
$programacaoInfo    = null;

if ($selectedProgramId <= 0 && !empty($programacoes))
    $selectedProgramId = (int)$programacoes[0]['prg_id'];

if ($selectedProgramId > 0) {
    $programacaoInfo = $repo->getProgramacaoById($selectedProgramId);
    if ($programacaoInfo === null && !empty($programacoes)) {
        $selectedProgramId = (int)$programacoes[0]['prg_id'];
        $programacaoInfo   = $programacoes[0];
    }
}

if ($selectedProgramId > 0) {
    $stmtSchedule = $pdo->prepare("
        SELECT s.*
        FROM sch_linhas s
        WHERE s.sch_programa_id = :programId
        ORDER BY s.sch_data_inicio ASC, s.sch_sequencia ASC, s.sch_id ASC
    ");
    $stmtSchedule->execute(['programId' => $selectedProgramId]);
    $schedule = $stmtSchedule->fetchAll(PDO::FETCH_ASSOC);
}

if ($hasValidPeriodFilter && strtotime($periodStartInput) !== false && strtotime($periodEndInput) !== false) {
    if (strtotime($periodStartInput) > strtotime($periodEndInput))
        [$periodStartInput, $periodEndInput] = [$periodEndInput, $periodStartInput];
}

$screenPeriodStart = '';
$screenPeriodEnd   = '';
if ($hasValidPeriodFilter) {
    $screenPeriodStart = $periodStartInput;
    $screenPeriodEnd   = $periodEndInput;
} else {
    $screenStartCandidates = [];
    $screenEndCandidates   = [];
    foreach ($schedule as $schRow) {
        $s = trim((string)($schRow['sch_inicio_producao'] ?? ''));
        $e = trim((string)($schRow['sch_fim_producao']   ?? ''));
        if ($s !== '') $screenStartCandidates[] = date('Y-m-d', strtotime($s));
        if ($e !== '') $screenEndCandidates[]   = date('Y-m-d', strtotime($e));
    }
    $screenPeriodStart = !empty($screenStartCandidates) ? min($screenStartCandidates) : date('Y-m-d');
    $screenPeriodEnd   = !empty($screenEndCandidates)   ? max($screenEndCandidates)   : date('Y-m-d');
}
if ($screenPeriodStart > $screenPeriodEnd)
    [$screenPeriodStart, $screenPeriodEnd] = [$screenPeriodEnd, $screenPeriodStart];

// ── Bucket OPs (idêntico ao gantt.php) ──────────────────────────────────────
$opBuckets = [];
if (!empty($schedule)) {
    $programIds = array_values(array_unique(array_map(static fn(array $row): int => (int)($row['sch_programa_id'] ?? 0), $schedule)));
    $programIds = array_values(array_filter($programIds, static fn(int $id): bool => $id > 0));
    if (!empty($programIds)) {
        $placeholders = implode(',', array_fill(0, count($programIds), '?'));
        $stmtOp = $pdo->prepare(
            "SELECT prg_programa_id, prg_sku, prg_quantidade, prg_sequencia, prg_id_item, prg_itens_op
             FROM prg_itens
             WHERE prg_programa_id IN ({$placeholders})
             ORDER BY prg_programa_id ASC, prg_sku ASC, prg_sequencia ASC"
        );
        $stmtOp->execute($programIds);
        $opRows = $stmtOp->fetchAll(PDO::FETCH_ASSOC);
        foreach ($opRows as $opRow) {
            $progId = (int)($opRow['prg_programa_id'] ?? 0);
            $sku    = trim((string)($opRow['prg_sku'] ?? ''));
            if ($progId <= 0 || $sku === '') continue;
            $opBuckets[$progId][$sku][] = $opRow;
        }
    }
}

// ── Resolver OP por sch_id ───────────────────────────────────────────────────
$itemsBySku   = [];
$assignedOps  = [];
foreach ($schedule as $row) {
    $progId = (int)($row['sch_programa_id'] ?? 0);
    $sku    = trim((string)($row['sch_sku'] ?? ''));
    if ($progId <= 0 || $sku === '' || strtolower(trim($row['sch_tipo'] ?? '')) === 'setup') continue;
    if (!isset($itemsBySku[$sku]) && isset($opBuckets[$progId][$sku]))
        $itemsBySku[$sku] = $opBuckets[$progId][$sku];
}
foreach ($schedule as $row) {
    $schId  = (int)($row['sch_id'] ?? 0);
    $sku    = trim((string)($row['sch_sku'] ?? ''));
    $isSetup = strtolower(trim($row['sch_tipo'] ?? '')) === 'setup';
    if ($isSetup || $schId <= 0 || $sku === '') continue;
    $assignedOps[$schId] = 'S/OP';
    if (!isset($itemsBySku[$sku])) continue;
    foreach ($itemsBySku[$sku] as $idx => &$item) {
        if (!empty($item['used'])) continue;
        $op = trim((string)($item['prg_itens_op'] ?? ''));
        if ($op === '') $op = 'S/OP';
        $item['used'] = true;
        $assignedOps[$schId] = $op;
        break;
    }
    unset($item);
}

// ── Realizado (idêntico ao gantt.php) ────────────────────────────────────────
$realizadoMap = [];
if (!empty($schedule)) {
    $realTable = 'realizado_2026_excel';
    $realCols  = [];
    try {
        $realCols = $pdo->query("SHOW COLUMNS FROM `{$realTable}`")->fetchAll(PDO::FETCH_COLUMN, 0);
    } catch (Throwable $e) { $realCols = []; }

    $pickFirstExisting = function(array $cols, array $candidates): ?string {
        $set = array_fill_keys($cols, true);
        foreach ($candidates as $c) if (isset($set[$c])) return $c;
        return null;
    };

    $colQty    = $pickFirstExisting($realCols, ['quantidade','qtd','qtde','quantidade_produzida']);
    $colOp     = $pickFirstExisting($realCols, ['ordem_op','op','ordem']);
    $colDate   = $pickFirstExisting($realCols, ['data_evento','data','data_apontamento','data_hora']);
    $colInicio = $pickFirstExisting($realCols, ['inicio_evento','inicio','data_inicio','inicio_apontamento','dt_inicio','inicio_real']);
    $colFim    = $pickFirstExisting($realCols, ['fim_evento','fim','data_fim','fim_apontamento','dt_fim','fim_real']);

    $opsPeriodos = [];
    foreach ($schedule as $schRow) {
        if (strtolower(trim($schRow['sch_tipo'] ?? '')) === 'setup') continue;
        if (empty($schRow['sch_sku']) || empty($schRow['sch_inicio_producao'])) continue;
        $opItem = $assignedOps[(int)($schRow['sch_id'] ?? 0)] ?? 'S/OP';
        if ($opItem === 'S/OP') continue;
        $itemStart  = date('Y-m-d', strtotime((string)$schRow['sch_inicio_producao']));
        $itemEnd    = date('Y-m-d', strtotime((string)$schRow['sch_fim_producao']));
        $queryStart = max($screenPeriodStart, $itemStart);
        $queryEnd   = $screenPeriodEnd;
        if ($queryStart > $queryEnd) continue;
        $opsPeriodos[] = [
            'op'     => $opItem,
            'inicio' => $queryStart,
            'fim'    => $queryEnd,
            'chave'  => $opItem . '|' . $schRow['sch_inicio_producao'],
        ];
    }

    if ($colQty && $colOp && $colDate) {
        $exprInicio = $colInicio ? "MIN(`{$colInicio}`)" : "MIN(`{$colDate}`)";
        $exprFim    = $colFim    ? "MAX(`{$colFim}`)"    : "MAX(`{$colDate}`)";
        $stmtReal   = $pdo->prepare("
            SELECT SUM(`{$colQty}`) as total, {$exprInicio} as inicio_real, {$exprFim} as fim_real
            FROM `{$realTable}`
            WHERE `{$colOp}` = ? AND `{$colDate}` >= ? AND `{$colDate}` <= ?
        ");
        foreach ($opsPeriodos as $item) {
            $stmtReal->execute([$item['op'], $item['inicio'], $item['fim']]);
            $res = $stmtReal->fetch(PDO::FETCH_ASSOC);
            $realizadoMap[$item['chave']] = [
                'total'       => (float)($res['total'] ?? 0),
                'inicio_real' => $res['inicio_real'] ?? null,
                'fim_real'    => $res['fim_real']    ?? null,
            ];
        }
    }
}

// ── Montar tasks (idêntico ao gantt.php) ─────────────────────────────────────
$tasks = [];
if (!empty($schedule)) {
    foreach ($schedule as $row) {
        $start = $row['sch_inicio_producao'];
        $end   = $row['sch_fim_producao'];
        if ($start && $end) {
            $isSetup  = strtolower(trim($row['sch_tipo'] ?? '')) === 'setup';
            $op       = $isSetup ? 'S/OP' : ($assignedOps[(int)($row['sch_id'] ?? 0)] ?? 'S/OP');
            $chave    = $op . '|' . $row['sch_inicio_producao'];
            $realData = $realizadoMap[$chave] ?? ['total' => 0.0, 'inicio_real' => null, 'fim_real' => null];
            $quantReal = (float)($realData['total'] ?? 0.0);
            $quantPrev = (float)($row['sch_quantidade'] ?? 0);
            $pct       = $quantPrev > 0 ? ($quantReal / $quantPrev) * 100 : 0;
            $cor       = $isSetup ? $pcpPalette['setup'] : $pcpPalette['prod'];
            $taskId    = (int)$row['sch_id'];
            $tasks[] = [
                'id'                    => $taskId,
                'text'                  => ($isSetup ? '• SETUP' : 'OP ' . $op . "\n" . trim($row['sch_descricao'] ?? '-')),
                'descricao_produto'     => trim($row['sch_descricao'] ?? '-'),
                'start_date'            => date('d-m-Y H:i', strtotime($start)),
                'end_date'              => date('d-m-Y H:i', strtotime($end)),
                'color'                 => $cor,
                'progress'              => 1,
                'open'                  => true,
                'sku'                   => $row['sch_sku'] ?: '-',
                'tipo'                  => $row['sch_tipo'],
                'op'                    => $op,
                'memoria_calculo'       => (string)($row['sch_memoria_calculo'] ?? ''),
                'quantidade_prevista'   => $quantPrev,
                'quantidade_realizada'  => $quantReal,
                'realizado_inicio'      => $realData['inicio_real'],
                'realizado_fim'         => $realData['fim_real'],
                'percentual_cumprimento' => $pct,
            ];
            if (!$isSetup && !empty($realData['inicio_real']) && !empty($realData['fim_real'])) {
                $tasks[] = [
                    'id'                    => 'real-' . $taskId,
                    'text'                  => 'Realizado',
                    'descricao_produto'     => trim($row['sch_descricao'] ?? '-'),
                    'start_date'            => date('d-m-Y H:i', strtotime($realData['inicio_real'])),
                    'end_date'              => date('d-m-Y H:i', strtotime($realData['fim_real'])),
                    'color'                 => $pcpPalette['realizado'],
                    'progress'              => 1,
                    'open'                  => true,
                    'sku'                   => $row['sch_sku'] ?: '-',
                    'tipo'                  => 'realizado',
                    'op'                    => $op,
                    'memoria_calculo'       => (string)($row['sch_memoria_calculo'] ?? ''),
                    'quantidade_prevista'   => $quantPrev,
                    'quantidade_realizada'  => $quantReal,
                    'realizado_inicio'      => $realData['inicio_real'],
                    'realizado_fim'         => $realData['fim_real'],
                    'percentual_cumprimento' => $pct,
                    'hide_real_bar'         => false,
                ];
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Gantt PCP — Sequenciamento</title>
<link rel="stylesheet" href="assets/css/app.css">
<link rel="stylesheet" href="assets/css/theme.css">
<!-- DHTMLX Gantt 9.0 — mesma versão do gantt.php -->
<link rel="stylesheet" href="https://cdn.dhtmlx.com/gantt/9.0/dhtmlxgantt.css">
<style>
/* ══════════════════════════════════════════════════════
   DESIGN TOKENS
══════════════════════════════════════════════════════ */
:root {
    --bg:            #f0f4fa;
    --surface:       #ffffff;
    --border:        #dde4f0;
    --text:          #0f172a;
    --muted:         #64748b;
    --accent:        #2563eb;
    --accent-soft:   #eff6ff;
    --radius-card:   18px;
    --shadow-card:   0 4px 24px rgba(15,23,42,.08);

    /* cores de tarefa (espelham $pcpPalette PHP) */
    --pcp-color-prod:                   <?= htmlspecialchars($pcpPalette['prod'],      ENT_QUOTES) ?>;
    --pcp-color-setup:                  <?= htmlspecialchars($pcpPalette['setup'],     ENT_QUOTES) ?>;
    --pcp-color-realizado:              <?= htmlspecialchars($pcpPalette['realizado'], ENT_QUOTES) ?>;
    --pcp-color-realizado-neutral:      <?= htmlspecialchars($pcpPalette['realizado_neutral'], ENT_QUOTES) ?>;
    --pcp-status-ok:                    <?= htmlspecialchars($pcpPalette['status_ok'],  ENT_QUOTES) ?>;
    --pcp-status-warn:                  <?= htmlspecialchars($pcpPalette['status_warn'],ENT_QUOTES) ?>;
    --pcp-status-bad:                   <?= htmlspecialchars($pcpPalette['status_bad'], ENT_QUOTES) ?>;
    --pcp-status-none:                  <?= htmlspecialchars($pcpPalette['status_none'],ENT_QUOTES) ?>;
    --pcp-neutral-light:                <?= htmlspecialchars($pcpPalette['neutral_light'],ENT_QUOTES) ?>;
    --pcp-color-realizado-border:       <?= htmlspecialchars($pcpPalette['realizado_border'],ENT_QUOTES) ?>;
    --pcp-color-realizado-neutral-border: <?= htmlspecialchars($pcpPalette['realizado_neutral_border'],ENT_QUOTES) ?>;
    --pcp-color-setup-border:           <?= htmlspecialchars($pcpPalette['setup_border'],ENT_QUOTES) ?>;
    --pcp-overlay-text:                 <?= htmlspecialchars($pcpPalette['overlay_text'],ENT_QUOTES) ?>;
    --pcp-overlay-muted:                <?= htmlspecialchars($pcpPalette['overlay_muted'],ENT_QUOTES) ?>;

    /* Timeline grid */
    --pcp-timeline-bg:            #f8faff;
    --pcp-timeline-zebra-6h:      rgba(15,23,42,.016);
    --pcp-timeline-gridline:      rgba(203,213,225,.55);
    --pcp-timeline-rowline:       rgba(203,213,225,.65);
    --pcp-timeline-dayline:       rgba(148,163,184,.95);
    --pcp-timeline-header-zebra:  rgba(15,23,42,.010);

    --setup-color: var(--pcp-color-setup);
    --prod-color:  var(--pcp-color-prod);
}

/* ── Reset ── */
*, *::before, *::after { box-sizing: border-box; }

body {
    margin: 0;
    padding: 16px 18px 18px;
    height: 100vh;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    font-family: 'Segoe UI', system-ui, Arial, sans-serif;
    background: radial-gradient(ellipse at top left, rgba(37,99,235,.10) 0%, transparent 55%),
                linear-gradient(180deg, #f4f8ff 0%, var(--bg) 100%);
    color: var(--text);
}

/* ══════════════════════════════════════════════════════
   HEADER CARD
══════════════════════════════════════════════════════ */
.header-card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius-card);
    box-shadow: var(--shadow-card);
    padding: 16px 20px 14px;
    margin-bottom: 14px;
    flex-shrink: 0;
}

.top-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 14px;
    flex-wrap: wrap;
}

.brand {
    display: flex;
    align-items: center;
    gap: 10px;
}

.brand-icon {
    width: 38px;
    height: 38px;
    background: var(--accent);
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.brand-icon svg { display: block; }

.brand-text { line-height: 1.15; }

.eyebrow {
    font-size: 10px;
    font-weight: 700;
    color: var(--accent);
    text-transform: uppercase;
    letter-spacing: .1em;
    margin-bottom: 1px;
}

h1 {
    margin: 0;
    font-size: 17px;
    font-weight: 800;
    color: var(--text);
    line-height: 1.15;
}

/* ── Botões do header ── */
.actions {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    align-items: center;
}

.btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 9px 15px;
    border-radius: 10px;
    border: 1px solid transparent;
    font-size: 13px;
    font-weight: 700;
    cursor: pointer;
    text-decoration: none;
    transition: transform .12s ease, box-shadow .12s ease, opacity .12s ease;
    white-space: nowrap;
}
.btn:hover { transform: translateY(-1px); box-shadow: 0 6px 16px rgba(15,23,42,.12); }
.btn:active { transform: translateY(0); }
.btn:disabled { opacity: .55; cursor: not-allowed; transform: none; box-shadow: none; }

.btn-primary  { background: var(--accent); color: #fff; }
.btn-green    { background: #059669; color: #fff; }
.btn-soft     { background: var(--accent-soft); color: var(--accent); border-color: #bfdbfe; }
.btn-ghost    { background: #f1f5f9; color: var(--text); border-color: var(--border); }

/* ── Controls row ── */
.controls-row {
    display: flex;
    gap: 10px;
    align-items: center;
    flex-wrap: wrap;
    margin-top: 12px;
}

.controls-label {
    font-size: 12px;
    font-weight: 700;
    color: var(--muted);
    text-transform: uppercase;
    letter-spacing: .07em;
    white-space: nowrap;
}

.select-wrap {
    position: relative;
    flex: 1 1 360px;
    min-width: 260px;
    max-width: 600px;
}

.select-wrap select {
    width: 100%;
    padding: 10px 36px 10px 14px;
    border-radius: 10px;
    border: 1px solid var(--border);
    background: #fff;
    color: var(--text);
    font-size: 13px;
    font-weight: 600;
    appearance: none;
    cursor: pointer;
    transition: border-color .15s ease, box-shadow .15s ease;
}
.select-wrap select:focus { outline: none; border-color: var(--accent); box-shadow: 0 0 0 3px rgba(37,99,235,.12); }
.select-wrap::after {
    content: '';
    position: absolute;
    right: 13px;
    top: 50%;
    transform: translateY(-50%);
    width: 0; height: 0;
    border-left: 4px solid transparent;
    border-right: 4px solid transparent;
    border-top: 5px solid var(--muted);
    pointer-events: none;
}

.meta-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 6px 10px;
    border-radius: 8px;
    background: var(--accent-soft);
    border: 1px solid #bfdbfe;
    color: var(--accent);
    font-size: 12px;
    font-weight: 700;
    white-space: nowrap;
}

/* ══════════════════════════════════════════════════════
   GANTT CONTAINER
══════════════════════════════════════════════════════ */
#gantt_here {
    flex: 1;
    border-radius: var(--radius-card);
    border: 1px solid var(--border);
    background: var(--surface);
    box-shadow: var(--shadow-card);
    position: relative;
    min-height: 400px;
    overflow: hidden;
}

/* ══════════════════════════════════════════════════════
   LEGENDA
══════════════════════════════════════════════════════ */
.legend-bar {
    flex-shrink: 0;
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 20px;
    padding: 10px 18px;
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 14px;
    margin-top: 12px;
    box-shadow: 0 2px 10px rgba(15,23,42,.05);
}

.legend-item {
    display: flex;
    align-items: center;
    gap: 7px;
    font-size: 12px;
    font-weight: 600;
    color: var(--muted);
}

.legend-dot {
    width: 13px; height: 13px;
    border-radius: 4px;
    flex-shrink: 0;
}

.legend-hint {
    margin-left: auto;
    font-size: 11px;
    color: #94a3b8;
    font-style: italic;
}

/* ══════════════════════════════════════════════════════
   DHTMLX OVERRIDES — visual moderno
══════════════════════════════════════════════════════ */

/* Grade: fundo e linhas */
.gantt_task_bg { background: var(--pcp-timeline-bg) !important; }
.gantt_task_bg .gantt_task_row { box-shadow: inset 0 -1px 0 var(--pcp-timeline-rowline); }

/* Células de escala */
.gantt_scale_cell {
    font-weight: 700;
    color: #334155;
    border-right: 1px solid var(--pcp-timeline-gridline);
    font-size: 11px;
    padding: 0 6px;
    display: flex;
    align-items: center;
}
.gantt_scale_cell.pcp-scale-6h { font-size: 10px; color: var(--muted); }
.gantt_scale_cell.pcp-scale-6h--alt { background: var(--pcp-timeline-header-zebra); }
.gantt_scale_cell.pcp-scale-6h--day-start,
.gantt_task_cell.pcp-timeline-6h--day-start { border-right-color: var(--pcp-timeline-dayline); }
.gantt_task_cell.pcp-timeline-6h { border-right: 1px solid var(--pcp-timeline-gridline); }
.gantt_task_cell.pcp-timeline-6h--alt { background: var(--pcp-timeline-zebra-6h); }

/* Cabeçalho do grid */
.gantt_grid_head_cell {
    font-size: 11px;
    font-weight: 700;
    color: var(--muted);
    text-transform: uppercase;
    letter-spacing: .06em;
    background: #f8faff !important;
}

/* Células do grid: 2 linhas */
.gantt_grid_data .gantt_cell,
.gantt_grid_data .gantt_tree_content {
    white-space: normal !important;
    line-height: 1.2;
}

/* Barra principal */
.gantt_task_line { border-radius: 6px; border: none; padding: 0; }

.gantt_task_bar {
    height: 14px !important;
    min-height: 14px !important;
    border-radius: 7px !important;
    padding: 0 8px !important;
    margin: 0 !important;
    box-shadow: none !important;
    display: flex !important;
    align-items: center !important;
    justify-content: flex-start !important;
    overflow: visible !important;
}

.gantt_task_line.pcp-task-setup .gantt_task_bar {
    border: 1px solid var(--pcp-color-setup-border) !important;
    box-shadow: 0 0 0 1px rgba(255,255,255,.22) inset !important;
}

/* Setup pequeno */
.gantt_task_line.pcp-task-setup-short { z-index: 9; overflow: visible !important; }
.gantt_task_line.pcp-task-setup-short:hover { min-width: 18px !important; z-index: 16; }
.gantt_task_line.pcp-task-setup-short:hover .gantt_task_bar {
    width: 100% !important; min-width: 18px !important; padding: 0 4px !important;
    justify-content: center !important;
    box-shadow: 0 0 0 1px rgba(255,255,255,.28) inset, 0 2px 8px rgba(154,52,18,.18) !important;
}
.gantt_task_line.pcp-task-setup-short .gantt_task_content { font-size: 0 !important; padding: 0 !important; }
.gantt_task_line.pcp-task-setup-short .gantt_task_content::before { content: "•"; font-size: 9px; line-height: 1; }

/* Sub-barra realizado */
.pcp-realized-subbar {
    position: absolute; left: 5px; right: 5px; bottom: 5px; height: 5px;
    border-radius: 999px;
    background: rgba(255,255,255,.92);
    border: 1px solid rgba(15,23,42,.12);
    overflow: hidden; pointer-events: none; z-index: 8;
}
.pcp-realized-subbar-fill {
    height: 100%; border-radius: 999px; min-width: 0;
    transition: width .12s ease;
    box-shadow: 0 0 0 1px rgba(255,255,255,.20) inset;
}

/* Marcador de status */
.pcp-status-marker {
    position: absolute; top: 2px; bottom: 2px; left: 2px; width: 3px;
    border-radius: 2px; pointer-events: none; z-index: 9;
    box-shadow: 0 0 0 1px rgba(255,255,255,.18) inset;
}

/* Overlay realizado */
.pcp-realized-overlay {
    position: absolute; top: 50%; left: 8px;
    transform: translateY(-50%);
    font-size: 8px; font-weight: 600;
    color: var(--pcp-overlay-text);
    display: flex; align-items: center; gap: 5px;
    padding: 0 4px; border-radius: 2px;
    white-space: nowrap; pointer-events: none; z-index: 10; opacity: .55;
}
.gantt_task_line:hover .pcp-realized-overlay { opacity: .85; }

/* Conteúdo da barra */
.gantt_task_content {
    font-size: 9px; font-weight: 600; line-height: 1;
    display: inline-block; vertical-align: middle; padding: 0 4px;
}

/* Célula de texto do grid */
.pcp-grid-op   { font-weight: 700; color: #0f172a; font-size: 11px; line-height: 1.1; margin-bottom: 2px; }
.pcp-grid-prod { font-size: 9px; font-weight: 500; color: var(--muted); line-height: 1.1; }
.pcp-grid-setup {
    width: 100%; text-align: right; padding-right: 10px;
    font-weight: 900; color: var(--pcp-color-setup); line-height: 29px;
    font-size: 10px; text-transform: uppercase; letter-spacing: .05em;
}

/* Linha realizado no grid */
.pcp-grid-real-compare-line { line-height: 1.1; white-space: nowrap; }
.pcp-grid-real-inline-label { font-size: 10px; font-weight: 800; color: #334155; display: inline; margin-right: 4px; }
.pcp-grid-real             { font-size: 9px; font-weight: 600; color: #475569; display: inline; }
.pcp-grid-real--empty      { color: #94a3b8; }

/* Badge previsto/realizado */
.pcp-realizado-cell {
    display: flex; align-items: center; justify-content: center;
    gap: 8px; height: 100%; width: 100%;
}
.pcp-realizado-cell--setup { display: flex; width: 100%; height: 100%; }
.pcp-realizado-setup { color: #94a3b8; font-weight: 800; white-space: nowrap; font-size: 10px; }
.pcp-realizado-sep   { color: #94a3b8; font-weight: 700; }
.pcp-realizado-badge {
    color: #fff; padding: 2px 6px; border-radius: 5px;
    font-size: 10px; font-weight: 800; display: inline-block;
    text-align: center; line-height: 1.3; min-width: 50px;
}
.pcp-realizado-badge--prev { background: var(--pcp-color-prod); }
.pcp-realizado-badge--real { min-width: 84px; }

/* Barra realizado */
.gantt_task_line.pcp-task-realizado {
    background: transparent !important; border: none !important;
    box-shadow: none !important; padding: 0 !important;
    display: flex !important; align-items: center !important;
    height: 29px !important; min-height: 29px !important;
    border-radius: 4px !important;
}
.gantt_task_line.pcp-task-realizado .gantt_task_bar {
    height: 10px !important; min-height: 10px !important;
    border-radius: 5px !important; padding: 0 8px !important;
    box-shadow: none !important; box-sizing: border-box !important;
    display: flex !important; align-items: center !important;
    background: var(--pcp-color-realizado-neutral) !important;
    border: 1px solid var(--pcp-color-realizado-neutral-border) !important;
    overflow: visible !important; transform: translateY(-1px) !important;
}
.gantt_task_line.pcp-task-realizado .gantt_task_content { display: none !important; }

/* Scrollbars */
.gantt_hor_scroll { background: #f8f9fa !important; display: block !important; visibility: visible !important; }
.gantt_ver_scroll { background: #f8f9fa !important; display: block !important; visibility: visible !important; }
.gantt_scrollbar  { display: block !important; visibility: visible !important; }

/* Centralizar célula de previsto|realizado */
.gantt_grid_data .gantt_cell[data-column-name="realizado"] { display: flex; align-items: center; }

/* Tooltip */
.gantt_tooltip {
    max-width: 340px; white-space: normal; line-height: 1.25;
    padding: 10px 12px; font-size: 12px;
    border-radius: 12px !important;
    box-shadow: 0 8px 32px rgba(15,23,42,.18) !important;
    border: 1px solid rgba(255,255,255,.12) !important;
}
.pcp-tooltip-title  { font-size: 13px; font-weight: 800; margin-bottom: 7px; }
.pcp-tooltip-grid   { display: grid; grid-template-columns: auto 1fr; gap: 3px 9px; align-items: start; }
.pcp-tooltip-label  { font-weight: 700; white-space: nowrap; }
.pcp-tooltip-memory { margin-top: 7px; padding-top: 6px; border-top: 1px solid rgba(255,255,255,.18); }
.pcp-tooltip-memory-label { font-weight: 700; margin-bottom: 3px; }
.pcp-tooltip-memory-body  { line-height: 1.3; }

/* ══════════════════════════════════════════════════════
   SYNC MODAL (idêntico ao gantt.php)
══════════════════════════════════════════════════════ */
.sync-modal-overlay {
    position: fixed; inset: 0;
    background: rgba(15,23,42,.62);
    display: none; align-items: center; justify-content: center;
    z-index: 9999; backdrop-filter: blur(3px);
}
.sync-modal-overlay.is-open { display: flex; }
.sync-modal {
    width: min(560px, calc(100vw - 24px));
    background: #fff; border-radius: 18px;
    box-shadow: 0 24px 60px rgba(15,23,42,.28); overflow: hidden;
}
.sync-modal__head  { padding: 18px 20px 12px; border-bottom: 1px solid #e5e7eb; }
.sync-modal__title { margin: 0; font-size: 18px; font-weight: 800; color: var(--text); }
.sync-modal__body  { padding: 18px 20px 16px; color: #1f2937; }
.sync-modal__message { margin: 0 0 14px; line-height: 1.45; }
.sync-modal__note  { margin-top: 10px; font-size: 12px; color: #6b7280; }
.sync-progress { height: 10px; border-radius: 999px; background: #e5e7eb; overflow: hidden; position: relative; }
.sync-progress__bar {
    width: 18%; height: 100%;
    background: linear-gradient(90deg, var(--accent), #60a5fa);
    border-radius: inherit; transition: width 180ms ease;
}
.sync-progress__bar.is-indeterminate { position: relative; overflow: hidden; }
.sync-progress__bar.is-indeterminate::after {
    content: ''; position: absolute; inset: 0;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,.4), transparent);
    animation: syncShimmer 1.1s infinite;
}
@keyframes syncShimmer { from { transform: translateX(-120%); } to { transform: translateX(120%); } }
.sync-modal__status { margin-top: 12px; font-size: 13px; color: #374151; min-height: 18px; }
.sync-stage-counter { margin-top: 12px; font-size: 12px; font-weight: 700; color: var(--text); }
.sync-stage-list { display: grid; grid-template-columns: repeat(auto-fit, minmax(120px,1fr)); gap: 8px; margin-top: 10px; }
.sync-stage-item { border: 1px solid #dbe3ea; border-radius: 10px; padding: 8px 10px; background: #f8fafc; color: #475569; font-size: 12px; }
.sync-stage-item.is-active { background: #eff6ff; border-color: var(--accent); color: #1e40af; font-weight: 700; }
.sync-stage-item.is-done   { background: #f0fdf4; border-color: #bbf7d0; color: #166534; }
.sync-modal__actions { padding: 0 20px 18px; display: flex; justify-content: flex-end; gap: 10px; flex-wrap: wrap; }
.sync-btn {
    border: 0; border-radius: 999px; padding: 9px 18px;
    font-weight: 700; cursor: pointer;
    transition: transform 120ms ease, opacity 120ms ease;
}
.sync-btn:hover   { transform: translateY(-1px); }
.sync-btn:disabled { cursor: not-allowed; opacity: .62; transform: none; }
.sync-btn--primary   { background: #059669; color: #fff; }
.sync-btn--secondary { background: #e5e7eb; color: #111827; }
.sync-btn--danger    { background: #f59e0b; color: #111827; }
</style>
</head>
<body>

<!-- ════════════════════════════════════════════════════
     HEADER CARD
════════════════════════════════════════════════════ -->
<div class="header-card">
    <div class="top-row">
        <div class="brand">
            <div class="brand-icon">
                <!-- mini Gantt icon -->
                <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <rect x="3" y="4" width="8" height="3" rx="1.5" fill="rgba(255,255,255,.9)"/>
                    <rect x="3" y="8.5" width="12" height="3" rx="1.5" fill="rgba(255,255,255,.7)"/>
                    <rect x="3" y="13" width="6" height="3" rx="1.5" fill="rgba(255,255,255,.55)"/>
                </svg>
            </div>
            <div class="brand-text">
                <div class="eyebrow">PCP · Sequenciamento</div>
                <h1>Gráfico de Gantt</h1>
            </div>
        </div>

        <div class="actions">
            <button type="button" id="syncCodiBtn" class="btn btn-green">
                <svg width="14" height="14" viewBox="0 0 14 14" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M2 7A5 5 0 0 1 12 7" stroke="white" stroke-width="1.5" stroke-linecap="round"/>
                    <path d="M12 7A5 5 0 0 1 2 7" stroke="white" stroke-width="1.5" stroke-linecap="round" stroke-dasharray="2 2"/>
                    <path d="M10.5 5 L12 7 L13.5 5" stroke="white" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                Sincronizar CODI
            </button>
            <a href="relgantt.php" class="btn btn-soft">Analítico</a>
            <a href="index.php" class="btn btn-ghost">Voltar ao Sistema</a>
        </div>
    </div>

    <div class="controls-row">
        <span class="controls-label">Programação</span>
        <div class="select-wrap">
            <form method="GET" id="filterForm" style="display:contents;">
                <select name="programacao_id" onchange="this.form.submit()">
                    <?php foreach ($programacoes as $prg): ?>
                        <option value="<?= $prg['prg_id'] ?>" <?= $selectedProgramId === (int)$prg['prg_id'] ? 'selected' : '' ?>>
                            <?php
                                $linha  = htmlspecialchars($ganttNormalizeLineLabel((string)($prg['linha_excel_dominante'] ?: $prg['lin_codigo'] ?: 'S/Linha')), ENT_QUOTES, 'UTF-8');
                                $inicio = $prg['inicio_base_cronograma'] ? date('d/m/Y H:i', strtotime($prg['inicio_base_cronograma'])) : 'S/data';
                                $prog   = $prg['programacao_criada_em']  ? date('d/m/Y H:i', strtotime($prg['programacao_criada_em']))  : 'S/data';
                                $eff    = $prg['prg_eficiencia'] ?? 0;
                                echo "{$linha} | Início: {$inicio} | Programação: {$prog} | Eficiência: {$eff}%";
                            ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>
        <?php if ($programacaoInfo): ?>
            <?php
                $baseInicio = !empty($programacaoInfo['prg_base_inicio'])
                    ? date('d/m/Y H:i', strtotime($programacaoInfo['prg_base_inicio']))
                    : 'S/data';
            ?>
            <div class="meta-badge">
                <svg width="12" height="12" viewBox="0 0 12 12" fill="none"><circle cx="6" cy="6" r="5" stroke="#2563eb" stroke-width="1.5"/><path d="M6 4v3l2 1" stroke="#2563eb" stroke-width="1.5" stroke-linecap="round"/></svg>
                Eficiência <strong><?= $programacaoInfo['prg_eficiencia'] ?? 0 ?>%</strong>
            </div>
            <div class="meta-badge">
                <svg width="12" height="12" viewBox="0 0 12 12" fill="none"><rect x="1" y="2" width="10" height="9" rx="1.5" stroke="#2563eb" stroke-width="1.5"/><path d="M4 1v2M8 1v2M1 5h10" stroke="#2563eb" stroke-width="1.5" stroke-linecap="round"/></svg>
                Início <strong><?= $baseInicio ?></strong>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- ════════════════════════════════════════════════════
     GANTT
════════════════════════════════════════════════════ -->
<div id="gantt_here"></div>

<!-- ════════════════════════════════════════════════════
     LEGENDA
════════════════════════════════════════════════════ -->
<div class="legend-bar">
    <div class="legend-item">
        <div class="legend-dot" style="background:var(--prod-color)"></div>
        Produção (SKU)
    </div>
    <div class="legend-item">
        <div class="legend-dot" style="background:var(--setup-color)"></div>
        Setup (Troca)
    </div>
    <div class="legend-item">
        <div class="legend-dot" style="background:var(--pcp-status-ok)"></div>
        ≥ 100 % realizado
    </div>
    <div class="legend-item">
        <div class="legend-dot" style="background:var(--pcp-status-warn)"></div>
        80 – 99 %
    </div>
    <div class="legend-item">
        <div class="legend-dot" style="background:var(--pcp-status-bad)"></div>
        &lt; 80 %
    </div>
    <div class="legend-hint">Shift + scroll = horizontal &nbsp;|&nbsp; Ctrl/Cmd + scroll = zoom</div>
</div>

<!-- ════════════════════════════════════════════════════
     SYNC MODAL (idêntico ao gantt.php)
════════════════════════════════════════════════════ -->
<div id="codiSyncOverlay" class="sync-modal-overlay" aria-hidden="true">
    <div class="sync-modal" role="dialog" aria-modal="true" aria-labelledby="codiSyncTitle">
        <div class="sync-modal__head">
            <h2 id="codiSyncTitle" class="sync-modal__title">Sincronização CODI</h2>
        </div>
        <div class="sync-modal__body">
            <p id="codiSyncMessage" class="sync-modal__message">Sincronização em andamento, aguarde...</p>
            <div class="sync-progress" aria-hidden="true">
                <div id="codiSyncProgressBar" class="sync-progress__bar is-indeterminate"></div>
            </div>
            <div id="codiSyncStageCounter" class="sync-stage-counter"></div>
            <div id="codiSyncStageList" class="sync-stage-list" aria-live="polite"></div>
            <div id="codiSyncStatus" class="sync-modal__status"></div>
            <div class="sync-modal__note">O andamento é baseado em etapas reais do backend; não há percentual exato.</div>
        </div>
        <div class="sync-modal__actions">
            <button type="button" id="codiSyncNoBtn"    class="sync-btn sync-btn--secondary">Não</button>
            <button type="button" id="codiSyncYesBtn"   class="sync-btn sync-btn--primary">Sim</button>
            <button type="button" id="codiSyncCancelBtn" class="sync-btn sync-btn--danger"    style="display:none;">Cancelar</button>
            <button type="button" id="codiSyncCloseBtn"  class="sync-btn sync-btn--secondary" style="display:none;">Fechar</button>
        </div>
    </div>
</div>

<!-- DHTMLX Gantt (mesma versão do gantt.php) -->
<script src="https://cdn.dhtmlx.com/gantt/9.0/dhtmlxgantt.js"></script>
<script>
// ─── Localização PT ───────────────────────────────────────────────────────────
gantt.i18n.setLocale("pt");
gantt.plugins({ tooltip: true });

var PCP_COLORS = <?= json_encode($pcpPalette, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;

// ─── Helpers de data ──────────────────────────────────────────────────────────
function escapeHtml(text) {
    if (text === null || text === undefined) return "";
    return String(text)
        .replace(/&/g,"&amp;").replace(/</g,"&lt;").replace(/>/g,"&gt;")
        .replace(/"/g,"&quot;").replace(/'/g,"&#039;");
}

function formatRealDateTime(value) {
    if (!value) return "";
    var normalized = String(value).trim().replace(" ","T");
    var date = new Date(normalized);
    if (Number.isNaN(date.getTime())) return escapeHtml(String(value).slice(0,16));
    return String(date.getDate()).padStart(2,"0") + "/" +
           String(date.getMonth()+1).padStart(2,"0") + " " +
           String(date.getHours()).padStart(2,"0") + ":" +
           String(date.getMinutes()).padStart(2,"0");
}

function formatGanttDateTime(value) {
    if (!value) return "";
    var date = value instanceof Date ? value : new Date(value);
    if (Number.isNaN(date.getTime())) return "";
    return String(date.getDate()).padStart(2,"0") + "/" +
           String(date.getMonth()+1).padStart(2,"0") + " " +
           String(date.getHours()).padStart(2,"0") + ":" +
           String(date.getMinutes()).padStart(2,"0");
}

// ─── Config básica ────────────────────────────────────────────────────────────
gantt.config.date_format    = "%d-%m-%Y %H:%i";
gantt.config.readonly       = true;
gantt.config.tooltip_timeout = 80;
gantt.config.autosize       = false;
gantt.config.scroll_size    = 20;
gantt.config.enable_scroll  = true;

// Layout com scrollbars persistentes (idêntico ao gantt.php)
gantt.config.layout = {
    css: "gantt_container",
    rows: [
        {
            cols: [
                { view: "grid",      id: "grid",     scrollY: "scrollVer" },
                { resizer: true,     width: 1 },
                { view: "timeline",  id: "timeline", scrollX: "scrollHor", scrollY: "scrollVer" },
                { view: "scrollbar", id: "scrollVer" }
            ]
        },
        { view: "scrollbar", id: "scrollHor" }
    ]
};

// ─── Colunas do grid ──────────────────────────────────────────────────────────
gantt.config.columns = [
    {
        name: "text",
        label: "Produto / Recurso",
        width: 260,
        tree: true,
        fixed: true,
        template: function(task) {
            var tipo = String(task.tipo || "").toLowerCase();
            var isSetup    = (tipo === "setup") || (task.text && task.text.indexOf("SETUP") !== -1);
            var isRealizado = tipo === "realizado";

            if (isSetup) return '<div class="pcp-grid-op">&nbsp;</div>';

            if (isRealizado) {
                var prevInicio = "", prevFim = "";
                try {
                    if (typeof task.id === "string" && task.id.indexOf("real-") === 0) {
                        var pId = Number(task.id.slice(5));
                        if (!Number.isNaN(pId) && gantt.isTaskExists(pId)) {
                            var pt = gantt.getTask(pId);
                            prevInicio = formatGanttDateTime(pt.start_date);
                            prevFim    = formatGanttDateTime(pt.end_date);
                        }
                    }
                } catch(e) {}
                var periodoP = prevInicio && prevFim ? prevInicio + " – " + prevFim : prevInicio || prevFim || "";
                var inicioR  = task.realizado_inicio || "";
                var fimR     = task.realizado_fim    || "";
                var periodoR = inicioR && fimR ? formatRealDateTime(inicioR) + " – " + formatRealDateTime(fimR)
                                               : (inicioR ? formatRealDateTime(inicioR) : (fimR ? formatRealDateTime(fimR) : ""));
                var cP = "pcp-grid-real" + (periodoP ? "" : " pcp-grid-real--empty");
                var cR = "pcp-grid-real" + (periodoR ? "" : " pcp-grid-real--empty");
                return '<div class="pcp-grid-real-compare">' +
                    '<div class="pcp-grid-real-compare-line"><span class="pcp-grid-real-inline-label">Prev.</span><span class="' + cP + '">' + (periodoP ? escapeHtml(periodoP) : "S/período") + '</span></div>' +
                    '<div class="pcp-grid-real-compare-line"><span class="pcp-grid-real-inline-label">Real.</span><span class="' + cR + '">' + (periodoR ? escapeHtml(periodoR) : "S/período") + '</span></div>' +
                '</div>';
            }

            return '<div class="pcp-grid-op">OP ' + escapeHtml(task.op || "") + '</div>' +
                   '<div class="pcp-grid-prod">'  + escapeHtml(task.descricao_produto || "-") + '</div>';
        }
    },
    {
        name: "realizado",
        label: "<span style='display:inline-block;width:56px;text-align:center;'>Previsto</span>" +
               "<span style='display:inline-block;margin:0 6px;color:#94a3b8;'>|</span>" +
               "<span style='display:inline-block;width:80px;text-align:center;'>Realizado</span>",
        width: 200,
        fixed: true,
        template: function(task) {
            if (task.text && task.text.indexOf("SETUP") !== -1) {
                return '<div class="pcp-realizado-cell pcp-realizado-cell--setup">' +
                       '<div style="width:100%;text-align:right;padding-right:8px;">' +
                       '<span class="pcp-realizado-setup">SETUP</span></div></div>';
            }
            if (String(task.tipo || "").toLowerCase() === "realizado") {
                return '<div class="pcp-realizado-cell"></div>';
            }
            var prev = task.quantidade_prevista  || 0;
            var real = task.quantidade_realizada || 0;
            var pct  = task.percentual_cumprimento || 0;
            var bg   = PCP_COLORS.neutral_light;
            if (real > 0)
                bg = pct >= 100 ? PCP_COLORS.status_ok : (pct >= 80 ? PCP_COLORS.status_warn : PCP_COLORS.status_bad);
            return '<div class="pcp-realizado-cell">' +
                   '<span class="pcp-realizado-badge pcp-realizado-badge--prev">' + prev.toFixed(0) + '</span>' +
                   '<span class="pcp-realizado-sep">|</span>' +
                   '<span class="pcp-realizado-badge pcp-realizado-badge--real" style="background:' + bg + ';">' +
                   real.toFixed(0) + ' (' + pct.toFixed(0) + '%)' + '</span></div>';
        }
    }
];

// ─── Tooltip ─────────────────────────────────────────────────────────────────
gantt.templates.tooltip_text = function(start, end, task) {
    var dateToStr = gantt.date.date_to_str("%d/%m/%Y %H:%i");
    var prev = Number(task.quantidade_prevista  || 0);
    var real = Number(task.quantidade_realizada || 0);
    var pct  = Number(task.percentual_cumprimento || 0);
    var isSetup   = String(task.tipo || "").toLowerCase() === "setup";
    var durMin    = Math.max(0, Math.round((end - start) / 60000));
    var durLabel  = Math.floor(durMin / 60) + "h " + String(durMin % 60).padStart(2, "0") + "m";
    var memoria   = escapeHtml(task.memoria_calculo || "Memória de cálculo não disponível.")
                        .replace(/\s\|\s/g, "<br>").replace(/\n/g, "<br>");
    var tipo = isSetup ? "SETUP" : "Produção";
    return "<div class='pcp-tooltip-title'>" + tipo + "</div>" +
           "<div class='pcp-tooltip-grid'>" +
               "<div class='pcp-tooltip-label'>OP:</div><div>"      + escapeHtml(task.op || "S/OP") + "</div>" +
               "<div class='pcp-tooltip-label'>Produto:</div><div>" + escapeHtml(task.descricao_produto || "-") + "</div>" +
               "<div class='pcp-tooltip-label'>SKU:</div><div>"     + escapeHtml(task.sku || "-") + "</div>" +
               "<div class='pcp-tooltip-label'>Previsto:</div><div>" + prev.toFixed(0) + "</div>" +
               "<div class='pcp-tooltip-label'>Realizado:</div><div>" + real.toFixed(0) + " (" + pct.toFixed(0) + "%)</div>" +
               "<div class='pcp-tooltip-label'>Período:</div><div>"  + dateToStr(start) + " – " + dateToStr(end) + "</div>" +
               (isSetup ? "<div class='pcp-tooltip-label'>Duração:</div><div>" + durLabel + "</div>" : "") +
           "</div>" +
           "<div class='pcp-tooltip-memory'>" +
               "<div class='pcp-tooltip-memory-label'>Memória de cálculo</div>" +
               "<div class='pcp-tooltip-memory-body'>" + memoria + "</div>" +
           "</div>";
};

// ─── Classe de linha ──────────────────────────────────────────────────────────
gantt.templates.row_class = function(start, end, task) {
    return String(task.tipo || "").toLowerCase() === "setup" ? "pcp-row-setup" : "";
};

// ─── Escalas: Semana → Dia → 6h ──────────────────────────────────────────────
gantt.config.scales = [
    {
        unit: "week", step: 1,
        format: function(date) { return gantt.date.date_to_str("Semana %W")(date); }
    },
    { unit: "day", step: 1, format: "%D, %d %M" },
    {
        unit: "hour", step: 6,
        format: function(date) { return gantt.date.date_to_str("%Hh")(date); },
        css: function(date) {
            var isDayStart = date.getHours() === 0;
            var isAlt      = Math.floor(date.getHours() / 6) % 2 === 1;
            return "pcp-scale-6h" + (isAlt ? " pcp-scale-6h--alt" : "") + (isDayStart ? " pcp-scale-6h--day-start" : "");
        }
    }
];

gantt.config.scale_height      = 48;
gantt.config.row_height         = 30;
gantt.config.min_column_width   = 50;
gantt.config.show_task_cells    = true;

gantt.templates.timeline_cell_class = function(task, date) {
    var isDayStart = date.getHours() === 0;
    var isAlt      = Math.floor(date.getHours() / 6) % 2 === 1;
    return "pcp-timeline-6h" + (isAlt ? " pcp-timeline-6h--alt" : "") + (isDayStart ? " pcp-timeline-6h--day-start" : "");
};

// ─── Dados ────────────────────────────────────────────────────────────────────
var tasksData = { data: <?= json_encode($tasks, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?> };

gantt.init("gantt_here");
gantt.parse(tasksData);

// ─── Scroll/Zoom via wheel (idêntico ao gantt.php) ────────────────────────────
(function bindGanttWheelScroll() {
    var ganttRoot = document.getElementById("gantt_here");
    if (!ganttRoot || typeof gantt.getScrollState !== "function") return;

    var zoomLevels = [
        { name: "12h", hourStep: 12, minColumnWidth: 42 },
        { name: "6h",  hourStep:  6, minColumnWidth: 50 },
        { name: "3h",  hourStep:  3, minColumnWidth: 62 },
        { name: "1h",  hourStep:  1, minColumnWidth: 78 }
    ];
    var zoomIndex = 1;
    var pendingX = 0, pendingY = 0, rafId = null;
    var pendingZoomDelta = 0, zoomRafId = null, lastZoomClientX = null;
    var ZOOM_THRESHOLD = 60, MAX_ZOOM_STEPS = 3;

    function normalizeWheelDelta(e) {
        var dx = Number(e.deltaX||0), dy = Number(e.deltaY||0);
        if (e.deltaMode===1) { dx*=16; dy*=16; }
        else if (e.deltaMode===2) { dx*=ganttRoot.clientWidth||1; dy*=ganttRoot.clientHeight||1; }
        return { dx, dy };
    }

    function getHourScaleRef() {
        var scales = gantt.config && gantt.config.scales;
        if (!scales) return null;
        for (var i=0; i<scales.length; i++) if (scales[i] && scales[i].unit==="hour") return scales[i];
        return null;
    }

    function syncZoomIndex() {
        var h = getHourScaleRef();
        if (!h) return;
        for (var i=0; i<zoomLevels.length; i++) if (zoomLevels[i].hourStep===h.step) { zoomIndex=i; return; }
    }

    function applyZoomIndex(nextIdx, anchorX) {
        var clamped = Math.max(0, Math.min(zoomLevels.length-1, nextIdx));
        if (clamped===zoomIndex) return;
        var state = gantt.getScrollState();
        var anchorDate = null, cursorOffX = null;
        if (typeof gantt.dateFromPos==="function") {
            var el = gantt.$task_data || gantt.$task || ganttRoot.querySelector(".gantt_task") || ganttRoot;
            if (el && el.getBoundingClientRect) {
                var rect = el.getBoundingClientRect();
                cursorOffX = typeof anchorX==="number" ? Math.max(0,Math.min(rect.width,anchorX-rect.left)) : rect.width/2;
                anchorDate = gantt.dateFromPos(state.x + cursorOffX);
            }
        }
        var level = zoomLevels[clamped];
        var h = getHourScaleRef();
        if (h) h.step = level.hourStep;
        if (gantt.config) gantt.config.min_column_width = level.minColumnWidth;
        zoomIndex = clamped;
        gantt.render();
        if (anchorDate && cursorOffX !== null && typeof gantt.posFromDate==="function") {
            gantt.scrollTo(Math.max(0, gantt.posFromDate(anchorDate) - cursorOffX), state.y);
        } else {
            gantt.scrollTo(state.x, state.y);
        }
    }

    function flushScroll() {
        rafId = null;
        if (!pendingX && !pendingY) return;
        var s = gantt.getScrollState();
        gantt.scrollTo(s.x + pendingX, s.y + pendingY);
        pendingX = 0; pendingY = 0;
    }

    function flushZoom() {
        zoomRafId = null;
        if (!pendingZoomDelta) return;
        var sign = pendingZoomDelta > 0 ? 1 : -1;
        var steps = Math.min(MAX_ZOOM_STEPS, Math.floor(Math.abs(pendingZoomDelta) / ZOOM_THRESHOLD));
        if (steps < 1) return;
        pendingZoomDelta -= sign * steps * ZOOM_THRESHOLD;
        syncZoomIndex();
        for (var i=0; i<steps; i++) applyZoomIndex(zoomIndex + (sign>0?-1:1), lastZoomClientX);
        if (Math.abs(pendingZoomDelta) >= ZOOM_THRESHOLD) zoomRafId = requestAnimationFrame(flushZoom);
    }

    ganttRoot.addEventListener("wheel", function(e) {
        if (!e) return;
        var target = e.target;
        if (target && typeof target.closest==="function" && target.closest(".gantt_scrollbar,.gantt_ver_scroll,.gantt_hor_scroll")) return;
        var { dx, dy } = normalizeWheelDelta(e);
        if (e.ctrlKey || e.metaKey) {
            e.preventDefault();
            lastZoomClientX = typeof e.clientX==="number" ? e.clientX : null;
            pendingZoomDelta += Math.abs(dy)>=Math.abs(dx) ? dy : dx;
            if (zoomRafId===null) zoomRafId = requestAnimationFrame(flushZoom);
            return;
        }
        var scrollX = dx, scrollY = dy;
        if (e.shiftKey && Math.abs(scrollX)<0.5) { scrollX=dy; scrollY=0; }
        if (!scrollX && !scrollY) return;
        e.preventDefault();
        pendingX += scrollX; pendingY += scrollY;
        if (rafId===null) rafId = requestAnimationFrame(flushScroll);
    }, { passive: false });
})();

// ─── Subbarra realizado + marker + overlay (idêntico ao gantt.php) ─────────────
gantt.attachEvent("onAfterTaskRender", function(id, task, div) {
    var tipo    = String(task.tipo || "").toLowerCase();
    var isSetup = tipo === "setup" || (task.text && task.text.indexOf("SETUP") !== -1);

    if (isSetup) {
        var bars = div.getElementsByClassName("gantt_task_bar");
        if (bars.length > 0) {
            var b = bars[0];
            div.classList.remove("pcp-task-setup-short");
            div.style.width = ""; div.style.minWidth = "";
            b.style.width = ""; b.style.minWidth = "";
            if (div.offsetWidth > 0 && div.offsetWidth < 14) div.classList.add("pcp-task-setup-short");
        }
        return;
    }
    if (tipo === "realizado") return;

    var prev = task.quantidade_prevista  || 0;
    var real = task.quantidade_realizada || 0;
    var pct  = task.percentual_cumprimento || 0;
    var bars = div.getElementsByClassName("gantt_task_bar");
    if (!bars.length) return;
    var bar = bars[0];

    var ex = bar.querySelector(".pcp-realized-subbar");  if(ex) ex.remove();
    var eo = bar.querySelector(".pcp-realized-overlay"); if(eo) eo.remove();
    var em = bar.querySelector(".pcp-status-marker");    if(em) em.remove();

    var cor = PCP_COLORS.status_none;
    if (real > 0) cor = pct >= 100 ? PCP_COLORS.status_ok : (pct >= 80 ? PCP_COLORS.status_warn : PCP_COLORS.status_bad);

    var track = document.createElement("div");
    track.className = "pcp-realized-subbar";
    var fill = document.createElement("div");
    fill.className = "pcp-realized-subbar-fill";
    fill.style.backgroundColor = cor;
    fill.style.width = Math.max(0, Math.min(pct, 100)).toFixed(2) + "%";
    track.appendChild(fill);

    var marker = document.createElement("div");
    marker.className = "pcp-status-marker";
    marker.style.backgroundColor = cor;

    var overlay = document.createElement("div");
    overlay.className = "pcp-realized-overlay";
    var span = document.createElement("span");
    span.style.color = PCP_COLORS.overlay_muted || "rgba(255,255,255,.75)";
    span.textContent = real.toFixed(0) + "/" + prev.toFixed(0) + " (" + pct.toFixed(0) + "%)";
    overlay.appendChild(span);

    bar.style.position = "relative";
    bar.appendChild(track);
    bar.appendChild(marker);
    if (bar.offsetWidth >= 56) bar.appendChild(overlay);
});

// ─── Auto-sync CODI ───────────────────────────────────────────────────────────
(function autoSyncCODI() {
    var today = new Date().toISOString().split("T")[0];
    if (localStorage.getItem("codi_last_sync_date") === today) return;
    fetch("api/sync_codi.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ action: "sync_yesterday" })
    }).then(r => r.json()).then(data => {
        if (data.success) localStorage.setItem("codi_last_sync_date", today);
    }).catch(() => {});
})();
</script>

<!-- ── Botão Sincronizar CODI (lógica idêntica ao gantt.php) ──────────────── -->
<script>
(function() {
    var originalButton = document.getElementById("syncCodiBtn");
    if (!originalButton || !originalButton.parentNode) return;
    var syncButton = originalButton.cloneNode(true);
    originalButton.parentNode.replaceChild(syncButton, originalButton);

    var syncOverlay       = document.getElementById("codiSyncOverlay");
    var syncTitle         = document.getElementById("codiSyncTitle");
    var syncMessage       = document.getElementById("codiSyncMessage");
    var syncStatus        = document.getElementById("codiSyncStatus");
    var syncStageCounter  = document.getElementById("codiSyncStageCounter");
    var syncStageList     = document.getElementById("codiSyncStageList");
    var syncProgressBar   = document.getElementById("codiSyncProgressBar");
    var syncYesBtn        = document.getElementById("codiSyncYesBtn");
    var syncNoBtn         = document.getElementById("codiSyncNoBtn");
    var syncCancelBtn     = document.getElementById("codiSyncCancelBtn");
    var syncCloseBtn      = document.getElementById("codiSyncCloseBtn");
    var syncRequestController = null;
    var syncProgressTimer     = null;
    var syncStatusPollTimer   = null;
    var syncBusy = false;

    var syncKnownStages = [
        { code: "starting",        label: "Iniciando" },
        { code: "consulting_codi", label: "Consultando CODI" },
        { code: "processing_data", label: "Processando dados" },
        { code: "saving_aggregate",label: "Gravando agregado" },
        { code: "saving_events",   label: "Gravando eventos brutos" },
        { code: "finalizing",      label: "Finalizando" },
        { code: "done",            label: "Concluído" },
        { code: "error",           label: "Erro" }
    ];

    function formatSyncDateTime(value) {
        if (!value) return "";
        var text = String(value).trim();
        var m = text.match(/^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}):(\d{2}))?/);
        return m ? m[3]+"/"+m[2]+"/"+m[1]+" "+(m[4]||"00")+":"+(m[5]||"00") : text;
    }

    function getStageWidth(stageIndex, stageTotal, isRunning, stageCode) {
        var code = String(stageCode || "").toLowerCase();
        if (code === "done" || code === "error") return 100;
        var idx = Math.max(0, parseInt(stageIndex||0,10));
        var tot = Math.max(1, parseInt(stageTotal||6,10));
        var pct = Math.round((idx/tot)*100);
        if (isRunning && pct<10) pct=10;
        if (pct>95 && isRunning) pct=95;
        return pct;
    }

    function renderSyncStages(status) {
        var code  = String((status && status.stageCode)  || "").toLowerCase();
        var idx   = parseInt((status && status.stageIndex) || 0, 10);
        var tot   = parseInt((status && status.stageTotal) || 6, 10);
        var run   = !!(status && status.isRunning);
        var label = (status && status.stageLabel)  ? String(status.stageLabel)  : "";
        var detail= (status && status.stageDetail) ? String(status.stageDetail) : "";

        syncStageCounter.textContent = idx>0 && tot>0
            ? ("Etapa "+idx+"/"+tot+(label?" – "+label:""))
            : label;

        syncStageList.innerHTML = syncKnownStages.map(function(stage, i) {
            var cls = ["sync-stage-item"];
            if (stage.code === code) cls.push("is-active");
            else if (i < Math.max(0,idx-1) || (!run && code==="done" && stage.code!=="error")) cls.push("is-done");
            return '<div class="'+cls.join(" ")+'">'+stage.label+"</div>";
        }).join("");

        syncStatus.textContent = detail || (run ? "Sincronização em andamento..." : syncStatus.textContent);
        syncProgressBar.classList.remove("is-indeterminate");
        syncProgressBar.style.width = getStageWidth(idx, tot, run, code) + "%";
        if (code==="done") syncProgressBar.style.background = "linear-gradient(90deg,#059669,#34d399)";
        else if (code==="error") syncProgressBar.style.background = "linear-gradient(90deg,#ef4444,#f97316)";
    }

    function stopPolling() { if (syncStatusPollTimer) { clearInterval(syncStatusPollTimer); syncStatusPollTimer=null; } }

    function startPolling() {
        stopPolling();
        syncStatusPollTimer = setInterval(function() {
            if (!syncBusy) { stopPolling(); return; }
            fetchSyncStatus().then(function(s) { renderSyncStages(s); if (!s.isRunning) stopPolling(); }).catch(function() {});
        }, 1000);
    }

    function setSyncButtons(mode) {
        syncYesBtn.style.display    = mode==="confirm"  ? "" : "none";
        syncNoBtn.style.display     = mode==="confirm"  ? "" : "none";
        syncCancelBtn.style.display = mode==="progress" ? "" : "none";
        syncCloseBtn.style.display  = mode==="result"   ? "" : "none";
    }

    function openOverlay()  { syncOverlay.classList.add("is-open");    syncOverlay.setAttribute("aria-hidden","false"); }
    function closeOverlay() { syncOverlay.classList.remove("is-open"); syncOverlay.setAttribute("aria-hidden","true");  }

    function stopProgress(w) {
        if (syncProgressTimer) { clearInterval(syncProgressTimer); syncProgressTimer=null; }
        syncProgressBar.classList.remove("is-indeterminate");
        syncProgressBar.style.width = (typeof w==="number" ? w : 100) + "%";
    }

    function showConfirm(lastAt, records) {
        syncTitle.textContent   = "Sincronização já realizada";
        syncMessage.textContent = "Já sincronizado hoje. Deseja refazer?";
        syncStatus.textContent  = lastAt ? "Última execução: "+formatSyncDateTime(lastAt)+(records?" ("+records+" registros)":"") : "";
        syncStageCounter.textContent = ""; syncStageList.innerHTML = "";
        stopProgress(100); setSyncButtons("confirm"); openOverlay();
    }

    function showProgress() {
        syncTitle.textContent   = "Sincronização CODI";
        syncMessage.textContent = "Sincronização em andamento, aguarde...";
        syncStatus.textContent  = "As etapas serão atualizadas em tempo real.";
        renderSyncStages({ stageCode:"starting", stageLabel:"Iniciando", stageDetail:"Preparando sincronização CODI.", stageIndex:1, stageTotal:6, isRunning:true });
        setSyncButtons("progress"); openOverlay();
    }

    function showResult(title, msg, statusMsg, isError) {
        syncTitle.textContent   = title;
        syncMessage.textContent = msg;
        syncStatus.textContent  = statusMsg || "";
        syncProgressBar.style.background = isError
            ? "linear-gradient(90deg,#ef4444,#f97316)"
            : "linear-gradient(90deg,#059669,#34d399)";
        stopProgress(100); setSyncButtons("result"); openOverlay();
    }

    function fetchSyncStatus() {
        return fetch("api/sync_codi.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ action: "status" })
        }).then(function(r) {
            return r.json().then(function(data) { return { ok: r.ok, data: data }; });
        }).then(function(res) {
            if (!res.ok || !res.data || !res.data.success)
                throw new Error((res.data && res.data.message) ? res.data.message : "Erro ao verificar sync.");
            return res.data;
        });
    }

    function runManualSync(force) {
        if (syncBusy) return;
        syncBusy = true;
        syncRequestController = new AbortController();
        showProgress(); startPolling();
        fetch("api/sync_codi.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ action: "sync_yesterday", force: !!force }),
            signal: syncRequestController.signal
        }).then(function(r) {
            return r.json().then(function(data) { return { ok: r.ok, data: data }; });
        }).then(function(res) {
            syncBusy=false; syncRequestController=null; stopPolling(); stopProgress(100);
            if (res.data && res.data.success) {
                localStorage.setItem("codi_last_sync_date", new Date().toISOString().split("T")[0]);
                renderSyncStages({ stageCode:"done", stageLabel:"Concluído", stageDetail:res.data.message||"Finalizado.", stageIndex:syncKnownStages.length, stageTotal:syncKnownStages.length, isRunning:false });
                showResult("Concluído","Sincronização concluída!",res.data.message||"",false);
            } else {
                showResult("Erro","Não foi possível concluir.",(res.data&&res.data.message)||"",true);
            }
        }).catch(function(err) {
            syncBusy=false; syncRequestController=null; stopPolling(); stopProgress(100);
            if (err && err.name==="AbortError") {
                showResult("Cancelado","Sincronização cancelada.","O backend pode continuar em execução no servidor.",false);
                return;
            }
            showResult("Erro","Erro ao sincronizar.",(err&&err.message)||"",true);
        });
    }

    syncNoBtn.addEventListener("click", function() { closeOverlay(); syncButton.disabled=false; syncButton.textContent="Sincronizar CODI"; });
    syncYesBtn.addEventListener("click", function() { syncButton.disabled=true; runManualSync(true); });
    syncCancelBtn.addEventListener("click", function() {
        if (syncRequestController) { syncRequestController.abort(); }
        else { syncBusy=false; stopPolling(); closeOverlay(); syncButton.disabled=false; }
    });
    syncCloseBtn.addEventListener("click", function() { syncBusy=false; stopPolling(); closeOverlay(); syncButton.disabled=false; });

    syncButton.addEventListener("click", function() {
        if (syncBusy) return;
        var btn = this;
        btn.disabled=true;
        fetchSyncStatus().then(function(s) {
            btn.textContent = "Sincronizar CODI"; btn.disabled=false;
            if (s.isRunning) { syncBusy=true; showProgress(); renderSyncStages(s); startPolling(); return; }
            if (s.alreadySynced) { showConfirm(s.lastSyncAt, s.recordsToday||0); return; }
            runManualSync(true);
        }).catch(function(err) {
            btn.textContent="Sincronizar CODI"; btn.disabled=false;
            showResult("Verificação indisponível","Não foi possível verificar.",(err&&err.message)||"Tente novamente.",true);
        });
    });
})();
</script>
</body>
</html>
