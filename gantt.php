<?php
// encoding: UTF-8
/**
 * Gráfico de Gantt PCP - Versão Final (DHTMLX Gantt com Scroll Persistente)
 * Foco em: Cabeçalho Hierárquico, Scroll Vertical/Horizontal Sempre Visíveis.
 */

declare(strict_types=1);

require __DIR__ . '/src/bootstrap.php';

use App\Auth\Auth;
use App\Repository\ProgramacaoRepository;
use App\Database\Connection;

Auth::startSession();
$repo = new ProgramacaoRepository();
$pdo = Connection::get();

// Paleta visual centralizada do Gantt (fonte de verdade para PHP/CSS/JS)
$pcpPalette = [
    'prod' => '#3498db',
    'setup' => '#e67e22',
    'realizado' => '#ef4444',
    'realizado_neutral' => '#64748b',
    'status_ok' => '#10b981',
    'status_warn' => '#f59e0b',
    'status_bad' => '#ef4444',
    'status_none' => '#6b7280',
    'neutral_light' => '#d1d5db',
    'realizado_border' => 'rgba(185,28,28,0.7)',
    'realizado_neutral_border' => 'rgba(51,65,85,0.55)',
    'setup_border' => 'rgba(154, 52, 18, 0.35)',
    'overlay_text' => 'rgba(255,255,255,0.92)',
    'overlay_muted' => 'rgba(255,255,255,0.75)',
    'overlay_divider' => 'rgba(255,255,255,0.55)',
];

// Buscar lista de programa??es para o seletor
$programacoes = $repo->getAllProgramacoes(100, 0);

$ganttNormalizeLineLabel = static function (?string $value): string {
    $raw = trim((string) $value);
    if ($raw === '') {
        return 'S/Linha';
    }

    if (preg_match('/^(?:linha|ln)\s*0*(\d+)$/iu', $raw, $match) === 1) {
        return 'Linha ' . str_pad((string) (int) $match[1], 2, '0', STR_PAD_LEFT);
    }

    if (preg_match('/^0*(\d+)$/u', $raw, $match) === 1) {
        return 'Linha ' . str_pad((string) (int) $match[1], 2, '0', STR_PAD_LEFT);
    }

    return $raw;
};

$ganttExtractLineSortInfo = static function (array $prg) use ($ganttNormalizeLineLabel): array {
    $candidates = [
        (string) ($prg['linha_excel_dominante'] ?? ''),
        (string) ($prg['lin_codigo'] ?? ''),
    ];

    foreach ($candidates as $candidate) {
        $raw = trim($candidate);
        if ($raw === '') {
            continue;
        }

        if (preg_match('/^(?:linha|ln)\s*0*(\d+)$/iu', $raw, $match) === 1 || preg_match('/^0*(\d+)$/u', $raw, $match) === 1) {
            return [
                'group' => 0,
                'numeric' => (int) $match[1],
                'label' => $ganttNormalizeLineLabel($raw),
                'raw' => $raw,
            ];
        }

        return [
            'group' => 1,
            'numeric' => null,
            'label' => $ganttNormalizeLineLabel($raw),
            'raw' => $raw,
        ];
    }

    return [
        'group' => 2,
        'numeric' => null,
        'label' => 'S/Linha',
        'raw' => 'S/Linha',
    ];
};

usort($programacoes, static function (array $a, array $b) use ($ganttExtractLineSortInfo): int {
    $lineA = $ganttExtractLineSortInfo($a);
    $lineB = $ganttExtractLineSortInfo($b);

    if ($lineA['group'] !== $lineB['group']) {
        return $lineA['group'] <=> $lineB['group'];
    }

    if ($lineA['group'] === 0 && $lineA['numeric'] !== $lineB['numeric']) {
        return $lineA['numeric'] <=> $lineB['numeric'];
    }

    if ($lineA['label'] !== $lineB['label']) {
        return strcasecmp($lineA['label'], $lineB['label']);
    }

    $inicioA = (string) ($a['inicio_base_cronograma'] ?? '');
    $inicioB = (string) ($b['inicio_base_cronograma'] ?? '');
    if ($inicioA !== $inicioB) {
        return strcmp($inicioA, $inicioB);
    }

    $progA = (string) ($a['programacao_criada_em'] ?? '');
    $progB = (string) ($b['programacao_criada_em'] ?? '');
    if ($progA !== $progB) {
        return strcmp($progA, $progB);
    }

    return ((int) ($a['prg_id'] ?? 0)) <=> ((int) ($b['prg_id'] ?? 0));
});

// Se uma programacao especifica foi selecionada
$selectedProgramId = (int) ($_GET['programacao_id'] ?? $_GET['id'] ?? 0);
$periodStartInput = isset($_GET['data_inicio']) ? trim((string)$_GET['data_inicio']) : '';
$periodEndInput = isset($_GET['data_fim']) ? trim((string)$_GET['data_fim']) : '';
$hasValidPeriodFilter = preg_match('/^\d{4}-\d{2}-\d{2}$/', $periodStartInput) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $periodEndInput);
$schedule = [];
$programacaoInfo = null;

if ($selectedProgramId <= 0 && !empty($programacoes)) {
    $selectedProgramId = (int) $programacoes[0]['prg_id'];
}

