<?php
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

// Buscar lista de programações para o seletor
$programacoes = $repo->getAllProgramacoes(100, 0);

// Se um ID específico foi selecionado
$selectedId = isset($_GET['id']) ? (int)$_GET['id'] : null;
$periodStartInput = isset($_GET['data_inicio']) ? trim((string)$_GET['data_inicio']) : '';
$periodEndInput = isset($_GET['data_fim']) ? trim((string)$_GET['data_fim']) : '';
$hasValidPeriodFilter = preg_match('/^\d{4}-\d{2}-\d{2}$/', $periodStartInput) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $periodEndInput);
$isPeriodFilterMode = false;
$schedule = [];
$programacaoInfo = null;
$periodProgramCount = 0;

if ($hasValidPeriodFilter && strtotime($periodStartInput) !== false && strtotime($periodEndInput) !== false) {
    if (strtotime($periodStartInput) > strtotime($periodEndInput)) {
        [$periodStartInput, $periodEndInput] = [$periodEndInput, $periodStartInput];
    }

    $isPeriodFilterMode = true;
    $periodStartSql = $periodStartInput . ' 00:00:00';
    $periodEndSql = $periodEndInput . ' 23:59:59';
    $stmtPeriod = $pdo->prepare("
        SELECT s.*
        FROM sch_linhas s
        INNER JOIN (
            SELECT sch_programa_id, MAX(sch_criado_em) AS max_criado_em
            FROM sch_linhas
            GROUP BY sch_programa_id
        ) latest
            ON latest.sch_programa_id = s.sch_programa_id
           AND latest.max_criado_em = s.sch_criado_em
        WHERE s.sch_inicio_producao <= :periodEnd
          AND s.sch_fim_producao >= :periodStart
        ORDER BY s.sch_inicio_producao ASC, s.sch_programa_id ASC, s.sch_sequencia ASC
    ");
    $stmtPeriod->execute([
        'periodStart' => $periodStartSql,
        'periodEnd' => $periodEndSql,
    ]);
    $schedule = $stmtPeriod->fetchAll(PDO::FETCH_ASSOC);
    if (!empty($schedule)) {
        $periodProgramCount = count(array_unique(array_map(static fn(array $row): int => (int)($row['sch_programa_id'] ?? 0), $schedule)));
    }
} elseif ($selectedId) {
    $programacaoInfo = $repo->getProgramacaoById($selectedId);
    if ($programacaoInfo) {
        $schedule = $repo->getProgramacaoSchedule($selectedId);
    }
} elseif (!empty($programacoes)) {
    $selectedId = (int)$programacoes[0]['prg_id'];
    $programacaoInfo = $programacoes[0];
    $schedule = $repo->getProgramacaoSchedule($selectedId);
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
// Estrategia: buscar o realizado por OP dentro do periodo de cada item do schedule,
// com margem de +7 dias no fim para capturar apontamentos tardios.
// Chave do mapa: op . '|' . sch_inicio_producao (distingue lotes da mesma OP)
$realizadoMap = [];
if (!empty($schedule)) {
    // Coletar todos os periodos e OPs nao-setup de uma vez
    $opsPeriodos = [];
    foreach ($schedule as $schRow) {
        if (strtolower(trim($schRow['sch_tipo'] ?? '')) === 'setup') continue;
        if (empty($schRow['sch_sku']) || empty($schRow['sch_inicio_producao'])) continue;
        $opItem = $assignedOps[(int)($schRow['sch_id'] ?? 0)] ?? 'S/OP';
        if ($opItem === 'S/OP') continue;
        $opsPeriodos[] = [
            'op'     => $opItem,
            'inicio' => date('Y-m-d', strtotime($schRow['sch_inicio_producao'])),
            'fim'    => date('Y-m-d', strtotime($schRow['sch_fim_producao'] . ' +7 days')),
            'chave'  => $opItem . '|' . $schRow['sch_inicio_producao'],
        ];
    }

    // Buscar realizado para cada item individualmente
    $stmtReal = $pdo->prepare("
        SELECT
            SUM(quantidade) as total,
            MIN(inicio_evento) as inicio_real,
            MAX(fim_evento) as fim_real
        FROM realizado_2026_excel
        WHERE ordem_op = ? AND data_evento >= ? AND data_evento <= ?
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
            
            // Cor baseada em cumprimento
            $cor = '#3498db'; // Azul padrão para previsto
            if (!$isSetup) {
                if ($quantidadeRealizada > 0) {
                    if ($percentualCumprimento >= 100) {
                        $cor = '#10b981'; // Verde - cumprimento >= 100%
                    } elseif ($percentualCumprimento >= 80) {
                        $cor = '#f59e0b'; // Laranja - cumprimento 80-99%
                    } else {
                        $cor = '#ef4444'; // Vermelho - cumprimento < 80%
                    }
                }
            }
            
            $taskId = (int)$row['sch_id'];
            $tasks[] = [
                'id' => $taskId,
                'text' => ($isSetup ? "⚙️ SETUP" : "📦 OP " . $op . "\n" . trim($row['sch_descricao'] ?? '-')),
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
                $realStart = $inicioRealizado ?: $start;
                $realEnd = $fimRealizado ?: $realStart;
                $hideRealBar = empty($inicioRealizado) || empty($fimRealizado);
                $tasks[] = [
                    'id' => 'real-' . $taskId,
                    'text' => 'Realizado',
                    'descricao_produto' => trim($row['sch_descricao'] ?? '-'),
                    'start_date' => date('d-m-Y H:i', strtotime($realStart)),
                    'end_date' => date('d-m-Y H:i', strtotime($realEnd)),
                    'color' => '#64748b',
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
                    'hide_real_bar' => $hideRealBar
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
    <title>Gantt PCP - Visualização de Cronograma</title>
    <link rel="stylesheet" href="assets/css/app.css">
    <link rel="stylesheet" href="assets/css/theme.css">
    <!-- DHTMLX Gantt 9.0.6 - versão fixada para evitar quebras por atualização automática -->
    <link rel="stylesheet" href="https://cdn.dhtmlx.com/gantt/9.0/dhtmlxgantt.css">
    <style>
        :root {
            --primary-dark: #2c3e50;
            --setup-color: #e67e22;
            --prod-color: #3498db;
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
        
        /* Estilização interna do DHTMLX Gantt */
        .gantt_task_line { border-radius: 4px; border: none; }
        .pcp-realized-subbar {
            position: absolute;
            left: 4px;
            right: 4px;
            bottom: 3px;
            height: 9px;
            border-radius: 999px;
            background: rgba(255,255,255,0.98);
            border: 1px solid rgba(15, 23, 42, 0.28);
            box-shadow: inset 0 0 0 1px rgba(255,255,255,0.45);
            overflow: hidden;
            pointer-events: none;
            z-index: 8;
        }
        .pcp-realized-subbar-fill {
            height: 100%;
            border-radius: 999px;
            min-width: 0;
            transition: width 0.2s ease;
        }
        .pcp-realized-overlay {
            position: absolute;
            top: 50%;
            left: 6px;
            transform: translateY(-50%);
            font-size: 9px;
            font-weight: bold;
            color: #fff;
            display: flex;
            align-items: center;
            gap: 4px;
            padding: 1px 3px;
            border-radius: 2px;
            white-space: nowrap;
            pointer-events: none;
            z-index: 10;
        }
        .gantt_task_content { font-size: 11px; font-weight: bold; }
        .gantt_grid_head_cell { font-weight: bold; color: #555; }
        .gantt_scale_cell { font-weight: bold; color: #2c3e50; border-right: 1px solid #ebebeb; }
        .gantt_scale_cell.pcp-scale-6h {
            font-size: 10px;
            color: #64748b;
        }
        .gantt_scale_cell.pcp-scale-6h--day-start,
        .gantt_task_cell.pcp-timeline-6h--day-start {
            border-right-color: #94a3b8;
        }
        .gantt_task_cell.pcp-timeline-6h {
            border-right: 1px solid #e2e8f0;
        }

        /* Permitir 2 linhas por raia no grid */
        .gantt_grid_data .gantt_cell,
        .gantt_grid_data .gantt_tree_content {
            white-space: normal !important;
            line-height: 1.15;
        }

        .pcp-grid-op {
            font-weight: 800;
            color: #0f172a;
        }

        .pcp-grid-setup {
            width: 100%;
            text-align: right;
            padding-right: 10px;
            font-weight: 900;
            color: #0f172a;
            line-height: 38px; /* alinha no meio da linha (row_height) */
        }

        .pcp-grid-prod {
            font-size: 10px;
            font-weight: 600;
            color: #64748b;
            margin-top: 1px;
            line-height: 1.1;
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
            background: #e2e8f0 !important;
            border: 1px solid #94a3b8 !important;
            box-shadow: none !important;
            height: 10px !important;
            margin-top: 5px;
            border-radius: 999px !important;
        }
        .gantt_task_line.pcp-task-realizado .gantt_task_content {
            display: none !important;
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

        /* Para SETUP: deixar o "-" bem a direita na coluna */
        .pcp-realizado-cell--setup {
            justify-content: flex-end;
            padding-right: 10px;
            text-align: right;
        }

        .pcp-realizado-setup {
            display: block;
            width: 100%;
            text-align: right;
            color: #94a3b8;
            font-weight: 800;
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
            background: #10b981;
        }

        .pcp-realizado-badge--real {
            min-width: 86px;
        }
        
        /* Forçar visibilidade das barras de scroll - APARECER SEMPRE QUE NECESSÁRIO */
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
        <h1 style="margin:0; font-size: 20px; color: var(--primary-dark);">📊 Gráfico de Sequenciamento do PCP</h1>
        <div style="display: flex; gap: 10px;">
            <button type="button" id="syncCodiBtn" class="btn-home" style="background: #27ae60; cursor: pointer;">🔄 Sincronizar CODI</button>
            <a href="index.php" class="btn-home">← Voltar ao Sistema</a>
        </div>
    </div>
    <div class="controls">
        <form method="GET" id="filterForm" style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
            <strong>Programação:</strong>
            <select name="id" onchange="this.form.submit()">
                <?php foreach ($programacoes as $prg): ?>
                    <option value="<?= $prg['prg_id'] ?>" <?= $selectedId === (int)$prg['prg_id'] ? 'selected' : '' ?>>
                        <?php 
                            $linha = $prg['linha_excel_dominante'] ?: $prg['lin_codigo'] ?: 'S/Linha';
                            $inicio = $prg['inicio_base_cronograma'] ? date('d/m/Y H:i', strtotime($prg['inicio_base_cronograma'])) : 'S/data';
                            $prog = $prg['programacao_criada_em'] ? date('d/m/Y H:i', strtotime($prg['programacao_criada_em'])) : 'S/data';
                            $eff = $prg['prg_eficiencia'] ?? 0;
                            echo "Linha $linha | Início: $inicio | Prog: $prog | Ef: " . $eff . '%';
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
            <span style="color: #666;">Eficiência: <b><?= $programacaoInfo['prg_eficiencia'] ?? 0 ?>%</b> | Início: <b><?= $baseInicio ?></b></span>
        <?php endif; ?>
    </div>
</div>

<div id="gantt_here"></div>

<div class="legend">
    <div class="legend-item"><div class="box" style="background:var(--prod-color)"></div> Produção (SKU)</div>
    <div class="legend-item"><div class="box" style="background:var(--setup-color)"></div> Setup (Troca)</div>
    <div style="margin-left: auto; color: #888; font-style: italic;">* Use as barras de rolagem para navegar no tempo e nos itens.</div>
</div>

<script src="https://cdn.dhtmlx.com/gantt/9.0/dhtmlxgantt.js"></script>
<script>
    // Localização para Português (Deve vir antes da config)
    gantt.i18n.setLocale("pt");
    gantt.plugins({ tooltip: true });

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
            width: 220, 
            tree: true,
            template: function(task) {
                // Simplesmente retornar o text - ele já contém a quebra de linha e descrição
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
                    var realRowClass = "pcp-grid-real" + (periodoRealRow ? "" : " pcp-grid-real--empty");
                    return '<div class="pcp-grid-real-row-title">Realizado</div>' +
                           '<div class="' + realRowClass + '">' + (periodoRealRow ? escapeHtml(periodoRealRow) : 'S/período') + '</div>';
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
            template: function(task) {
                // Se for SETUP, não mostrar
                if(task.text && task.text.indexOf("SETUP") !== -1) {
                    return '<div class="pcp-realizado-cell pcp-realizado-cell--setup"><span class="pcp-realizado-setup">SETUP</span></div>';
                }
                if (String(task.tipo || "").toLowerCase() === "realizado") {
                    var realRow = Number(task.quantidade_realizada || 0);
                    var realBadgeClass = 'pcp-realizado-row-badge' + (realRow > 0 ? '' : ' pcp-realizado-row-badge--empty');
                    return '<div class="pcp-realizado-cell"><span class="' + realBadgeClass + '">' + realRow.toFixed(0) + '</span></div>';
                }
                
                var prev = task.quantidade_prevista || 0;
                var real = task.quantidade_realizada || 0;
                var pct = task.percentual_cumprimento || 0;
                
                // Cor para o REALIZADO (baseado em porcentagem)
                var bgColorRealizado = '#d1d5db'; // Cinza padrão
                if (real > 0) {
                    bgColorRealizado = pct >= 100 ? '#10b981' : (pct >= 80 ? '#f59e0b' : '#ef4444');
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
        var memoria = escapeHtml(task.memoria_calculo || "Memória de cálculo não disponível.")
            .replace(/\s\|\s/g, "<br>")
            .replace(/\n/g, "<br>");
        var op = escapeHtml(task.op || "S/OP");
        var produto = escapeHtml(task.descricao_produto || "-");
        var sku = escapeHtml(task.sku || "-");
        var tipo = String(task.tipo || "").toLowerCase() === "setup" ? "SETUP" : "Produção";

        return "<div class='pcp-tooltip-title'>" + tipo + "</div>" +
            "<div class='pcp-tooltip-grid'>" +
                "<div class='pcp-tooltip-label'>OP:</div><div>" + op + "</div>" +
                "<div class='pcp-tooltip-label'>Produto:</div><div>" + produto + "</div>" +
                "<div class='pcp-tooltip-label'>SKU:</div><div>" + sku + "</div>" +
                "<div class='pcp-tooltip-label'>Previsto:</div><div>" + prev.toFixed(0) + "</div>" +
                "<div class='pcp-tooltip-label'>Realizado:</div><div>" + real.toFixed(0) + " (" + pct.toFixed(0) + "%)</div>" +
                "<div class='pcp-tooltip-label'>Início:</div><div>" + dateToStr(start) + "</div>" +
                "<div class='pcp-tooltip-label'>Fim:</div><div>" + dateToStr(end) + "</div>" +
            "</div>" +
            "<div class='pcp-tooltip-memory'>" +
                "<div class='pcp-tooltip-memory-label'>Memória de cálculo</div>" +
                "<div class='pcp-tooltip-memory-body'>" + memoria + "</div>" +
            "</div>";
    };
    gantt.templates.task_class = function(start, end, task) {
        if (String(task.tipo || "").toLowerCase() === "realizado") {
            return task.hide_real_bar ? "pcp-task-realizado-hidden" : "pcp-task-realizado";
        }
        return "";
    };

    // CABEÇALHO HIERÁRQUICO (Semanas, Dias e marcações de 6h)
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
            return date.getHours() === 0 ? "pcp-scale-6h pcp-scale-6h--day-start" : "pcp-scale-6h";
        }}
    ];

    // Ajustes de Dimensões para forçar o scroll horizontal
    gantt.config.scale_height = 72;
    // 2 linhas por raia; o realizado agora usa uma raia própria
    gantt.config.row_height = 38;
    gantt.config.min_column_width = 100;
    gantt.config.show_task_cells = true;
    gantt.templates.timeline_cell_class = function(task, date){
        return date.getHours() === 0 ? "pcp-timeline-6h pcp-timeline-6h--day-start" : "pcp-timeline-6h";
    };

    // Inicialização com os dados
    var tasksData = {
        data: <?= json_encode($tasks) ?>
    };

    gantt.init("gantt_here");
    gantt.parse(tasksData);

    // ===== ADICIONAR INFORMAÇÃO REALIZADO SOBRE AS BARRAS =====
    gantt.attachEvent("onAfterTaskRender", function(id, task, div){
        // Pular se for SETUP ou se não houver espaço
        if(task.text && task.text.indexOf("SETUP") !== -1) {
            return;
        }
        if (String(task.tipo || "").toLowerCase() === "realizado") {
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

        // Criar barra visual secundária de realizado abaixo da barra planejada
        var corRealizado = '#6b7280'; // Cinza: sem dados
        if (real > 0) {
            corRealizado = pct >= 100 ? '#10b981' : (pct >= 80 ? '#f59e0b' : '#ef4444');
        }
        var realizedTrack = document.createElement('div');
        realizedTrack.className = 'pcp-realized-subbar';
        var realizedFill = document.createElement('div');
        realizedFill.className = 'pcp-realized-subbar-fill';
        realizedFill.style.backgroundColor = corRealizado;
        realizedFill.style.width = Math.max(0, Math.min(pct, 100)).toFixed(2) + '%';
        realizedTrack.appendChild(realizedFill);

        // Criar overlay com informação - REALIZADO | PREVISTO
        var overlay = document.createElement('div');
        overlay.className = 'pcp-realized-overlay';
        var realSpan = document.createElement('span');
        realSpan.style.backgroundColor = corRealizado;
        realSpan.style.padding = '0 3px';
        realSpan.textContent = real.toFixed(0);
        
        var divider = document.createElement('span');
        divider.style.color = '#999';
        divider.textContent = '|';
        
        var prevSpan = document.createElement('span');
        prevSpan.style.backgroundColor = '#10b981';  // Verde para previsto
        prevSpan.style.padding = '0 3px';
        prevSpan.textContent = prev.toFixed(0);
        
        var pctSpan = document.createElement('span');
        pctSpan.style.color = '#fff';
        pctSpan.style.margin = '0 2px 0 0';
        pctSpan.textContent = '(' + pct.toFixed(0) + '%)';
        
        overlay.appendChild(realSpan);
        overlay.appendChild(divider);
        overlay.appendChild(prevSpan);
        overlay.appendChild(pctSpan);
        
        // Adicionar ao bar
        bar.style.position = 'relative';
        bar.appendChild(realizedTrack);
        bar.appendChild(overlay);
    });

    // ========== SINCRONIZAÇÃO CODI AUTOMÁTICA ==========
    // Verifica se já sincronizou hoje, se não sincroniza automaticamente
    function autoSyncCODI() {
        const today = new Date().toISOString().split('T')[0];
        const lastSyncKey = 'codi_last_sync_date';
        const lastSyncDate = localStorage.getItem(lastSyncKey);
        
        // Se já sincronizou hoje, não faz nada
        if (lastSyncDate === today) {
            console.log('[CODI] Ja sincronizado hoje:', today);
            return;
        }
        
        console.log('[CODI] Iniciando sincronizacao automática...');
        
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
    
    // Executar auto-sync quando página carrega
    autoSyncCODI();

    // ========== SINCRONIZAÇÃO CODI (BOTÃO MANUAL) ==========
    document.getElementById('syncCodiBtn').addEventListener('click', function() {
        const btn = this;
        btn.disabled = true;
        btn.textContent = '⏳ Sincronizando...';
        
        fetch('api/sync_codi.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                action: 'sync_yesterday',
                force: true
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const today = new Date().toISOString().split('T')[0];
                localStorage.setItem('codi_last_sync_date', today);
                alert('✅ Sincronização concluída!\n\n' + data.message);
                btn.textContent = '🔄 Sincronizar CODI';
                btn.disabled = false;
            } else {
                alert('⚠️ ' + data.message);
                btn.textContent = '🔄 Sincronizar CODI';
                btn.disabled = false;
            }
        })
        .catch(error => {
            console.error('Erro:', error);
            alert('❌ Erro ao sincronizar: ' + error.message);
            btn.textContent = '🔄 Sincronizar CODI';
            btn.disabled = false;
        });
    });
</script>
</body>
</html>