if ($selectedProgramId > 0) {
    $programacaoInfo = $repo->getProgramacaoById($selectedProgramId);
    if ($programacaoInfo === null && !empty($programacoes)) {
        $selectedProgramId = (int) $programacoes[0]['prg_id'];
        $programacaoInfo = $programacoes[0];
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
    if (strtotime($periodStartInput) > strtotime($periodEndInput)) {
        [$periodStartInput, $periodEndInput] = [$periodEndInput, $periodStartInput];
    }
}

$screenPeriodStart = '';
$screenPeriodEnd = '';
if ($hasValidPeriodFilter) {
    $screenPeriodStart = $periodStartInput;
    $screenPeriodEnd = $periodEndInput;
} else {
    $screenStartCandidates = [];
    $screenEndCandidates = [];
    foreach ($schedule as $schRow) {
        $screenStartCandidate = trim((string) ($schRow['sch_inicio_producao'] ?? ''));
        $screenEndCandidate = trim((string) ($schRow['sch_fim_producao'] ?? ''));
        if ($screenStartCandidate !== '') {
            $screenStartCandidates[] = date('Y-m-d', strtotime($screenStartCandidate));
        }
        if ($screenEndCandidate !== '') {
            $screenEndCandidates[] = date('Y-m-d', strtotime($screenEndCandidate));
        }
    }

    $screenPeriodStart = !empty($screenStartCandidates) ? min($screenStartCandidates) : date('Y-m-d');
    $screenPeriodEnd = !empty($screenEndCandidates) ? max($screenEndCandidates) : date('Y-m-d');
}

if ($screenPeriodStart > $screenPeriodEnd) {
    [$screenPeriodStart, $screenPeriodEnd] = [$screenPeriodEnd, $screenPeriodStart];
}

// Carregar buckets programa+SKU => itens/OPs em uma unica query
$opBuckets = [];
if (!empty($schedule)) {
    $programIds = array_values(array_unique(array_map(static fn(array $row): int => (int)($row['sch_programa_id'] ?? 0), $schedule)));
    $programIds = array_values(array_filter($programIds, static fn(int $id): bool => $id > 0));
    if (!empty($programIds)) {
        $placeholders = implode(',', array_fill(0, count($programIds), '?'));
        $stmtOp = $pdo->prepare(
            "SELECT prg_programa_id, prg_sku, prg_quantidade, prg_sequencia, prg_id_item, prg_itens_op
             FROM prg_itens
             WHERE prg_programa_id IN ($placeholders)
             ORDER BY prg_programa_id ASC, prg_sequencia ASC, prg_id_item ASC"
        );
        $stmtOp->execute($programIds);
        foreach ($stmtOp->fetchAll(PDO::FETCH_ASSOC) as $opRow) {
            $bucketKey = $opRow['prg_programa_id'] . '|' . $opRow['prg_sku'];
            if (!isset($opBuckets[$bucketKey])) {
                $opBuckets[$bucketKey] = [];
            }
            $opBuckets[$bucketKey][] = [
                'op' => (string)($opRow['prg_itens_op'] ?? 'S/OP'),
                'quantidade' => (float)($opRow['prg_quantidade'] ?? 0),
                'used' => false,
            ];
        }
    }
}

$assignedOps = [];
if (!empty($schedule)) {
    foreach ($schedule as $schRow) {
        $scheduleId = (int)($schRow['sch_id'] ?? 0);
        $isSetup = strtolower(trim($schRow['sch_tipo'] ?? '')) === 'setup';
        $assignedOps[$scheduleId] = 'S/OP';
        if ($isSetup || empty($schRow['sch_sku'])) {
            continue;
        }

        $bucketKey = ((int)($schRow['sch_programa_id'] ?? 0)) . '|' . $schRow['sch_sku'];
        if (empty($opBuckets[$bucketKey])) {
            continue;
        }

        $quantidadePrevista = (float)($schRow['sch_quantidade'] ?? 0);
        $pickedIdx = null;

        foreach ($opBuckets[$bucketKey] as $idx => $item) {
            if ($item['used']) {
                continue;
            }
            if (abs($item['quantidade'] - $quantidadePrevista) < 0.0001) {
                $pickedIdx = $idx;
                break;
            }
        }

        if ($pickedIdx === null) {
            foreach ($opBuckets[$bucketKey] as $idx => $item) {
                if (!$item['used']) {
                    $pickedIdx = $idx;
                    break;
                }
            }
        }

        if ($pickedIdx !== null) {
            $assignedOps[$scheduleId] = $opBuckets[$bucketKey][$pickedIdx]['op'];
            $opBuckets[$bucketKey][$pickedIdx]['used'] = true;
        }
    }
}

// Preparar dados para o DHTMLX Gantt
$tasks = [];

// ===== BUSCAR DADOS REALIZADO (por OP + periodo de cada item) =====
// Estrategia: buscar o realizado por OP dentro da janela da tela, sem estender o fim.
// Chave do mapa: op . '|' . sch_inicio_producao (distingue lotes da mesma OP)
$realizadoMap = [];
if (!empty($schedule)) {
    $realTable = 'realizado_2026_excel';

    $pickFirstExisting = function(array $cols, array $candidates): ?string {
        $set = array_fill_keys($cols, true);
        foreach ($candidates as $c) {
            if (isset($set[$c])) return $c;
        }
        return null;
    };

    // Compatibilidade entre ambientes: algumas colunas podem não existir (ex.: inicio_evento/fim_evento).
    // Descobrir colunas disponíveis uma vez e montar SQL seguro (sem alterar dados/cálculos).
    $realCols = [];
    try {
        $realCols = $pdo->query("SHOW COLUMNS FROM `{$realTable}`")->fetchAll(PDO::FETCH_COLUMN, 0);
    } catch (Throwable $e) {
        $realCols = [];
    }

    $colQty = $pickFirstExisting($realCols, ['quantidade', 'qtd', 'qtde', 'quantidade_produzida']);
    $colOp = $pickFirstExisting($realCols, ['ordem_op', 'op', 'ordem']);
    $colDate = $pickFirstExisting($realCols, ['data_evento', 'data', 'data_apontamento', 'data_hora']);

    $colInicio = $pickFirstExisting($realCols, ['inicio_evento', 'inicio', 'data_inicio', 'inicio_apontamento', 'dt_inicio', 'inicio_real']);
    $colFim = $pickFirstExisting($realCols, ['fim_evento', 'fim', 'data_fim', 'fim_apontamento', 'dt_fim', 'fim_real']);

    // Coletar todos os periodos e OPs nao-setup de uma vez, respeitando a janela da tela
    $opsPeriodos = [];
    foreach ($schedule as $schRow) {
        if (strtolower(trim($schRow['sch_tipo'] ?? '')) === 'setup') continue;
        if (empty($schRow['sch_sku']) || empty($schRow['sch_inicio_producao'])) continue;
        $opItem = $assignedOps[(int)($schRow['sch_id'] ?? 0)] ?? 'S/OP';
        if ($opItem === 'S/OP') continue;

        $itemStart = date('Y-m-d', strtotime((string) $schRow['sch_inicio_producao']));
        $itemEnd = date('Y-m-d', strtotime((string) $schRow['sch_fim_producao']));
        $queryStart = max($screenPeriodStart, $itemStart);
        $queryEnd = $screenPeriodEnd;
        if ($queryStart > $queryEnd) {
            continue;
        }

        $opsPeriodos[] = [
            'op'     => $opItem,
            'inicio' => $queryStart,
            'fim'    => $queryEnd,
            'chave'  => $opItem . '|' . $schRow['sch_inicio_producao'],
        ];
    }

    // Buscar realizado para cada item individualmente
    if ($colQty && $colOp && $colDate) {
        $exprInicio = $colInicio ? "MIN(`{$colInicio}`)" : "MIN(`{$colDate}`)";
        $exprFim = $colFim ? "MAX(`{$colFim}`)" : "MAX(`{$colDate}`)";

        $stmtReal = $pdo->prepare("
            SELECT
                SUM(`{$colQty}`) as total,
                {$exprInicio} as inicio_real,
                {$exprFim} as fim_real
            FROM `{$realTable}`
            WHERE `{$colOp}` = ? AND `{$colDate}` >= ? AND `{$colDate}` <= ?
        ");
        foreach ($opsPeriodos as $item) {
            $stmtReal->execute([$item['op'], $item['inicio'], $item['fim']]);
            $res = $stmtReal->fetch(PDO::FETCH_ASSOC);
            $realizadoMap[$item['chave']] = [
                'total' => (float)($res['total'] ?? 0),
                'inicio_real' => $res['inicio_real'] ?? null,
                'fim_real' => $res['fim_real'] ?? null,
            ];
        }
    }
}

if (!empty($schedule)) {
    foreach ($schedule as $row) {
        $start = $row['sch_inicio_producao'];
        $end = $row['sch_fim_producao'];
        
        if ($start && $end) {
            $isSetup = strtolower(trim($row['sch_tipo'] ?? '')) === 'setup';
            
            // Pegar a OP do item correspondente em prg_itens
            $op = $isSetup ? 'S/OP' : ($assignedOps[(int)($row['sch_id'] ?? 0)] ?? 'S/OP');
            
            // Buscar realizado para esta OP
            $chaveRealizado = $op . '|' . $row['sch_inicio_producao'];
            $realizadoData = $realizadoMap[$chaveRealizado] ?? [
                'total' => 0.0,
                'inicio_real' => null,
                'fim_real' => null,
            ];
            $quantidadeRealizada = (float)($realizadoData['total'] ?? 0.0);
            $inicioRealizado = $realizadoData['inicio_real'] ?? null;
            $fimRealizado = $realizadoData['fim_real'] ?? null;
            $quantidadePrevista = (float)($row['sch_quantidade'] ?? 0);
            $percentualCumprimento = $quantidadePrevista > 0 ? ($quantidadeRealizada / $quantidadePrevista) * 100 : 0;
            
            // Cor base por tipo (status por percentual é mostrado no overlay/subbarra).
            $cor = $isSetup ? $pcpPalette['setup'] : $pcpPalette['prod'];
            
            $taskId = (int)$row['sch_id'];
            $tasks[] = [
                'id' => $taskId,
                'text' => ($isSetup ? "• SETUP" : "OP " . $op . "\n" . trim($row['sch_descricao'] ?? '-')),
                'descricao_produto' => trim($row['sch_descricao'] ?? '-'),
                'start_date' => date('d-m-Y H:i', strtotime($start)),
                'end_date' => date('d-m-Y H:i', strtotime($end)),
                'color' => $cor,
                'progress' => 1,
                'open' => true,
                'sku' => $row['sch_sku'] ?: '-',
                'tipo' => $row['sch_tipo'],
                'op' => $op,
                'memoria_calculo' => (string)($row['sch_memoria_calculo'] ?? ''),
                'quantidade_prevista' => $quantidadePrevista,
                'quantidade_realizada' => $quantidadeRealizada,
                'realizado_inicio' => $inicioRealizado,
                'realizado_fim' => $fimRealizado,
                'percentual_cumprimento' => $percentualCumprimento
            ];

            if (!$isSetup) {
                // Criar a task de realizado somente quando existir início e fim reais válidos.
                if (!empty($inicioRealizado) && !empty($fimRealizado)) {
                    $realStart = $inicioRealizado;
                    $realEnd = $fimRealizado;
                    $tasks[] = [
                        'id' => 'real-' . $taskId,
                        'text' => 'Realizado',
                        'descricao_produto' => trim($row['sch_descricao'] ?? '-'),
                        'start_date' => date('d-m-Y H:i', strtotime($realStart)),
                        'end_date' => date('d-m-Y H:i', strtotime($realEnd)),
                        'color' => $pcpPalette['realizado'],
                        'progress' => 1,
                        'open' => true,
                        'sku' => $row['sch_sku'] ?: '-',
                        'tipo' => 'realizado',
                        'op' => $op,
                        'memoria_calculo' => (string)($row['sch_memoria_calculo'] ?? ''),
                        'quantidade_prevista' => $quantidadePrevista,
                        'quantidade_realizada' => $quantidadeRealizada,
                        'realizado_inicio' => $inicioRealizado,
                        'realizado_fim' => $fimRealizado,
                        'percentual_cumprimento' => $percentualCumprimento,
                        'hide_real_bar' => false
                    ];
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Gantt PCP - Visualização de Cronograma</title>
    <link rel="stylesheet" href="assets/css/app.css">
    <link rel="stylesheet" href="assets/css/theme.css">
    <!-- DHTMLX Gantt 9.0.6 - versão fixada para evitar quebras por atualização automática -->
    <link rel="stylesheet" href="https://cdn.dhtmlx.com/gantt/9.0/dhtmlxgantt.css">
    <style>
        :root {
            --primary-dark: #2c3e50;
            --pcp-color-prod: <?= htmlspecialchars($pcpPalette['prod'], ENT_QUOTES) ?>;
            --pcp-color-setup: <?= htmlspecialchars($pcpPalette['setup'], ENT_QUOTES) ?>;
            --pcp-color-realizado: <?= htmlspecialchars($pcpPalette['realizado'], ENT_QUOTES) ?>;
            --pcp-color-realizado-neutral: <?= htmlspecialchars($pcpPalette['realizado_neutral'], ENT_QUOTES) ?>;
            --pcp-status-ok: <?= htmlspecialchars($pcpPalette['status_ok'], ENT_QUOTES) ?>;
            --pcp-status-warn: <?= htmlspecialchars($pcpPalette['status_warn'], ENT_QUOTES) ?>;
            --pcp-status-bad: <?= htmlspecialchars($pcpPalette['status_bad'], ENT_QUOTES) ?>;
            --pcp-status-none: <?= htmlspecialchars($pcpPalette['status_none'], ENT_QUOTES) ?>;
            --pcp-neutral-light: <?= htmlspecialchars($pcpPalette['neutral_light'], ENT_QUOTES) ?>;
            --pcp-color-realizado-border: <?= htmlspecialchars($pcpPalette['realizado_border'], ENT_QUOTES) ?>;
            --pcp-color-realizado-neutral-border: <?= htmlspecialchars($pcpPalette['realizado_neutral_border'], ENT_QUOTES) ?>;
            --pcp-color-setup-border: <?= htmlspecialchars($pcpPalette['setup_border'], ENT_QUOTES) ?>;
            --pcp-overlay-text: <?= htmlspecialchars($pcpPalette['overlay_text'], ENT_QUOTES) ?>;
            --pcp-overlay-muted: <?= htmlspecialchars($pcpPalette['overlay_muted'], ENT_QUOTES) ?>;
            --pcp-overlay-divider: <?= htmlspecialchars($pcpPalette['overlay_divider'], ENT_QUOTES) ?>;

            /* Timeline (grade) - apenas aparência */
            --pcp-timeline-bg: #fbfcfe;
            --pcp-timeline-zebra-6h: rgba(15, 23, 42, 0.018);
            --pcp-timeline-gridline: rgba(226, 232, 240, 0.55);
            --pcp-timeline-rowline: rgba(226, 232, 240, 0.68);
            --pcp-timeline-dayline: rgba(148, 163, 184, 0.95);
            --pcp-timeline-header-zebra-6h: rgba(15, 23, 42, 0.012);

            --setup-color: var(--pcp-color-setup);
            --prod-color: var(--pcp-color-prod);
        }
        body { 
            font-family: 'Segoe UI', sans-serif; 
            background-color: #f4f7f6; 
            margin: 0; 
            padding: 15px; 
            height: 100vh; 
            display: flex; 
            flex-direction: column; 
            overflow: hidden; 
        }
        .header-card { 
            background: white; 
            padding: 15px; 
            border-radius: 8px; 
            box-shadow: 0 2px 10px rgba(0,0,0,0.05); 
            margin-bottom: 15px; 
        }
        .top-row { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; }
        
        /* Container do Gantt com altura fixa para forçar scroll vertical */
        #gantt_here { 
            flex: 1; 
            border-radius: 8px; 
            border: 1px solid #ddd; 
            background: #fff; 
            box-shadow: 0 2px 10px rgba(0,0,0,0.05); 
            position: relative;
            min-height: 500px;
            overflow: hidden;
        }

        .controls { display: flex; gap: 15px; align-items: center; font-size: 14px; flex-wrap: wrap; }
        select { padding: 8px; border-radius: 4px; border: 1px solid #ccc; min-width: 350px; }
        .btn-home { background: var(--primary-dark); color: white; padding: 8px 16px; border-radius: 4px; text-decoration: none; font-weight: bold; }
        .sync-modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.62);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            backdrop-filter: blur(2px);
        }
        .sync-modal-overlay.is-open {
            display: flex;
        }
        .sync-modal {
            width: min(560px, calc(100vw - 24px));
            background: #fff;
            border-radius: 14px;
            box-shadow: 0 22px 56px rgba(15, 23, 42, 0.28);
            overflow: hidden;
        }
        .sync-modal__head {
            padding: 18px 20px 12px;
            border-bottom: 1px solid #e5e7eb;
        }
        .sync-modal__title {
            margin: 0;
            font-size: 18px;
            color: var(--primary-dark);
        }
        .sync-modal__body {
            padding: 18px 20px 16px;
            color: #1f2937;
        }
        .sync-modal__message {
            margin: 0 0 14px;
            line-height: 1.45;
        }
        .sync-modal__note {
            margin-top: 10px;
            font-size: 12px;
            color: #6b7280;
        }
        .sync-progress {
            height: 12px;
            border-radius: 999px;
            background: #e5e7eb;
            overflow: hidden;
            position: relative;
        }
        .sync-progress__bar {
            width: 18%;
            height: 100%;
            background: linear-gradient(90deg, #27ae60, #57d67d);
            border-radius: inherit;
            transition: width 180ms ease;
        }
        .sync-progress__bar.is-indeterminate {
            position: relative;
            overflow: hidden;
        }
        .sync-progress__bar.is-indeterminate::after {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.4), transparent);
            animation: syncShimmer 1.1s infinite;
        }
        @keyframes syncShimmer {
            from { transform: translateX(-120%); }
            to { transform: translateX(120%); }
        }
        .sync-modal__status {
            margin-top: 12px;
            font-size: 13px;
            color: #374151;
            min-height: 18px;
        }
        .sync-stage-counter {
            margin-top: 12px;
            font-size: 12px;
            font-weight: 700;
            color: #0f172a;
        }
        .sync-stage-list {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 8px;
            margin-top: 10px;
        }
        .sync-stage-item {
            border: 1px solid #dbe3ea;
            border-radius: 10px;
            padding: 8px 10px;
            background: #f8fafc;
            color: #475569;
            font-size: 12px;
            line-height: 1.25;
        }
        .sync-stage-item.is-active {
            background: #e8f6ee;
            border-color: #27ae60;
            color: #14532d;
            font-weight: 700;
        }
        .sync-stage-item.is-done {
            background: #eefaf1;
            border-color: #b6e4c4;
            color: #166534;
        }
        .sync-modal__actions {
            padding: 0 20px 18px;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            flex-wrap: wrap;
        }
        .sync-btn {
            border: 0;
            border-radius: 999px;
            padding: 9px 16px;
            font-weight: 700;
            cursor: pointer;
            transition: transform 120ms ease, opacity 120ms ease, background 120ms ease;
        }
        .sync-btn:hover {
            transform: translateY(-1px);
        }
        .sync-btn:disabled {
            cursor: not-allowed;
            opacity: 0.62;
            transform: none;
        }
        .sync-btn--primary {
            background: #27ae60;
            color: #fff;
        }
        .sync-btn--secondary {
            background: #e5e7eb;
            color: #111827;
        }
        .sync-btn--danger {
            background: #f59e0b;
            color: #111827;
        }
        
        /* Estilização interna do DHTMLX Gantt */
        .gantt_task_line { border-radius: 4px; border: none; padding: 0; }
        /* Barra principal: altura mais fina, texto centralizado e elegante */
        .gantt_task_bar {
            height: 13px !important;
            min-height: 13px !important;
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
            box-shadow: 0 0 0 1px rgba(255,255,255,0.22) inset !important;
        }
        .gantt_task_line.pcp-task-setup-short {
            z-index: 9;
            overflow: visible !important;
        }
        .gantt_task_line.pcp-task-setup-short:hover {
            min-width: 18px !important;
            z-index: 16;
        }
        .gantt_task_line.pcp-task-setup-short:hover .gantt_task_bar {
            width: 100% !important;
            min-width: 18px !important;
            padding: 0 4px !important;
            justify-content: center !important;
            box-shadow: 0 0 0 1px rgba(255,255,255,0.28) inset, 0 2px 6px rgba(154, 52, 18, 0.18) !important;
        }
        .gantt_task_line.pcp-task-setup-short .gantt_task_content {
            font-size: 0 !important;
            padding: 0 !important;
        }
        .gantt_task_line.pcp-task-setup-short .gantt_task_content::before {
            content: "•";
            font-size: 9px;
            line-height: 1;
        }
        .pcp-realized-subbar {
            position: absolute;
            left: 5px;
            right: 5px;
            bottom: 5px;
            height: 5px;
            border-radius: 999px;
            background: rgba(255,255,255,0.92);
            border: 1px solid rgba(15, 23, 42, 0.12);
            box-shadow: 0 0 0 1px rgba(255,255,255,0.18) inset;
            overflow: hidden;
            pointer-events: none;
            z-index: 8;
        }
        .pcp-realized-subbar-fill {
            height: 100%;
            border-radius: 999px;
            min-width: 0;
            transition: width 0.12s ease;
            box-shadow: 0 0 0 1px rgba(255,255,255,0.20) inset;
        }
        .pcp-status-marker {
            position: absolute;
            top: 2px;
            bottom: 2px;
            left: 2px;
            width: 3px;
            border-radius: 2px;
            pointer-events: none;
            z-index: 9;
            box-shadow: 0 0 0 1px rgba(255,255,255,0.18) inset;
        }
        .pcp-realized-overlay {
            position: absolute;
            top: 50%;
            left: 8px;
            transform: translateY(-50%);
            font-size: 8px;
            font-weight: 600;
            color: var(--pcp-overlay-text);
            display: flex;
            align-items: center;
            gap: 5px;
            padding: 0 4px;
            border-radius: 2px;
            white-space: nowrap;
            pointer-events: none;
            z-index: 10;
            opacity: 0.55;
        }
        .gantt_task_line:hover .pcp-realized-overlay {
            opacity: 0.82;
        }
        .gantt_task_content { font-size: 9px; font-weight: 600; line-height: 1; display: inline-block; vertical-align: middle; padding: 0 4px; }
        .gantt_grid_head_cell { font-weight: bold; color: #555; }

        /* Fundo base e linhas horizontais da timeline (não afeta escala/posicionamento) */
        .gantt_task_bg {
            background: var(--pcp-timeline-bg) !important;
        }
        .gantt_task_bg .gantt_task_row {
            box-shadow: inset 0 -1px 0 var(--pcp-timeline-rowline);
        }

        .gantt_scale_cell { font-weight: bold; color: #2c3e50; border-right: 1px solid var(--pcp-timeline-gridline); font-size: 11px; padding: 8px 6px; line-height: 1.05; display: flex; align-items: center; }
        .gantt_scale_cell.pcp-scale-6h {
            font-size: 10px;
            color: #64748b;
        }
        .gantt_scale_cell.pcp-scale-6h--alt {
            background: var(--pcp-timeline-header-zebra-6h);
        }
        .gantt_scale_cell.pcp-scale-6h--day-start,
        .gantt_task_cell.pcp-timeline-6h--day-start {
            border-right-color: var(--pcp-timeline-dayline);
        }
        .gantt_task_cell.pcp-timeline-6h {
            border-right: 1px solid var(--pcp-timeline-gridline);
            padding: 0 4px;
        }
        .gantt_task_cell.pcp-timeline-6h--alt {
            background: var(--pcp-timeline-zebra-6h);
        }

        /* Permitir 2 linhas por raia no grid */
        .gantt_grid_data .gantt_cell,
        .gantt_grid_data .gantt_tree_content {
            white-space: normal !important;
            line-height: 1.15;
        }

        .pcp-grid-op {
            font-weight: 700;
            color: #0f172a;
            font-size: 10px;
            line-height: 1.05;
            margin-bottom: 1px;
        }

        .pcp-grid-setup {
            width: 100%;
            text-align: right;
            padding-right: 10px;
            font-weight: 900;
            color: #0f172a;
            line-height: 29px; /* alinha no meio da linha ajustada */
        }

        .pcp-grid-prod {
            font-size: 8px;
            font-weight: 500;
            color: #64748b;
            margin-top: 0;
            line-height: 1.05;
        }
        .pcp-grid-real-row-title,
        .pcp-grid-real-inline-label {
            font-size: 10px;
            font-weight: 800;
            color: #334155;
            display: inline;
            margin-right: 4px;
        }
        .pcp-grid-real {
            font-size: 9px;
            font-weight: 700;
            color: #475569;
            display: inline;
            line-height: 1.1;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .pcp-grid-real--empty {
            color: #94a3b8;
        }
        .pcp-grid-real-compare-line {
            line-height: 1.05;
            white-space: nowrap;
        }
        .pcp-realizado-row-badge {
            display: inline-block;
            color: #fff;
            background: #64748b;
            padding: 1px 5px;
            border-radius: 3px;
            font-size: 10px;
            font-weight: 800;
            min-width: 72px;
            text-align: center;
        }
        .pcp-realizado-row-badge--empty {
            background: #cbd5e1;
            color: #334155;
        }
        .gantt_task_line.pcp-task-realizado {
            /* manter apenas um wrapper leve; a apar?ncia principal vir? da .gantt_task_bar interna */
            background: transparent !important;
            border: none !important;
            box-shadow: none !important;
            padding: 0 !important;
            margin-top: 0 !important;
            border-radius: 4px !important;
            display: flex !important;
            align-items: center !important;
            height: 29px !important;
            min-height: 29px !important; /* respeita a altura da raia */
        }
        .gantt_task_line.pcp-task-realizado .gantt_task_bar {
            /* herdar visual da barra principal, apenas trocar a cor */
            height: 10px !important;
            min-height: 10px !important;
            border-radius: 4px !important;
            padding: 0 8px !important;
            box-shadow: none !important;
            box-sizing: border-box !important;
            display: flex !important;
            align-items: center !important;
            justify-content: flex-start !important;
            background: var(--pcp-color-realizado-neutral) !important;
            border: 1px solid var(--pcp-color-realizado-neutral-border) !important;
            color: inherit !important;
            overflow: visible !important;
            transform: translateY(-1px) !important;
        }
        .gantt_task_line.pcp-task-realizado .gantt_task_content {
            display: none !important;
        }
        .gantt_task_line.pcp-task-realizado .gantt_task_progress {
            background: var(--pcp-color-realizado-neutral) !important;
            border: 1px solid var(--pcp-color-realizado-neutral-border) !important;
            border-radius: 4px !important;
            box-sizing: border-box !important;
            opacity: 1 !important;
        }
        .gantt_task_line.pcp-task-realizado-hidden,
        .gantt_task_line.pcp-task-realizado-hidden .gantt_task_content {
            background: transparent !important;
            color: transparent !important;
            border: none !important;
            box-shadow: none !important;
        }

        /* Centralizar verticalmente os badges Previsto/Realizado dentro da linha */
        .pcp-realizado-cell {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            height: 100%;
            width: 100%;
        }

        /* Para SETUP: deixar o JavaScript controlar o posicionamento */
        .pcp-realizado-cell--setup {
            display: flex;
            width: 100%;
            height: 100%;
        }

        .pcp-realizado-setup {
            color: #94a3b8;
            font-weight: 800;
            white-space: nowrap;
        }

        /* Garante centralizacao vertical do conteudo na coluna Previsto|Realizado */
        .gantt_grid_data .gantt_cell[data-column-name="realizado"] {
            display: flex;
            align-items: center;
        }

        .pcp-realizado-sep {
            color: #94a3b8;
            font-weight: 700;
        }

        .pcp-realizado-badge {
            color: #fff;
            padding: 1px 5px;
            border-radius: 3px;
            font-size: 10px;
            font-weight: 800;
            display: inline-block;
            text-align: center;
            line-height: 1.25;
            min-width: 54px;
        }

        .pcp-realizado-badge--prev {
            background: var(--pcp-color-prod);
        }

        .pcp-realizado-badge--real {
            min-width: 86px;
        }
        
        /* For?ar visibilidade das barras de scroll - APARECER SEMPRE QUE NECESS?RIO */
        .gantt_hor_scroll { 
            background-color: #f8f9fa !important;
            display: block !important;
            visibility: visible !important;
            overflow-x: auto !important;
        }
        
        .gantt_ver_scroll { 
            background-color: #f8f9fa !important;
            display: block !important;
            visibility: visible !important;
            overflow-y: auto !important;
        }
        
        .gantt_scrollbar {
            display: block !important;
            visibility: visible !important;
        }

        .legend { 
            display: flex; 
            gap: 20px; 
            padding: 10px; 
            background: white; 
            border-radius: 0 0 8px 8px; 
            font-size: 12px; 
            border-top: 1px solid #eee; 
            margin-top: 10px;
        }
        .legend-item { display: flex; align-items: center; gap: 6px; }
        .box { width: 14px; height: 14px; border-radius: 3px; }
        .gantt_tooltip {
            max-width: 340px;
            white-space: normal;
            line-height: 1.2;
            padding: 8px 10px;
            font-size: 12px;
        }
        .pcp-tooltip-title {
            font-size: 13px;
            font-weight: 700;
            margin-bottom: 6px;
        }
        .pcp-tooltip-grid {
            display: grid;
            grid-template-columns: auto 1fr;
            gap: 2px 8px;
            align-items: start;
        }
        .pcp-tooltip-label {
            font-weight: 700;
            white-space: nowrap;
        }
        .pcp-tooltip-memory {
            margin-top: 6px;
            padding-top: 5px;
            border-top: 1px solid rgba(255,255,255,0.16);
        }
        .pcp-tooltip-memory-label {
            font-weight: 700;
            margin-bottom: 3px;
        }
        .pcp-tooltip-memory-body {
            line-height: 1.25;
        }
    </style>
</head>
<body>

<div class="header-card">
    <div class="top-row">
        <h1 style="margin:0; font-size: 20px; color: var(--primary-dark);">Gráfico de Sequenciamento do PCP</h1>
        <div style="display: flex; gap: 10px;">
            <button type="button" id="syncCodiBtn" class="btn-home" style="background: #27ae60; cursor: pointer;">Sincronizar CODI</button>
            <a href="relgantt.php" class="btn-home">Analítico</a>
            <a href="index.php" class="btn-home">Voltar ao Sistema</a>
        </div>
    </div>
    <div class="controls">
        <form method="GET" id="filterForm" style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
            <strong>Programa&ccedil;&atilde;o:</strong>
            <select name="programacao_id" onchange="this.form.submit()">
                <?php foreach ($programacoes as $prg): ?>
                    <option value="<?= $prg['prg_id'] ?>" <?= $selectedProgramId === (int)$prg['prg_id'] ? 'selected' : '' ?>>
                        <?php 
                            $linha = htmlspecialchars($ganttNormalizeLineLabel((string) ($prg['linha_excel_dominante'] ?: $prg['lin_codigo'] ?: 'S/Linha')), ENT_QUOTES, 'UTF-8');
                            $inicio = $prg['inicio_base_cronograma'] ? date('d/m/Y H:i', strtotime($prg['inicio_base_cronograma'])) : 'S/data';
                            $prog = $prg['programacao_criada_em'] ? date('d/m/Y H:i', strtotime($prg['programacao_criada_em'])) : 'S/data';
                            $eff = $prg['prg_eficiencia'] ?? 0;
                            echo "{$linha} | In&iacute;cio: {$inicio} | Data da Programa&ccedil;&atilde;o: {$prog} | Efici&ecirc;ncia: " . $eff . '%';
                        ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>
        <?php if ($programacaoInfo): ?>
            <?php
                $baseInicio = !empty($programacaoInfo['prg_base_inicio'])
                    ? date('d/m/Y H:i', strtotime($programacaoInfo['prg_base_inicio']))
                    : 'S/data';
            ?>
            <span style="color: #666;">Efici&ecirc;ncia: <b><?= $programacaoInfo['prg_eficiencia'] ?? 0 ?>%</b> | In&iacute;cio: <b><?= $baseInicio ?></b></span>
        <?php endif; ?>
    </div>
</div>

<div id="gantt_here"></div>

<div class="legend">
    <div class="legend-item"><div class="box" style="background:var(--prod-color)"></div> Produção (SKU)</div>
    <div class="legend-item"><div class="box" style="background:var(--setup-color)"></div> Setup (Troca)</div>
    <div style="margin-left: auto; color: #888; font-style: italic;">* Use as barras de rolagem para navegar no tempo e nos itens.</div>
</div>

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
            <button type="button" id="codiSyncNoBtn" class="sync-btn sync-btn--secondary">Não</button>
            <button type="button" id="codiSyncYesBtn" class="sync-btn sync-btn--primary">Sim</button>
            <button type="button" id="codiSyncCancelBtn" class="sync-btn sync-btn--danger" style="display:none;">Cancelar</button>
            <button type="button" id="codiSyncCloseBtn" class="sync-btn sync-btn--secondary" style="display:none;">Fechar</button>
        </div>
    </div>
</div>

<script src="https://cdn.dhtmlx.com/gantt/9.0/dhtmlxgantt.js"></script>
<script>
    // Localiza??o para Portugu?s (Deve vir antes da config)
    gantt.i18n.setLocale("pt");
    gantt.plugins({ tooltip: true });

    var PCP_COLORS = <?= json_encode($pcpPalette, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;

    function escapeHtml(text) {
        if (text === null || text === undefined) return "";
        return String(text)
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/\"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }

    function formatRealDateTime(value) {
        if (!value) return "";
        var normalized = String(value).trim().replace(" ", "T");
        var date = new Date(normalized);
        if (Number.isNaN(date.getTime())) {
            return escapeHtml(String(value).slice(0, 16));
        }
        var day = String(date.getDate()).padStart(2, "0");
        var month = String(date.getMonth() + 1).padStart(2, "0");
        var hours = String(date.getHours()).padStart(2, "0");
        var minutes = String(date.getMinutes()).padStart(2, "0");
        return day + "/" + month + " " + hours + ":" + minutes;
    }

    function formatGanttDateTime(value) {
        if (!value) return "";
        var date = value instanceof Date ? value : new Date(value);
        if (Number.isNaN(date.getTime())) return "";
        var day = String(date.getDate()).padStart(2, "0");
        var month = String(date.getMonth() + 1).padStart(2, "0");
        var hours = String(date.getHours()).padStart(2, "0");
        var minutes = String(date.getMinutes()).padStart(2, "0");
        return day + "/" + month + " " + hours + ":" + minutes;
    }

    // Configurações Básicas
    gantt.config.date_format = "%d-%m-%Y %H:%i";
    gantt.config.readonly = true;
    gantt.config.tooltip_timeout = 80;

    // FORÇAR SCROLLS PERSISTENTES
    gantt.config.autosize = false; 
    gantt.config.scroll_size = 24; // Barra de scroll mais larga e fácil de clicar
    gantt.config.enable_scroll = true;
    
    // Configuração de Layout para garantir scrolls
    gantt.config.layout = {
        css: "gantt_container",
        rows: [
            {
                cols: [
                    {view: "grid", id: "grid", scrollY: "scrollVer"},
                    {resizer: true, width: 1},
                    {view: "timeline", id: "timeline", scrollX: "scrollHor", scrollY: "scrollVer"},
                    {view: "scrollbar", id: "scrollVer"}
                ]
            },
            {view: "scrollbar", id: "scrollHor"}
        ]
    };

    gantt.config.columns = [
        {
            name: "text", 
            label: "Produto / Recurso", 
            width: 260, 
            tree: true,
            fixed: true,
            template: function(task) {
                // Simplesmente retornar o texto - ele já contém a quebra de linha e descrição
                // OP na primeira linha e produto abaixo (mesma linha/raia no grid).
                var tipoTask = String(task.tipo || "").toLowerCase();
                var isSetup = (tipoTask === "setup")
                    || (task.text && task.text.indexOf("SETUP") !== -1);
                var isRealizadoRow = tipoTask === "realizado";

                if (isSetup) {
                    // Move o "SETUP" para a coluna Previsto | Realizado (no lugar do "-").
                    return '<div class="pcp-grid-op">&nbsp;</div>';
                }

                if (isRealizadoRow) {
                    var previstoInicioRow = "";
                    var previstoFimRow = "";
                    try {
                        if (typeof task.id === "string" && task.id.indexOf("real-") === 0) {
                            var plannedId = Number(task.id.slice(5));
                            if (!Number.isNaN(plannedId) && gantt.isTaskExists(plannedId)) {
                                var plannedTask = gantt.getTask(plannedId);
                                previstoInicioRow = formatGanttDateTime(plannedTask.start_date);
                                previstoFimRow = formatGanttDateTime(plannedTask.end_date);
                            }
                        }
                    } catch (e) {}

                    var periodoPrevRow = "";
                    if (previstoInicioRow && previstoFimRow) {
                        periodoPrevRow = previstoInicioRow + " - " + previstoFimRow;
                    } else if (previstoInicioRow) {
                        periodoPrevRow = previstoInicioRow;
                    } else if (previstoFimRow) {
                        periodoPrevRow = previstoFimRow;
                    }

                    var inicioRealRow = task.realizado_inicio || "";
                    var fimRealRow = task.realizado_fim || "";
                    var periodoRealRow = "";
                    if (inicioRealRow && fimRealRow) {
                        periodoRealRow = formatRealDateTime(inicioRealRow) + " - " + formatRealDateTime(fimRealRow);
                    } else if (inicioRealRow) {
                        periodoRealRow = formatRealDateTime(inicioRealRow);
                    } else if (fimRealRow) {
                        periodoRealRow = formatRealDateTime(fimRealRow);
                    }
                          var prevRowClass = "pcp-grid-real" + (periodoPrevRow ? "" : " pcp-grid-real--empty");
                          var realRowClass = "pcp-grid-real" + (periodoRealRow ? "" : " pcp-grid-real--empty");
                          return '<div class="pcp-grid-real-compare">' +
                              '<div class="pcp-grid-real-compare-line">' +
                                  '<span class="pcp-grid-real-inline-label">Previsto</span>' +
                                  '<span class="' + prevRowClass + '">' + (periodoPrevRow ? escapeHtml(periodoPrevRow) : 'S/período') + '</span>' +
                              '</div>' +
                              '<div class="pcp-grid-real-compare-line">' +
                                  '<span class="pcp-grid-real-inline-label">Realizado</span>' +
                                  '<span class="' + realRowClass + '">' + (periodoRealRow ? escapeHtml(periodoRealRow) : 'S/período') + '</span>' +
                              '</div>' +
                          '</div>';
                }

                var op = escapeHtml(task.op || "");
                var prod = escapeHtml(task.descricao_produto || "-");

                return '<div class="pcp-grid-op">OP ' + op + '</div>' +
                       '<div class="pcp-grid-prod">' + prod + '</div>';
            }
        },
        {
            name: "realizado",
            label: "<span style='display: inline-block; width: 60px; text-align: center;'>Previsto</span><span style='display: inline-block; margin: 0 8px;'>|</span><span style='display: inline-block; width: 80px; text-align: center;'>Realizado</span>",
            width: 200,
            fixed: true,
            template: function(task) {
                // Se for SETUP, renderizar alinhado à direita
                if(task.text && task.text.indexOf("SETUP") !== -1) {
                    // Renderizar um wrapper que ocupa 100% da célula e alinha o conteúdo à direita
                    return '<div class="pcp-realizado-cell pcp-realizado-cell--setup">'
                         + '<div style="width:100%; text-align:right; padding-right:8px;">'
                         + '<span class="pcp-realizado-setup">SETUP</span>'
                         + '</div>'
                         + '</div>';
                }
                if (String(task.tipo || "").toLowerCase() === "realizado") {
                    return '<div class="pcp-realizado-cell"></div>';
                }
                
                var prev = task.quantidade_prevista || 0;
                var real = task.quantidade_realizada || 0;
                var pct = task.percentual_cumprimento || 0;
                
                // Cor para o REALIZADO (baseado em porcentagem)
                var bgColorRealizado = PCP_COLORS.neutral_light; // Cinza padr?o
                if (real > 0) {
                    bgColorRealizado = pct >= 100 ? PCP_COLORS.status_ok : (pct >= 80 ? PCP_COLORS.status_warn : PCP_COLORS.status_bad);
                }
                
                return '<div class="pcp-realizado-cell">' +
                       '<span class="pcp-realizado-badge pcp-realizado-badge--prev">' + prev.toFixed(0) + '</span>' +
                       '<span class="pcp-realizado-sep">|</span>' +
                       '<span class="pcp-realizado-badge pcp-realizado-badge--real" style="background:' + bgColorRealizado + ';">' +
                          real.toFixed(0) + ' (' + pct.toFixed(0) + '%)' +
                       '</span>' +
                       '</div>';
            }
        }
    ];

    gantt.templates.tooltip_text = function(start, end, task) {
        var dateToStr = gantt.date.date_to_str("%d/%m/%Y %H:%i");
        var prev = Number(task.quantidade_prevista || 0);
        var real = Number(task.quantidade_realizada || 0);
        var pct = Number(task.percentual_cumprimento || 0);
        var isSetupTooltip = String(task.tipo || "").toLowerCase() === "setup";
        var durationMinutes = Math.max(0, Math.round((end - start) / 60000));
        var durationLabel = Math.floor(durationMinutes / 60) + "h " + String(durationMinutes % 60).padStart(2, "0") + "m";
        var memoria = escapeHtml(task.memoria_calculo || "Mem\u00f3ria de c\u00e1lculo n\u00e3o dispon\u00edvel.")
            .replace(/\s\|\s/g, "<br>")
            .replace(/\n/g, "<br>");
        var op = escapeHtml(task.op || "S/OP");
        var produto = escapeHtml(task.descricao_produto || "-");
        var sku = escapeHtml(task.sku || "-");
        var tipo = String(task.tipo || "").toLowerCase() === "setup" ? "SETUP" : "Produ\u00e7\u00e3o";

        return "<div class='pcp-tooltip-title'>" + tipo + "</div>" +
            "<div class='pcp-tooltip-grid'>" +
                "<div class='pcp-tooltip-label'>OP:</div><div>" + op + "</div>" +
                "<div class='pcp-tooltip-label'>Produto:</div><div>" + produto + "</div>" +
                "<div class='pcp-tooltip-label'>SKU:</div><div>" + sku + "</div>" +
                "<div class='pcp-tooltip-label'>Previsto:</div><div>" + prev.toFixed(0) + "</div>" +
                "<div class='pcp-tooltip-label'>Realizado:</div><div>" + real.toFixed(0) + " (" + pct.toFixed(0) + "%)</div>" +
                "<div class='pcp-tooltip-label'>Envelope de produ\u00e7\u00e3o:</div><div>" + dateToStr(start) + " - " + dateToStr(end) + "</div>" +
                "<div class='pcp-tooltip-label'>Nota:</div><div>Tempo produtivo abaixo considera pausas/calend\u00e1rio.</div>" +
                (isSetupTooltip ? "<div class='pcp-tooltip-label'>Dura\u00e7\u00e3o:</div><div>" + durationLabel + "</div>" : "") +
            "</div>" +
            "<div class='pcp-tooltip-memory'>" +
                "<div class='pcp-tooltip-memory-label'>Mem\u00f3ria de c\u00e1lculo</div>" +
                "<div class='pcp-tooltip-memory-body'>" + memoria + "</div>" +
            "</div>";
    };
    // ===== FOCAR SETUP PARA A DIREITA USANDO MUTATIONOBSERVER =====
    // DESATIVADO: removido temporariamente para investigação de layout.
    // Se precisar reativar a lógica de posicionamento automático, remova o comentário abaixo.
    /*
    var gridData = document.querySelector('.gantt_grid_data');
    if (gridData) {
        var observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                if (mutation.addedNodes.length > 0) {
                    mutation.addedNodes.forEach(function(node) {
                        if (node.nodeType === 1) { // Element node
                            var setupSpan = node.querySelector ? node.querySelector('.pcp-realizado-setup') : null;
                            if (!setupSpan && node.classList && node.classList.contains('pcp-realizado-setup')) {
                                setupSpan = node;
                            }
                            
                            if (setupSpan) {
                                var cell = setupSpan.closest('.gantt_cell[data-column-name="realizado"]');
                                if (cell && cell.querySelectorAll('.pcp-realizado-badge').length === 0) {
                                    cell.style.display = 'flex';
                                    cell.style.justifyContent = 'flex-end';
                                    cell.style.alignItems = 'center';
                                    cell.style.paddingRight = '8px';
                                    setupSpan.style.color = '#94a3b8';
                                    setupSpan.style.fontWeight = '800';
                                    setupSpan.style.whiteSpace = 'nowrap';
                                }
                            }
                        }
                    });
                }
            });
        });
        
        observer.observe(gridData, {
            childList: true,
            subtree: true,
            attributes: false
        });
    }
    */

    // Marca a row inteira quando for SETUP para que possamos atuar no pai da célula
    gantt.templates.row_class = function(start, end, task) {
        if (String(task.tipo || "").toLowerCase() === "setup") {
            return 'pcp-row-setup';
        }
        return '';
    };

    // CABE?ALHO HIER?RQUICO (Semanas, Dias e marca??es de 6h)
    gantt.config.scales = [
        {unit: "week", step: 1, format: function(date){
            var dateToStr = gantt.date.date_to_str("Semana %W");
            return dateToStr(date);
        }},
        {unit: "day", step: 1, format: "%D, %d %M"},
        {unit: "hour", step: 6, format: function(date){
            var hourToStr = gantt.date.date_to_str("%Hh");
            return hourToStr(date);
        }, css: function(date){
            var isDayStart = date.getHours() === 0;
            var isAlt = Math.floor(date.getHours() / 6) % 2 === 1;
            return "pcp-scale-6h"
                + (isAlt ? " pcp-scale-6h--alt" : "")
                + (isDayStart ? " pcp-scale-6h--day-start" : "");
        }}
    ];

    // Ajustes de Dimens?es para for?ar o scroll horizontal
    // Visual mais compacto: cabe?alho e raia reduzidos para apar?ncia de timeline
    // Pequeno aumento do cabeçalho para dar mais respiro vertical ao texto
    gantt.config.scale_height = 44;
    // raia reduzida (manter legibilidade) — ajustado para recuperar respiro
    gantt.config.row_height = 29;
    gantt.config.min_column_width = 50;
    gantt.config.show_task_cells = true;
    gantt.templates.timeline_cell_class = function(task, date){
        var isDayStart = date.getHours() === 0;
        var isAlt = Math.floor(date.getHours() / 6) % 2 === 1;
        return "pcp-timeline-6h"
            + (isAlt ? " pcp-timeline-6h--alt" : "")
            + (isDayStart ? " pcp-timeline-6h--day-start" : "");
    };

    // Inicializa??o com os dados
    var tasksData = {
        data: <?= json_encode($tasks) ?>
    };

    gantt.init("gantt_here");
    gantt.parse(tasksData);

    // ===== SCROLL/ZOOM VIA WHEEL (mouse/trackpad) dentro do Gantt =====
    // Regras:
    // - wheel normal: vertical
    // - Shift+wheel: horizontal
    // - Ctrl (Windows/Linux/pinch) ou Cmd (macOS) + wheel: zoom por níveis fixos
    // - trackpad deltaX/deltaY: respeitar ambos
    // Sem conflitar com os scrollbars nativos do DHTMLX (scrollVer/scrollHor).
    (function bindGanttWheelScroll() {
        var ganttRoot = document.getElementById("gantt_here");
        if (!ganttRoot) return;
        if (!gantt || typeof gantt.getScrollState !== "function" || typeof gantt.scrollTo !== "function") return;

        var zoomLevels = [
            { name: "12h", hourStep: 12, minColumnWidth: 42 },
            { name: "6h", hourStep: 6, minColumnWidth: 50 },
            { name: "3h", hourStep: 3, minColumnWidth: 62 },
            { name: "1h", hourStep: 1, minColumnWidth: 78 }
        ];
        var zoomIndex = 1;

        var pendingX = 0;
        var pendingY = 0;
        var rafId = null;

        var pendingZoomDelta = 0;
        var zoomRafId = null;
        var lastZoomClientX = null;
        var ZOOM_WHEEL_THRESHOLD = 60; // px acumulados para avançar 1 nível
        var MAX_ZOOM_STEPS_PER_FLUSH = 3;

        function normalizeWheelDelta(event) {
            var dx = Number(event.deltaX || 0);
            var dy = Number(event.deltaY || 0);
            if (event.deltaMode === 1) {
                dx *= 16;
                dy *= 16;
            } else if (event.deltaMode === 2) {
                dx *= ganttRoot.clientWidth || 1;
                dy *= ganttRoot.clientHeight || 1;
            }
            return { dx: dx, dy: dy };
        }

        function getHourScaleRef() {
            var scales = gantt.config && gantt.config.scales;
            if (!scales || !scales.length) return null;
            for (var i = 0; i < scales.length; i++) {
                if (scales[i] && scales[i].unit === "hour") return scales[i];
            }
            return null;
        }

        function syncZoomIndexFromConfig() {
            var hourScale = getHourScaleRef();
            if (!hourScale || !hourScale.step) return;
            for (var i = 0; i < zoomLevels.length; i++) {
                if (zoomLevels[i].hourStep === hourScale.step) {
                    zoomIndex = i;
                    return;
                }
            }
        }

        function getTimelineAreaElement() {
            return gantt.$task_data || gantt.$task || ganttRoot.querySelector(".gantt_task") || ganttRoot;
        }

        function applyZoomIndex(nextIndex, anchorClientX) {
            var clamped = Math.max(0, Math.min(zoomLevels.length - 1, nextIndex));
            if (clamped === zoomIndex) return;

            var state = gantt.getScrollState();
            var anchorDate = null;
            var cursorOffsetX = null;

            if (typeof gantt.dateFromPos === "function" && typeof gantt.posFromDate === "function") {
                var timelineEl = getTimelineAreaElement();
                if (timelineEl && timelineEl.getBoundingClientRect) {
                    var rect = timelineEl.getBoundingClientRect();
                    cursorOffsetX = (typeof anchorClientX === "number") ? (anchorClientX - rect.left) : (rect.width / 2);
                    cursorOffsetX = Math.max(0, Math.min(rect.width, cursorOffsetX));
                    anchorDate = gantt.dateFromPos(state.x + cursorOffsetX);
                }
            }

            var level = zoomLevels[clamped];
            var hourScaleRef = getHourScaleRef();
            if (hourScaleRef) {
                hourScaleRef.step = level.hourStep;
            }
            if (gantt.config) {
                gantt.config.min_column_width = level.minColumnWidth;
            }

            zoomIndex = clamped;
            gantt.render();

            if (anchorDate && cursorOffsetX !== null && typeof gantt.posFromDate === "function") {
                var newPos = gantt.posFromDate(anchorDate);
                var desiredX = Math.max(0, newPos - cursorOffsetX);
                gantt.scrollTo(desiredX, state.y);
            } else {
                gantt.scrollTo(state.x, state.y);
            }
        }

        function flushScroll() {
            rafId = null;
            if (!pendingX && !pendingY) return;
            var state = gantt.getScrollState();
            gantt.scrollTo(state.x + pendingX, state.y + pendingY);
            pendingX = 0;
            pendingY = 0;
        }

        function flushZoom() {
            zoomRafId = null;
            if (!pendingZoomDelta) return;

            var delta = pendingZoomDelta;
            var sign = delta > 0 ? 1 : -1;
            var abs = Math.abs(delta);
            var steps = Math.min(MAX_ZOOM_STEPS_PER_FLUSH, Math.floor(abs / ZOOM_WHEEL_THRESHOLD));

            if (steps < 1) return;

            pendingZoomDelta = delta - (sign * steps * ZOOM_WHEEL_THRESHOLD);

            var direction = sign > 0 ? -1 : 1;
            syncZoomIndexFromConfig();
            for (var i = 0; i < steps; i++) {
                applyZoomIndex(zoomIndex + direction, lastZoomClientX);
            }

            if (Math.abs(pendingZoomDelta) >= ZOOM_WHEEL_THRESHOLD) {
                zoomRafId = requestAnimationFrame(flushZoom);
            }
        }

        ganttRoot.addEventListener("wheel", function (event) {
            if (!event) return;

            var target = event.target;
            if (target && typeof target.closest === "function") {
                if (target.closest(".gantt_scrollbar, .gantt_ver_scroll, .gantt_hor_scroll")) {
                    return;
                }
            }

            var normalized = normalizeWheelDelta(event);
            var dx = normalized.dx;
            var dy = normalized.dy;

            var isZoomGesture = !!(event.ctrlKey || event.metaKey);
            if (isZoomGesture) {
                event.preventDefault();
                lastZoomClientX = typeof event.clientX === "number" ? event.clientX : null;
                pendingZoomDelta += (Math.abs(dy) >= Math.abs(dx) ? dy : dx);
                if (zoomRafId === null) {
                    zoomRafId = requestAnimationFrame(flushZoom);
                }
                return;
            }

            var scrollX = dx;
            var scrollY = dy;

            if (event.shiftKey && Math.abs(scrollX) < 0.5) {
                scrollX = dy;
                scrollY = 0;
            }

            if (!scrollX && !scrollY) return;

            event.preventDefault();
            pendingX += scrollX;
            pendingY += scrollY;
            if (rafId === null) {
                rafId = requestAnimationFrame(flushScroll);
            }
        }, { passive: false });
    })();

    // ===== ADICIONAR INFORMAÇÃO REALIZADO SOBRE AS BARRAS =====
    gantt.attachEvent("onAfterTaskRender", function(id, task, div){
        var tipoTask = String(task.tipo || "").toLowerCase();
        var isSetup = tipoTask === "setup" || (task.text && task.text.indexOf("SETUP") !== -1);

        if (isSetup) {
            var setupBars = div.getElementsByClassName("gantt_task_bar");
            if (setupBars.length > 0) {
                var setupBar = setupBars[0];
                div.classList.remove("pcp-task-setup-short");
                div.style.width = "";
                div.style.minWidth = "";
                setupBar.style.width = "";
                setupBar.style.minWidth = "";
                if (div.offsetWidth > 0 && div.offsetWidth < 14) {
                    div.classList.add("pcp-task-setup-short");
                }
            }
            return;
        }
        if (tipoTask === "realizado") {
            return;
        }
        
        var prev = task.quantidade_prevista || 0;
        var real = task.quantidade_realizada || 0;
        var pct = task.percentual_cumprimento || 0;
        
        // Procurar a barra (elemento com classe gantt_task_bar)
        var bars = div.getElementsByClassName("gantt_task_bar");
        if(bars.length === 0) return;
        
        var bar = bars[0];
        
        // Evitar duplicar elementos extras em re-renders do Gantt
        var existingTrack = bar.querySelector('.pcp-realized-subbar');
        if (existingTrack) existingTrack.remove();
        var existingOverlay = bar.querySelector('.pcp-realized-overlay');
        if (existingOverlay) existingOverlay.remove();
        var existingMarker = bar.querySelector('.pcp-status-marker');
        if (existingMarker) existingMarker.remove();

        // Criar barra visual secund?ria de realizado abaixo da barra planejada
        var corRealizado = PCP_COLORS.status_none; // Cinza: sem dados
        if (real > 0) {
            corRealizado = pct >= 100 ? PCP_COLORS.status_ok : (pct >= 80 ? PCP_COLORS.status_warn : PCP_COLORS.status_bad);
        }
        var realizedTrack = document.createElement('div');
        realizedTrack.className = 'pcp-realized-subbar';
        var realizedFill = document.createElement('div');
        realizedFill.className = 'pcp-realized-subbar-fill';
        realizedFill.style.backgroundColor = corRealizado;
        realizedFill.style.width = Math.max(0, Math.min(pct, 100)).toFixed(2) + '%';
        realizedTrack.appendChild(realizedFill);

        // Criar overlay com informa??o - REALIZADO | PREVISTO
        var marker = document.createElement('div');
        marker.className = 'pcp-status-marker';
        marker.style.backgroundColor = corRealizado;

        var overlay = document.createElement('div');
        overlay.className = 'pcp-realized-overlay';
        var overlayText = document.createElement('span');
        overlayText.style.color = PCP_COLORS.overlay_muted || 'rgba(255,255,255,0.75)';
        overlayText.textContent = real.toFixed(0) + '/' + prev.toFixed(0) + ' (' + pct.toFixed(0) + '%)';
        overlay.appendChild(overlayText);
        
        // Adicionar ao bar
        bar.style.position = 'relative';
        bar.appendChild(realizedTrack);
        bar.appendChild(marker);
        if (bar.offsetWidth >= 56) {
            bar.appendChild(overlay);
        }
    });

    // ========== SINCRONIZAÇÃO CODI AUTOMÁTICA ==========
    // Verifica se já sincronizou hoje, se não sincroniza automaticamente
    function autoSyncCODI() {
        const today = new Date().toISOString().split('T')[0];
        const lastSyncKey = 'codi_last_sync_date';
        const lastSyncDate = localStorage.getItem(lastSyncKey);
        
        // Se já sincronizou hoje, não faz nada
        if (lastSyncDate === today) {
            console.log('[CODI] Já sincronizado hoje:', today);
            return;
        }
        
        console.log('[CODI] Iniciando sincronização automática...');
        
        // Sincronizar silenciosamente
        fetch('api/sync_codi.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'sync_yesterday' })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                console.log('[CODI OK] Sincronizado:', data.message);
                localStorage.setItem(lastSyncKey, today);
            } else {
                console.warn('[CODI] Ja sincronizado hoje:', data.message);
                localStorage.setItem(lastSyncKey, today);
            }
        })
        .catch(error => {
            console.error('[CODI ERRO]', error);
        });
    }
    
    // Executar auto-sync quando p?gina carrega
    autoSyncCODI();

    // ========== SINCRONIZA??O CODI (BOT?O MANUAL) ==========
    document.getElementById('syncCodiBtn').addEventListener('click', function() {
        return;
        const btn = this;
        btn.disabled = true;
        btn.textContent = 'Sincronizando...';
        
        fetch('api/sync_codi.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                action: 'sync_today',
                force: true
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const today = new Date().toISOString().split('T')[0];
                localStorage.setItem('codi_last_sync_date', today);
                alert('Sincronização concluída!\n\n' + data.message);
                btn.textContent = 'Sincronizar CODI';
                btn.disabled = false;
            } else {
                alert(data.message);
                btn.textContent = 'Sincronizar CODI';
                btn.disabled = false;
            }
        })
        .catch(error => {
            console.error('Erro:', error);
            alert('Erro ao sincronizar: ' + error.message);
            btn.textContent = 'Sincronizar CODI';
            btn.disabled = false;
        });
    });
</script>
<script>
(function() {
    var originalButton = document.getElementById('syncCodiBtn');
    if (!originalButton || !originalButton.parentNode) {
        return;
    }

    var syncButton = originalButton.cloneNode(true);
    originalButton.parentNode.replaceChild(syncButton, originalButton);

    var syncOverlay = document.getElementById('codiSyncOverlay');
    var syncTitle = document.getElementById('codiSyncTitle');
    var syncMessage = document.getElementById('codiSyncMessage');
    var syncStatus = document.getElementById('codiSyncStatus');
    var syncStageCounter = document.getElementById('codiSyncStageCounter');
    var syncStageList = document.getElementById('codiSyncStageList');
    var syncProgressBar = document.getElementById('codiSyncProgressBar');
    var syncYesBtn = document.getElementById('codiSyncYesBtn');
    var syncNoBtn = document.getElementById('codiSyncNoBtn');
    var syncCancelBtn = document.getElementById('codiSyncCancelBtn');
    var syncCloseBtn = document.getElementById('codiSyncCloseBtn');
    var syncRequestController = null;
    var syncProgressTimer = null;
    var syncStatusPollTimer = null;
    var syncProgressValue = 12;
    var syncBusy = false;
    var syncKnownStages = [
        { code: 'starting', label: 'Iniciando' },
        { code: 'consulting_codi', label: 'Consultando CODI' },
        { code: 'processing_data', label: 'Processando dados' },
        { code: 'saving_aggregate', label: 'Gravando agregado' },
        { code: 'saving_events', label: 'Gravando eventos brutos' },
        { code: 'finalizing', label: 'Finalizando' },
        { code: 'done', label: 'Concluído' },
        { code: 'error', label: 'Erro' }
    ];

    function formatSyncDateTime(value) {
        if (!value) {
            return '';
        }

        var text = String(value).trim();
        var match = text.match(/^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}):(\d{2}))(?:\:\d{2})?/);
        if (match) {
            return match[3] + '/' + match[2] + '/' + match[1] + ' ' + match[4] + ':' + match[5];
        }

        return text;
    }

    function getStageWidthByIndex(stageIndex, stageTotal, isRunning, stageCode) {
        var code = String(stageCode || '').toLowerCase();
        if (code === 'done') {
            return 100;
        }
        if (code === 'error') {
            return 100;
        }

        var index = Math.max(0, parseInt(stageIndex || 0, 10));
        var total = Math.max(1, parseInt(stageTotal || 6, 10));
        var pct = Math.round((index / total) * 100);
        if (isRunning && pct < 10) {
            pct = 10;
        }
        if (pct > 95 && isRunning) {
            pct = 95;
        }
        return pct;
    }

    function renderSyncStages(status) {
        var currentCode = String((status && status.stageCode) || '').toLowerCase();
        var stageIndex = parseInt((status && status.stageIndex) || 0, 10);
        var stageTotal = parseInt((status && status.stageTotal) || 6, 10);
        var isRunning = !!(status && status.isRunning);
        var currentLabel = (status && status.stageLabel) ? String(status.stageLabel) : '';
        var currentDetail = (status && status.stageDetail) ? String(status.stageDetail) : '';

        syncStageCounter.textContent = stageIndex > 0 && stageTotal > 0
            ? ('Etapa ' + stageIndex + '/' + stageTotal + (currentLabel ? ' - ' + currentLabel : ''))
            : (currentLabel || '');

        syncStageList.innerHTML = syncKnownStages.map(function(stage, idx) {
            var classes = ['sync-stage-item'];
            if (stage.code === currentCode) {
                classes.push('is-active');
            } else if (idx < Math.max(0, stageIndex - 1) || (!isRunning && currentCode === 'done' && stage.code !== 'error')) {
                classes.push('is-done');
            }
            return '<div class="' + classes.join(' ') + '">' + stage.label + '</div>';
        }).join('');

        syncStatus.textContent = currentDetail || (isRunning ? 'Sincronização em andamento...' : syncStatus.textContent);
        syncProgressBar.classList.remove('is-indeterminate');
        syncProgressBar.style.width = getStageWidthByIndex(stageIndex, stageTotal, isRunning, currentCode) + '%';
        if (currentCode === 'done') {
            syncProgressBar.classList.remove('is-indeterminate');
            syncProgressBar.style.background = 'linear-gradient(90deg, #27ae60, #57d67d)';
        } else if (currentCode === 'error') {
            syncProgressBar.classList.remove('is-indeterminate');
            syncProgressBar.style.background = 'linear-gradient(90deg, #ef4444, #f97316)';
        }
    }

    function stopSyncPolling() {
        if (syncStatusPollTimer) {
            clearInterval(syncStatusPollTimer);
            syncStatusPollTimer = null;
        }
    }

    function startSyncPolling() {
        stopSyncPolling();
        syncStatusPollTimer = setInterval(function() {
            if (!syncBusy) {
                stopSyncPolling();
                return;
            }
            fetchSyncStatus()
                .then(function(status) {
                    renderSyncStages(status);
                    if (!status.isRunning) {
                        stopSyncPolling();
                    }
                })
                .catch(function(error) {
                    console.error('Erro ao consultar status do sync:', error);
                });
        }, 1000);
    }

    function setSyncButtons(mode) {
        syncYesBtn.style.display = mode === 'confirm' ? '' : 'none';
        syncNoBtn.style.display = mode === 'confirm' ? '' : 'none';
        syncCancelBtn.style.display = mode === 'progress' ? '' : 'none';
        syncCloseBtn.style.display = mode === 'result' ? '' : 'none';
    }

    function openSyncOverlay() {
        syncOverlay.classList.add('is-open');
        syncOverlay.setAttribute('aria-hidden', 'false');
    }

    function closeSyncOverlay() {
        syncOverlay.classList.remove('is-open');
        syncOverlay.setAttribute('aria-hidden', 'true');
    }

    function stopSyncProgress(finalWidth) {
        if (syncProgressTimer) {
            clearInterval(syncProgressTimer);
            syncProgressTimer = null;
        }
        syncProgressBar.classList.remove('is-indeterminate');
        syncProgressBar.style.width = (typeof finalWidth === 'number' ? finalWidth : 100) + '%';
    }

    function startSyncProgress() {
        stopSyncProgress(12);
        syncProgressBar.classList.remove('is-indeterminate');
    }

    function showSyncConfirm(lastSyncAt, recordsToday) {
        syncTitle.textContent = 'Sincronização já realizada';
        syncMessage.textContent = 'Sincronização já realizada, deseja fazer novamente?';
        syncStatus.textContent = lastSyncAt
            ? 'Última execução hoje em ' + formatSyncDateTime(lastSyncAt) + (recordsToday ? ' (' + recordsToday + ' registros)' : '')
            : '';
        syncStageCounter.textContent = '';
        syncStageList.innerHTML = '';
        stopSyncProgress(100);
        setSyncButtons('confirm');
        openSyncOverlay();
    }

    function showSyncProgress() {
        syncTitle.textContent = 'Sincronização CODI';
        syncMessage.textContent = 'Sincronização em andamento, aguarde...';
        syncStatus.textContent = 'Etapas do backend serão atualizadas aqui em tempo real.';
        renderSyncStages({
            stageCode: 'starting',
            stageLabel: 'Iniciando',
            stageDetail: 'Preparando sincronização CODI.',
            stageIndex: 1,
            stageTotal: 6,
            isRunning: true
        });
        setSyncButtons('progress');
        openSyncOverlay();
    }

    function showSyncResult(title, message, statusMessage, isError) {
        syncTitle.textContent = title;
        syncMessage.textContent = message;
        syncStatus.textContent = statusMessage || '';
        syncProgressBar.style.background = isError
            ? 'linear-gradient(90deg, #ef4444, #f97316)'
            : 'linear-gradient(90deg, #27ae60, #57d67d)';
        stopSyncProgress(100);
        setSyncButtons('result');
        openSyncOverlay();
    }

    function fetchSyncStatus() {
        return fetch('api/sync_codi.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'status' })
        }).then(function(response) {
            return response.json().then(function(data) {
                return { ok: response.ok, data: data };
            });
        }).then(function(result) {
            if (!result.ok || !result.data || !result.data.success) {
                throw new Error((result.data && result.data.message) ? result.data.message : 'Não foi possível verificar a sincronização do dia.');
            }
            return result.data;
        });
    }

    function runManualSync(forceSync) {
        if (syncBusy) {
            return;
        }

        syncBusy = true;
        syncRequestController = new AbortController();
        showSyncProgress();
        startSyncPolling();

        fetch('api/sync_codi.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'sync_today',
                force: !!forceSync
            }),
            signal: syncRequestController.signal
        })
        .then(function(response) {
            return response.json().then(function(data) {
                return { ok: response.ok, data: data };
            });
        })
        .then(function(result) {
            syncBusy = false;
            syncRequestController = null;
            stopSyncPolling();
            stopSyncProgress(100);

            if (result.data && result.data.success) {
                const today = new Date().toISOString().split('T')[0];
                localStorage.setItem('codi_last_sync_date', today);
                renderSyncStages({
                    stageCode: 'done',
                    stageLabel: 'Concluído',
                    stageDetail: result.data.message || 'Sincronização finalizada com sucesso.',
                    stageIndex: syncKnownStages.length,
                    stageTotal: syncKnownStages.length,
                    isRunning: false
                });
                showSyncResult(
                    'Sincronização concluída',
                    'Sincronização concluída!',
                    result.data.message || 'Sincronização finalizada com sucesso.',
                    false
                );
            } else {
                showSyncResult(
                    'Sincronização com erro',
                    'Não foi possível concluir a sincronização.',
                    (result.data && result.data.message) ? result.data.message : 'Falha sem mensagem detalhada.',
                    true
                );
            }
        })
        .catch(function(error) {
            syncBusy = false;
            syncRequestController = null;
            stopSyncPolling();
            stopSyncProgress(100);

            if (error && error.name === 'AbortError') {
                renderSyncStages({
                    stageCode: 'error',
                    stageLabel: 'Cancelado',
                    stageDetail: 'Sincronização cancelada pelo usuário.',
                    stageIndex: 0,
                    stageTotal: syncKnownStages.length,
                    isRunning: false
                });
                showSyncResult(
                    'Sincronização cancelada',
                    'Sincronização cancelada.',
                    'O navegador interrompeu a requisição. Se o backend já havia iniciado, ele pode continuar executando no servidor.',
                    false
                );
                return;
            }

            console.error('Erro:', error);
            showSyncResult(
                'Erro na sincronização',
                'Erro ao sincronizar.',
                (error && error.message) ? error.message : 'Falha inesperada.',
                true
            );
        });
    }

    syncNoBtn.addEventListener('click', function() {
        closeSyncOverlay();
        syncButton.disabled = false;
        syncButton.textContent = 'Sincronizar CODI';
    });

    syncYesBtn.addEventListener('click', function() {
        syncButton.disabled = true;
        syncButton.textContent = 'Sincronizando...';
        runManualSync(true);
    });

    syncCancelBtn.addEventListener('click', function() {
        if (syncRequestController) {
            syncRequestController.abort();
        } else {
            syncBusy = false;
            stopSyncPolling();
            closeSyncOverlay();
            syncButton.disabled = false;
            syncButton.textContent = 'Sincronizar CODI';
        }
    });

    syncCloseBtn.addEventListener('click', function() {
        syncBusy = false;
        stopSyncPolling();
        closeSyncOverlay();
        syncButton.disabled = false;
        syncButton.textContent = 'Sincronizar CODI';
    });

    syncButton.addEventListener('click', function() {
        if (syncBusy) {
            return;
        }

        const btn = this;
        btn.disabled = true;
        btn.textContent = 'Verificando...';

        fetchSyncStatus()
            .then(function(status) {
                btn.textContent = 'Sincronizar CODI';
                if (status.isRunning) {
                    syncBusy = true;
                    showSyncProgress();
                    renderSyncStages(status);
                    startSyncPolling();
                    return;
                }
                if (status.alreadySynced) {
                    showSyncConfirm(status.lastSyncAt, status.recordsToday || 0);
                    return;
                }

                runManualSync(true);
            })
            .catch(function(error) {
                btn.textContent = 'Sincronizar CODI';
                btn.disabled = false;
                showSyncResult(
                    'Verificação indisponível',
                    'Não foi possível verificar a sincronização do dia.',
                    (error && error.message) ? error.message : 'Tente novamente.',
                    true
                );
            });
    });
})();
</script>
</body>
</html>

