<?php declare(strict_types=1);

require __DIR__ . '/../controlepcp/src/bootstrap.php';

use App\Auth\Auth;

Auth::startSession();
Auth::requireLogin();

$isSandbox = (getenv('APP_ENV') ?: '') === 'sandbox';
if (!$isSandbox) {
    http_response_code(403);
    die('Este relatório está disponível apenas no ambiente SANDBOX.');
}

// ===================================================================
// SISTEMA DE LOGS
// ===================================================================

$logFile = sys_get_temp_dir() . '/previstorealizado_debug.log';
$logs = [];

function addLog($msg, $type = 'info') {
    global $logs, $logFile;
    $time = date('H:i:s');
    $entry = "[$time] [$type] $msg\n";
    $logs[] = ['time' => $time, 'type' => $type, 'msg' => $msg];
    @file_put_contents($logFile, $entry, FILE_APPEND);
}

addLog('===== INICIALIZAÇÃO DO RELATÓRIO =====', 'info');
addLog('IP: ' . ($_SERVER['REMOTE_ADDR'] ?? 'desconhecido'), 'info');

// ===================================================================
// CONEXÃO E EXTRAÇÃO DE DADOS
// ===================================================================

try {
    $pdo = new PDO(
        'mysql:host=localhost;dbname=controlepcp_sandbox',
        'root',
        'k7m2y9u4'
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    addLog('Conectado ao banco controlepcp_sandbox', 'success');
} catch (Exception $e) {
    addLog('ERRO ao conectar ao banco: ' . $e->getMessage(), 'error');
    http_response_code(500);
    die('Erro ao conectar ao banco: ' . htmlspecialchars($e->getMessage()));
}

// Extrair PREVISTO do banco
addLog('Iniciando extração PREVISTO...', 'info');
$previsto = [];
$total_planejado = 0;
try {
    $stmt = $pdo->query("
        SELECT pi.prg_itens_op as op, SUM(pi.prg_quantidade) as qtd, COUNT(*) as itens
        FROM prg_itens pi
        WHERE pi.prg_itens_op IS NOT NULL AND pi.prg_itens_op != ''
        GROUP BY pi.prg_itens_op
    ");
    foreach ($stmt->fetchAll() as $row) {
        $op = (string)$row['op'];
        $previsto[$op] = ['qtd' => (float)$row['qtd'], 'itens' => (int)$row['itens']];
        $total_planejado += $row['qtd'];
    }
    addLog('PREVISTO: ' . count($previsto) . ' OPs extraídas, total = ' . number_format($total_planejado, 2), 'success');
} catch (Exception $e) {
    addLog('ERRO ao extrair PREVISTO: ' . $e->getMessage(), 'error');
}

// Extrair REALIZADO do banco MySQL (importado do Excel)
addLog('Iniciando extração REALIZADO do banco...', 'info');
$realizado = [];
$total_realizado = 0;
try {
    $stmt = $pdo->query("
        SELECT ordem_op as op, SUM(quantidade) as qtd
        FROM realizado_2026_excel
        GROUP BY ordem_op
    ");
    foreach ($stmt->fetchAll() as $row) {
        $op = (string)$row['op'];
        $realizado[$op] = (float)$row['qtd'];
        $total_realizado += $row['qtd'];
    }
    addLog('REALIZADO: ' . count($realizado) . ' OPs extraídas, total = ' . number_format($total_realizado, 2), 'success');
} catch (Exception $e) {
    addLog('ERRO ao extrair REALIZADO: ' . $e->getMessage(), 'error');
}

// Merge e cálculos
$comparativo = [];
$stats = ['cumprida' => 0, 'excedida' => 0, 'nao_cumprida' => 0, 'so_previsto' => 0, 'so_realizado' => 0];

foreach (array_unique(array_merge(array_keys($previsto), array_keys($realizado))) as $op) {
    $op_norm = (string)(int)$op;
    $prev = $previsto[$op_norm]['qtd'] ?? 0;
    $real = $realizado[$op_norm] ?? 0;
    $diff = $real - $prev;
    $pct = ($prev > 0) ? ($real / $prev) * 100 : 0;
    
    if ($prev > 0 && $real > 0) {
        if ($pct > 100) {
            $status = 'Excedida';
            $stats['excedida']++;
        } elseif ($pct == 100) {
            $status = 'Cumprida';
            $stats['cumprida']++;
        } else {
            $status = 'Não Cumprida';
            $stats['nao_cumprida']++;
        }
    } elseif ($prev > 0) {
        $status = 'Só Previsto';
        $stats['so_previsto']++;
    } else {
        $status = 'Só Realizado';
        $stats['so_realizado']++;
    }
    
    $comparativo[$op_norm] = compact('op_norm', 'prev', 'real', 'diff', 'pct', 'status');
}

ksort($comparativo);
$total_diff = $total_realizado - $total_planejado;
$pct_medio = count($comparativo) > 0 ? array_sum(array_column($comparativo, 'pct')) / count($comparativo) : 0;

// ===================================================================
// ETAPA 3: VALIDAÇÃO DO MERGE
// ===================================================================
addLog('=== ETAPA 3: VALIDAÇÃO DO MERGE ===', 'info');
addLog('Total de OPs após merge: ' . count($comparativo), 'info');
addLog('  - Com Previsto: ' . count(array_filter($previsto)), 'info');
addLog('  - Com Realizado: ' . count(array_filter($realizado)), 'info');

addLog('Breakdown de Status:', 'info');
addLog('  - Cumprida (100%): ' . (int)(count($comparativo) - $stats['excedida'] - $stats['nao_cumprida'] - $stats['so_previsto'] - $stats['so_realizado']), 'info');
addLog('  - Excedida (>100%): ' . $stats['excedida'], 'info');
addLog('  - Não Cumprida (<100%): ' . $stats['nao_cumprida'], 'info');
addLog('  - Só Previsto (sem realizado): ' . $stats['so_previsto'], 'info');
addLog('  - Só Realizado (sem previsto): ' . $stats['so_realizado'], 'info');

addLog('Totais Validados:', 'info');
addLog('  - Total Planejado: ' . number_format($total_planejado, 2), 'success');
addLog('  - Total Realizado: ' . number_format($total_realizado, 2), 'success');
addLog('  - Diferença: ' . number_format($total_diff, 2), 'info');
addLog('  - % Médio: ' . number_format($pct_medio, 2) . '%', 'info');
addLog('=== ETAPA 3: MERGE VALIDADO COM SUCESSO ===', 'success');

// Preparar dados para gráficos
$chart_prev_real = ['labels' => [], 'previsto' => [], 'realizado' => []];
$chart_status = ['cumprida' => $stats['cumprida'], 'excedida' => $stats['excedida'], 'nao_cumprida' => $stats['nao_cumprida'], 'so_previsto' => $stats['so_previsto'], 'so_realizado' => $stats['so_realizado']];
$chart_perf = ['0-50%' => 0, '50-100%' => 0, '100%+' => 0];

foreach ($comparativo as $d) {
    if ($d['prev'] > 0 && $d['real'] > 0) {
        $chart_prev_real['labels'][] = $d['op_norm'];
        $chart_prev_real['previsto'][] = $d['prev'];
        $chart_prev_real['realizado'][] = $d['real'];
    }
    if ($d['pct'] < 50) $chart_perf['0-50%']++;
    elseif ($d['pct'] < 100) $chart_perf['50-100%']++;
    else $chart_perf['100%+']++;
}

// Limitar gráfico a top 15 para clareza
if (count($chart_prev_real['labels']) > 15) {
    $chart_prev_real['labels'] = array_slice($chart_prev_real['labels'], 0, 15);
    $chart_prev_real['previsto'] = array_slice($chart_prev_real['previsto'], 0, 15);
    $chart_prev_real['realizado'] = array_slice($chart_prev_real['realizado'], 0, 15);
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Comparativo: Previsto vs Realizado</title>
    <script src="https://cdn.jsdelivr.net/npm/apexcharts@latest"></script>
    <style>
        :root {
            --bg: #f5efe4;
            --panel: rgba(255, 251, 245, 0.92);
            --ink: #21322a;
            --muted: #5f6e64;
            --line: rgba(33, 50, 42, 0.12);
            --primary: #1f6a5a;
            --secondary: #c97f2d;
            --danger: #a94732;
            --radius: 12px;
            --font: "Trebuchet MS", "Segoe UI", sans-serif;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: var(--font);
            color: var(--ink);
            background: linear-gradient(180deg, #f9f4eb 0%, #f5efe4 55%, #efe5d6 100%);
            min-height: 100vh;
            padding: 24px;
        }
        body[data-app-env="sandbox"]::after {
            content: "AMBIENTE SANDBOX";
            position: fixed;
            top: 10px; right: 14px; z-index: 99999;
            padding: 6px 10px; border-radius: 999px;
            background: rgba(245, 158, 11, 0.96); color: #111827;
            border: 1px solid rgba(120, 53, 15, 0.25);
            font-size: 12px; font-weight: 800; letter-spacing: 0.08em;
            text-transform: uppercase; pointer-events: none;
        }
        .container {
            max-width: 1400px; margin: 0 auto;
            background: var(--panel); padding: 32px;
            border-radius: var(--radius);
            box-shadow: 0 18px 50px rgba(79, 62, 36, 0.12);
        }
        .breadcrumb {
            font-size: 13px; color: var(--muted);
            margin-bottom: 24px; text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .breadcrumb a { color: var(--primary); text-decoration: none; }
        h1, h2 { margin: 0; color: var(--ink); }
        h1 { font-size: 32px; margin-bottom: 8px; }
        .header-meta {
            font-size: 14px; color: var(--muted);
            margin-bottom: 32px;
        }
        .stats {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 16px; margin: 32px 0;
        }
        .stat-box {
            background: white; padding: 20px; border-radius: var(--radius);
            border-left: 4px solid var(--primary);
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        }
        .stat-box h3 {
            margin: 0 0 8px 0; font-size: 12px;
            text-transform: uppercase; color: var(--muted); letter-spacing: 0.05em;
        }
        .stat-box .value {
            font-size: 28px; font-weight: bold; color: var(--ink);
        }
        .stat-box.accent { border-left-color: var(--secondary); }
        .stat-box.danger { border-left-color: var(--danger); }
        .charts {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 16px; margin: 32px 0;
        }
        .chart-container {
            background: white; padding: 20px; border-radius: var(--radius);
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        }
        .chart-container h3 {
            margin: 0 0 16px 0; font-size: 16px; color: var(--ink);
        }
        .chart-container > div { height: 300px; }
        .line-filter {
            background: white; padding: 16px; border-radius: var(--radius);
            box-shadow: 0 2px 8px rgba(0,0,0,0.04); margin-bottom: 24px;
            display: flex; flex-direction: column; gap: 8px;
        }
        .line-filter label {
            font-size: 12px; text-transform: uppercase; letter-spacing: 0.08em;
            color: var(--muted);
        }
        .line-filter select {
            padding: 12px; border-radius: 6px; border: 1px solid #d1d5db;
            font-family: var(--font); font-size: 14px;
        }
        .sequence-section {
            background: white; border-radius: var(--radius); padding: 20px 20px 16px;
            margin-top: 16px; box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        .sequence-meta {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 16px; margin-bottom: 16px;
        }
        .sequence-period {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 12px;
            margin-bottom: 12px;
        }
        .sequence-period label {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--muted);
        }
        .sequence-period input {
            padding: 8px 10px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 13px;
        }
        .sequence-period button {
            padding: 8px 14px;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
        }
        .sequence-meta-label {
            font-size: 11px; text-transform: uppercase;
            letter-spacing: 0.08em; color: var(--muted);
        }
        .sequence-meta-value {
            font-size: 16px; font-weight: 600; color: var(--ink);
        }
        .sequence-calendar {
            margin-bottom: 4px;
            border-radius: 8px;
            background: #f7f8fb;
            padding: 10px 14px;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        .sequence-calendar-week {
            font-size: 12px;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            color: var(--muted);
            display: flex;
            justify-content: space-between;
        }
        .sequence-calendar-days {
            display: flex;
            gap: 12px;
            overflow-x: auto;
            padding-bottom: 4px;
        }
        .sequence-calendar-day {
            flex: 0 0 auto;
            min-width: 110px;
            padding: 8px;
            border-radius: 6px;
            background: white;
            box-shadow: inset 0 0 0 1px rgba(31,106,90,0.06);
            text-align: left;
            line-height: 1.3;
        }
        .sequence-calendar-restore {
            align-self: flex-end;
            padding: 4px 12px;
            font-size: 12px;
            border-radius: 999px;
            border: 1px solid rgba(31,106,90,0.2);
            background: white;
            color: var(--primary);
            cursor: pointer;
            text-transform: uppercase;
            letter-spacing: 0.08em;
        }
        .sequence-calendar-restore:hover {
            border-color: rgba(31,106,90,0.6);
            background: #fff;
        }
        .sequence-calendar-day strong {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: var(--ink);
        }
        .sequence-calendar-day small {
            display: block;
            font-size: 12px;
            text-transform: lowercase;
            color: var(--muted);
        }
        .sequence-calendar-day.weekend {
            background: #fef4e6;
        }
        .sequence-calendar-day.active {
            border-color: var(--primary);
            box-shadow: inset 0 0 0 1px var(--primary);
        }
        .sequence-placeholder {
            border: 1px dashed #cbd5f5; border-radius: var(--radius);
            padding: 42px 24px; text-align: center; color: var(--muted);
            display: flex; align-items: center; justify-content: center;
            background: #fdfcff;
        }
        .sequence-wrapper {
            display: none;
            margin-top: 0;
        }
        .sequence-legend {
            display: flex; gap: 20px; margin-bottom: 16px; flex-wrap: wrap;
        }
        .legend-chip {
            display: flex; align-items: center; gap: 8px;
            font-size: 14px; color: var(--ink);
        }
        .legend-dot {
            width: 14px; height: 14px; border-radius: 999px;
            display: inline-block;
        }
        .legend-dot.previsto { background: #f97316; }
        .legend-dot.realizado { background: #16a34a; }
        .legend-value {
            font-weight: 600; font-size: 15px;
        }
        .status-cards {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 12px; margin: 24px 0;
        }
        .status-card {
            background: white; padding: 16px; border-radius: var(--radius);
            text-align: center; box-shadow: 0 2px 8px rgba(0,0,0,0.04);
            cursor: pointer; transition: all 0.3s;
        }
        .status-card:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.08); }
        .status-card h4 {
            margin: 0 0 8px 0; font-size: 12px; color: var(--muted);
            text-transform: uppercase; letter-spacing: 0.05em;
        }
        .status-card .value {
            font-size: 24px; font-weight: bold;
        }
        .status-card.cumprida { border-top: 3px solid #28a745; }
        .status-card.cumprida .value { color: #28a745; }
        .status-card.excedida { border-top: 3px solid #0c5460; }
        .status-card.excedida .value { color: #0c5460; }
        .status-card.nao-cumprida { border-top: 3px solid var(--danger); }
        .status-card.nao-cumprida .value { color: var(--danger); }
        .status-card.so-previsto { border-top: 3px solid var(--secondary); }
        .status-card.so-previsto .value { color: var(--secondary); }
        .status-card.so-realizado { border-top: 3px solid var(--muted); }
        .status-card.so-realizado .value { color: var(--muted); }
        .table-section {
            margin-top: 32px;
        }
        .table-controls {
            display: flex; gap: 12px; margin-bottom: 16px; flex-wrap: wrap;
        }
        .search-box, .filter-btn {
            padding: 10px 14px; border: 1px solid var(--line);
            border-radius: var(--radius); font-family: var(--font);
            background: white; cursor: pointer;
            transition: all 0.3s;
        }
        .search-box:focus { outline: none; border-color: var(--primary); }
        .filter-btn { background: white; color: var(--ink); }
        .filter-btn.active { background: var(--primary); color: white; }
        .filter-btn:hover { background: var(--primary); color: white; }
        table {
            width: 100%; border-collapse: collapse;
            background: white; border-radius: var(--radius);
            overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        }
        th {
            background: var(--ink); color: white; padding: 14px;
            font-size: 13px; text-transform: uppercase; letter-spacing: 0.05em;
            text-align: left; font-weight: 600;
        }
        td {
            padding: 12px 14px; border-bottom: 1px solid var(--line);
            font-size: 14px;
        }
        tr:hover { background: rgba(31, 106, 90, 0.02); }
        .op-cell { font-weight: 600; color: var(--primary); }
        .num { text-align: right; font-variant-numeric: tabular-nums; }
        .status-badge {
            display: inline-block; padding: 4px 10px; border-radius: 4px;
            font-size: 12px; font-weight: 600; text-transform: capitalize;
        }
        .status-cumprida { background: #d4edda; color: #155724; }
        .status-excedida { background: #d1ecf1; color: #0c5460; }
        .status-nao-cumprida { background: #f8d7da; color: #721c24; }
        .status-so-previsto { background: #fff3cd; color: #856404; }
        .status-so-realizado { background: #e2e3e5; color: #383d41; }
        .positive { color: #28a745; }
        .negative { color: var(--danger); }
        .pagination {
            margin-top: 16px; text-align: center; padding-top: 16px;
            border-top: 1px solid var(--line);
        }
        .pagination button {
            padding: 6px 12px; margin: 0 4px; background: white;
            border: 1px solid var(--line); border-radius: 4px;
            cursor: pointer; font-family: var(--font);
        }
        .pagination button:hover { background: var(--primary); color: white; }
        .no-results {
            text-align: center; padding: 40px 20px;
            color: var(--muted); background: white;
            border-radius: var(--radius);
        }
    </style>
</head>
<body data-app-env="<?= $isSandbox ? 'sandbox' : 'production' ?>">
    <div class="container">
        <div class="breadcrumb">
            <a href="/">Controle PCP</a> / <a href="/">Relatórios</a> / Previsto vs Realizado
        </div>
        
        <h1>📊 Comparativo: Previsto vs Realizado</h1>
        <div class="header-meta">
            <strong>Período:</strong> Março-Abril 2026 | <strong>Total de OPs:</strong> <?= count($comparativo) ?> | <strong>Gerado em:</strong> <?= date('d/m/Y H:i:s') ?>
        </div>

        <!-- SELEÇÃO DE PROGRAMAÇÃO -->
        <div style="background: white; padding: 16px; border-radius: 12px; margin-bottom: 24px; box-shadow: 0 2px 8px rgba(0,0,0,0.04); display: none;">
            <label style="display: block; color: #5f6e64; font-size: 12px; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 8px;">
                📋 Filtrar por Programação de Linha
            </label>
            <select id="programacaoSelect" style="width: 100%; padding: 12px; border: 1px solid #1f6a5a; border-radius: 6px; font-family: Trebuchet MS; font-size: 14px; cursor: pointer;">
                <option value="">-- Todas as Programações --</option>
            </select>
            <div style="margin-top: 8px; font-size: 12px; color: #5f6e64;">
                Selecione uma programação para filtrar os dados de Previsto vs Realizado
            </div>
        </div>

        <!-- RESUMO ESTATÍSTICO -->

        <div class="line-filter">
            <label for="lineSelect">Programação calculada para o gráfico de sequenciamento</label>
            <select id="lineSelect">
                <option value="">— Selecione uma programação —</option>
            </select>
            <small style="color: var(--muted); font-size: 12px; display: block;">
                Escolha uma programação calculada para mostrar os blocos previstos vs realizado naquela semana.
            </small>
        </div>

        <div class="stats">
            <div class="stat-box">
                <h3>Total Planejado</h3>
                <div class="value" id="valuePlanejado"><?= number_format($total_planejado, 0, ',', '.') ?></div>
            </div>
            <div class="stat-box">
                <h3>Total Realizado</h3>
                <div class="value" id="valueRealizado"><?= number_format($total_realizado, 0, ',', '.') ?></div>
            </div>
            <div class="stat-box accent">
                <h3>Diferença Total</h3>
                <div class="value <?= $total_diff >= 0 ? 'positive' : 'negative' ?>" id="valueDiferenca">
                    <?= ($total_diff >= 0 ? '+' : '') . number_format($total_diff, 0, ',', '.') ?>
                </div>
            </div>
            <div class="stat-box">
                <h3>Percentual Médio</h3>
                <div class="value" id="valuePercentual"><?= number_format($pct_medio, 1, ',', '.') ?>%</div>
            </div>
        </div>

        <div class="sequence-section">
            <div class="sequence-meta">
                <div>
                    <div class="sequence-meta-label">Linha</div>
                    <div class="sequence-meta-value" id="sequenceLineLabel">—</div>
                </div>
                <div>
                    <div class="sequence-meta-label">Semana</div>
                    <div class="sequence-meta-value" id="sequenceWeekLabel">—</div>
                </div>
                <div>
                    <div class="sequence-meta-label">Período</div>
                    <div class="sequence-meta-value" id="sequenceWeekRange">—</div>
                </div>
            </div>
            <div class="sequence-calendar" id="sequenceCalendar">
                <div class="sequence-calendar-week" id="sequenceCalendarWeek">Semana —</div>
                <button type="button" class="sequence-calendar-restore" id="sequenceCalendarRestore">Restaurar semana</button>
                <div class="sequence-calendar-days" id="sequenceCalendarDays"></div>
            </div>
            <div id="sequencePlaceholder" class="sequence-placeholder">
                Selecione uma programação calculada para carregar o gráfico de sequência planejado vs realizado.
            </div>
        <div id="sequenceWrapper" class="sequence-wrapper">
            <div class="sequence-period">
                <label for="filterStart">Período:</label>
                <input type="date" id="filterStart">
                <input type="date" id="filterEnd">
                <button id="applyPeriodBtn">Aplicar</button>
            </div>
            <div class="sequence-legend" id="sequenceLegend">
                <div class="legend-chip">
                    <span class="legend-dot previsto"></span>
                    <span>Previsto: <span class="legend-value" id="legendPrevisto">0</span> un</span>
                </div>
                <div class="legend-chip">
                    <span class="legend-dot realizado"></span>
                    <span>Realizado: <span class="legend-value" id="legendRealizado">0</span> un</span>
                </div>
            </div>
            <div class="chart-container" style="margin-bottom: 0;">
                <div id="sequenceChart" style="height: 360px;"></div>
            </div>
        </div>
        </div>

        <!-- GRÁFICOS -->
        <div class="charts" style="display: none;">
            <div class="chart-container">
                <h3>Distribuição de Status</h3>
                <div id="chartStatus"></div>
            </div>
            <div class="chart-container">
                <h3>Performance por Faixa</h3>
                <div id="chartPerf"></div>
            </div>
        </div>

        <div class="chart-container" style="margin: 16px 0; display: none;">
            <h3>Top 15: Previsto vs Realizado</h3>
            <div id="chartComparison" style="height: 350px;"></div>
        </div>

        <!-- STATUS DAS OPS -->
        <h2 style="margin-top: 32px; margin-bottom: 16px; display: none;">Status das Ordens de Produção</h2>
        <div class="status-cards" style="display: none;">
            <div class="status-card cumprida" onclick="filterByStatus('Cumprida')">
                <h4>Cumprida (=100%)</h4>
                <div class="value"><?= $stats['cumprida'] ?></div>
            </div>
            <div class="status-card excedida" onclick="filterByStatus('Excedida')">
                <h4>Excedida (>100%)</h4>
                <div class="value"><?= $stats['excedida'] ?></div>
            </div>
            <div class="status-card nao-cumprida" onclick="filterByStatus('Não Cumprida')">
                <h4>Não Cumprida (<100%)</h4>
                <div class="value"><?= $stats['nao_cumprida'] ?></div>
            </div>
            <div class="status-card so-previsto" onclick="filterByStatus('Só Previsto')">
                <h4>Só Previsto</h4>
                <div class="value"><?= $stats['so_previsto'] ?></div>
            </div>
            <div class="status-card so-realizado" onclick="filterByStatus('Só Realizado')">
                <h4>Só Realizado</h4>
                <div class="value"><?= $stats['so_realizado'] ?></div>
            </div>
        </div>

        <!-- TABELA COM FILTROS -->
        <div class="table-section" style="display: none;">
            <h2 style="margin-bottom: 16px;">Detalhamento por OP</h2>
            <div class="table-controls">
                <input type="text" id="searchInput" class="search-box" placeholder="Buscar OP..." />
                <button class="filter-btn active" onclick="filterByStatus('')">Todas</button>
                <button class="filter-btn" onclick="filterByStatus('Cumprida')">Cumprida</button>
                <button class="filter-btn" onclick="filterByStatus('Excedida')">Excedida</button>
                <button class="filter-btn" onclick="filterByStatus('Não Cumprida')">Não Cumprida</button>
                <button class="filter-btn" onclick="filterByStatus('Só Previsto')">Só Previsto</button>
                <button class="filter-btn" onclick="filterByStatus('Só Realizado')">Só Realizado</button>
            </div>
            
            <div id="tableContainer"></div>
            <div id="pagination" class="pagination"></div>
        </div>
    </div>

    <script>
        // DADOS
        const dados = <?= json_encode(array_values($comparativo)) ?>;
        const dadosOriginais = JSON.parse(JSON.stringify(dados));
        const statsOriginais = {
            cumprida: <?= $stats['cumprida'] ?>,
            excedida: <?= $stats['excedida'] ?>,
            nao_cumprida: <?= $stats['nao_cumprida'] ?>,
            so_previsto: <?= $stats['so_previsto'] ?>,
            so_realizado: <?= $stats['so_realizado'] ?>
        };
        const globalSummary = {
            total_previsto: <?= $total_planejado ?>,
            total_realizado: <?= $total_realizado ?>,
            diferenca: <?= $total_diff ?>,
            percentual: <?= $pct_medio ?>
        };
        const numberFormatter = new Intl.NumberFormat('pt-BR', { maximumFractionDigits: 0 });
        const lineSelect = document.getElementById('lineSelect');
        const sequencePlaceholder = document.getElementById('sequencePlaceholder');
        const sequenceWrapper = document.getElementById('sequenceWrapper');
        const sequenceLineLabel = document.getElementById('sequenceLineLabel');
        const sequenceWeekLabel = document.getElementById('sequenceWeekLabel');
        const sequenceWeekRange = document.getElementById('sequenceWeekRange');
        const sequenceCalendarWeek = document.getElementById('sequenceCalendarWeek');
        const sequenceCalendarRestore = document.getElementById('sequenceCalendarRestore');
        const sequenceCalendarDays = document.getElementById('sequenceCalendarDays');
        const valuePlanejadoEl = document.getElementById('valuePlanejado');
        const valueRealizadoEl = document.getElementById('valueRealizado');
        const valueDiferencaEl = document.getElementById('valueDiferenca');
          const valuePercentualEl = document.getElementById('valuePercentual');
          const legendPrevistoEl = document.getElementById('legendPrevisto');
          const legendRealizadoEl = document.getElementById('legendRealizado');
          const filterStartInput = document.getElementById('filterStart');
          const filterEndInput = document.getElementById('filterEnd');
          const applyPeriodBtn = document.getElementById('applyPeriodBtn');
        let sequenceChart = null;
        let currentFilter = '';
        let currentPage = 1;
        let selectedProgram = null;
        const itemsPerPage = 20;
        const weekdayShortMap = ['Dom','Seg','Ter','Qua','Qui','Sex','Sáb'];
        let selectedCalendarDate = '';
        let currentCalendarRange = { start: '', end: '' };
        let baseCalendarRange = { start: '', end: '' };

        function formatNumber(value) {
            return numberFormatter.format(Math.round(value || 0));
        }

        function updateSequenceLegend(prev, real) {
            if (legendPrevistoEl) legendPrevistoEl.textContent = formatNumber(prev);
            if (legendRealizadoEl) legendRealizadoEl.textContent = formatNumber(real);
        }

        function updateSummaryCards(summary) {
            const prev = summary.total_previsto ?? 0;
            const real = summary.total_realizado ?? 0;
            const diff = typeof summary.diferenca === 'number' ? summary.diferenca : real - prev;
            const pct = prev > 0 ? (real / prev) * 100 : 0;

            valuePlanejadoEl.textContent = formatNumber(prev);
            valueRealizadoEl.textContent = formatNumber(real);
            valueDiferencaEl.textContent = `${diff >= 0 ? '+' : ''}${formatNumber(diff)}`;
            valueDiferencaEl.classList.toggle('positive', diff >= 0);
            valueDiferencaEl.classList.toggle('negative', diff < 0);
            valuePercentualEl.textContent = `${pct.toFixed(1)}%`;
        }

        function resetSummaryToGlobal() {
            updateSummaryCards(globalSummary);
        }

        function showSequencePlaceholder(text) {
            sequenceWrapper.style.display = 'none';
            sequencePlaceholder.style.display = 'flex';
            sequencePlaceholder.textContent = text;
        }

          function showSequenceChartContainer() {
              sequencePlaceholder.style.display = 'none';
              sequenceWrapper.style.display = 'block';
          }

          function setPeriodInputs(start, end) {
              if (filterStartInput) filterStartInput.value = start ?? '';
              if (filterEndInput) filterEndInput.value = end ?? '';
          }

          function resetSequenceSection() {
            sequenceLineLabel.textContent = '—';
            sequenceWeekLabel.textContent = '—';
            sequenceWeekRange.textContent = '—';
            resetSummaryToGlobal();
            if (sequenceChart) {
                sequenceChart.destroy();
                sequenceChart = null;
            }
            showSequencePlaceholder('Selecione uma programação calculada para carregar o gráfico de sequência planejado vs realizado.');
              if (legendPrevistoEl) legendPrevistoEl.textContent = '0';
              if (legendRealizadoEl) legendRealizadoEl.textContent = '0';
            if (filterStartInput) filterStartInput.value = '';
            if (filterEndInput) filterEndInput.value = '';
            if (sequenceCalendarWeek) sequenceCalendarWeek.textContent = 'Semana —';
            if (sequenceCalendarDays) sequenceCalendarDays.innerHTML = '';
            selectedCalendarDate = '';
            currentCalendarRange = { start: '', end: '' };
            baseCalendarRange = { start: '', end: '' };
        }

        function parseDateIso(value) {
            if (!value) {
                return null;
            }
            const normalized = value.replace(' ', 'T');
            const date = new Date(normalized);
            return isNaN(date.getTime()) ? null : date;
        }

        function formatDateTime(ms) {
            if (!ms) return '';
            return new Date(ms).toLocaleString('pt-BR', {
                day: '2-digit',
                month: '2-digit',
                hour: '2-digit',
                minute: '2-digit'
            });
        }

        function formatChartDayLabel(value) {
            const date = new Date(value);
            if (isNaN(date.getTime())) return '';
            const dayMonth = date.toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit' });
            const weekday = weekdayShortMap[date.getDay()]?.toLowerCase() ?? '';
            return weekday ? `${dayMonth} ${weekday}` : dayMonth;
        }

        function normalizeCalendarDate(value) {
            if (!value) {
                return null;
            }
            const normalized = value.length === 10 ? `${value}T00:00:00` : value;
            return parseDateIso(normalized);
        }

        function renderSequenceCalendar(startIso, endIso, weekLabel) {
            if (!sequenceCalendarWeek || !sequenceCalendarDays) {
                return;
            }
            sequenceCalendarWeek.textContent = weekLabel || 'Semana —';
            sequenceCalendarDays.innerHTML = '';
            const startDate = normalizeCalendarDate(startIso);
            const endDate = normalizeCalendarDate(endIso);
            if (!startDate || !endDate || endDate < startDate) {
                return;
            }

            currentCalendarRange.start = startDate.toISOString().slice(0, 10);
            currentCalendarRange.end = endDate.toISOString().slice(0, 10);

            const days = [];
            const cursor = new Date(startDate.getFullYear(), startDate.getMonth(), startDate.getDate());
            while (cursor <= endDate) {
                days.push(new Date(cursor));
                cursor.setDate(cursor.getDate() + 1);
            }

            sequenceCalendarDays.innerHTML = days.map(date => {
                const dayMonth = date.toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit' });
                const weekday = weekdayShortMap[date.getDay()]?.toLowerCase() ?? '';
                const isWeekend = date.getDay() === 0 || date.getDay() === 6;
                const isoDate = date.toISOString().slice(0, 10);
                return `
                    <div class="sequence-calendar-day${isWeekend ? ' weekend' : ''}" data-date="${isoDate}">
                        <strong>${dayMonth}</strong>
                        <small>${weekday}</small>
                    </div>
                `;
            }).join('');

            updateCalendarSelection(currentCalendarRange.start);
        }

        function updateCalendarSelection(dateIso) {
            selectedCalendarDate = dateIso || '';
            sequenceCalendarDays?.querySelectorAll('.sequence-calendar-day').forEach(day => {
                day.classList.toggle('active', day.dataset.date === selectedCalendarDate);
            });
        }

        function applyCalendarRange(startIso, endIso) {
            if (!startIso || !endIso) {
                return;
            }
            setPeriodInputs(startIso, endIso);
            if (!lineSelect.value) {
                return;
            }
            carregarSequenciaDeLinha(lineSelect.value, { startDate: startIso, endDate: endIso });
        }

        function handleCalendarDayClick(event) {
            let target = event.target;
            while (target && target.nodeType === 3) {
                target = target.parentElement;
            }
            while (target && (!target.classList || !target.classList.contains('sequence-calendar-day'))) {
                target = target.parentElement;
            }
            if (!target) {
                return;
            }
            const dateIso = target.dataset.date;
            if (!dateIso) {
                return;
            }
            updateCalendarSelection(dateIso);
            applyCalendarRange(dateIso, dateIso);
        }

        function handleCalendarWeekClick() {
            if (!baseCalendarRange.start || !baseCalendarRange.end) {
                return;
            }
            updateCalendarSelection('');
            applyCalendarRange(baseCalendarRange.start, baseCalendarRange.end);
        }

        function handleCalendarRestore() {
            if (!baseCalendarRange.start || !baseCalendarRange.end) {
                return;
            }
            updateCalendarSelection('');
            applyCalendarRange(baseCalendarRange.start, baseCalendarRange.end);
        }

        // Carregar programações
        async function carregarProgramacoes() {
            try {
                const response = await fetch('api_programacoes.php?action=programacoes');
                const result = await response.json();
                
                if (result.success && result.data) {
                    const select = document.getElementById('programacaoSelect');
                    result.data.forEach(prog => {
                        const option = document.createElement('option');
                        option.value = prog.prg_id;
                        const desc = prog.prg_numero_op ? `OP ${prog.prg_numero_op}` : `Prog #${prog.prg_id}`;
                        option.textContent = `${desc} - Linha ${prog.prg_linha_id} (${prog.total_itens} itens)`;
                        select.appendChild(option);
                    });
                }
            } catch (e) {
                console.error('Erro ao carregar programações:', e);
            }
        }

        // Carregar programações calculadas disponíveis
        async function carregarLinhas() {
            try {
                const response = await fetch('api/sequenciamento.php?action=linhas');
                const result = await response.json();

                if (result.sucesso && result.programacoes?.length) {
                    const select = document.getElementById('lineSelect');
                    select.innerHTML = '<option value="">— Selecione uma programação —</option>';
                    result.programacoes.forEach(programacao => {
                        const option = document.createElement('option');
                        option.value = programacao.id;
                        option.textContent = programacao.label;
                        option.dataset.linha = programacao.linha || '';
                        option.dataset.op = programacao.numero_op || '';
                        select.appendChild(option);
                    });
                }
            } catch (e) {
                console.error('Erro ao carregar programações:', e);
            }
        }

        sequenceCalendarDays?.addEventListener('click', handleCalendarDayClick);
        sequenceCalendarWeek?.addEventListener('click', handleCalendarWeekClick);
        sequenceCalendarRestore?.addEventListener('click', handleCalendarRestore);

        lineSelect.addEventListener('change', () => {
              const programacaoId = lineSelect.value;
              if (!programacaoId) {
                  resetSequenceSection();
                  return;
              }
              carregarSequenciaDeLinha(programacaoId);
          });

          applyPeriodBtn?.addEventListener('click', () => {
              const programacaoId = lineSelect.value;
              if (!programacaoId) {
                  return;
              }
              const filters = {};
              if (filterStartInput?.value) {
                  filters.startDate = filterStartInput.value;
              }
              if (filterEndInput?.value) {
                  filters.endDate = filterEndInput.value;
              }
              carregarSequenciaDeLinha(programacaoId, filters);
          });

          async function carregarSequenciaDeLinha(programacaoId, filters = {}) {
              showSequencePlaceholder('Carregando dados da programação selecionada...');
              try {
                  const params = new URLSearchParams({ action: 'sequenciaLinha', programacao_id: programacaoId });
                  if (filters.startDate) params.append('start_date', filters.startDate);
                  if (filters.endDate) params.append('end_date', filters.endDate);
                  const response = await fetch(`api/sequenciamento.php?${params.toString()}`);
                  const result = await response.json();

                  if (!result.sucesso) {
                      throw new Error(result.erro || 'Resposta inválida');
                  }

                  const fallbackLabel = result.lin_codigo ? `Linha ${result.lin_codigo}` : `Programação ${programacaoId}`;
                  sequenceLineLabel.textContent = result.programacao_label ?? fallbackLabel;
                  sequenceWeekLabel.textContent = result.week_label ?? '—';
                  sequenceWeekRange.textContent = result.week_range ?? '—';
                  updateSummaryCards({
                      total_previsto: result.resumo?.total_previsto ?? 0,
                      total_realizado: result.resumo?.total_realizado ?? 0,
                      diferenca: result.resumo?.diferenca ?? (result.resumo?.total_realizado ?? 0) - (result.resumo?.total_previsto ?? 0)
                  });

                  const rangeStart = result.start_range ?? result.start_date_filter ?? '';
                  const rangeEnd = result.end_range ?? result.end_date_filter ?? '';
                  const currentWeekLabel = result.week_label ?? 'Semana —';
                  if (!filters.startDate && !filters.endDate) {
                      baseCalendarRange = { start: rangeStart, end: rangeEnd };
                  }
                  renderSequenceCalendar(rangeStart, rangeEnd, currentWeekLabel);
                  setPeriodInputs(rangeStart, rangeEnd);
                  if (!result.blocos?.length) {
                      showSequencePlaceholder('Nenhum bloco registrado para esta linha nesta semana.');
                      return;
                  }

                  renderSequenceChart(result.blocos);
              } catch (error) {
                  console.error('Erro ao carregar sequência:', error);
                  showSequencePlaceholder('Não foi possível carregar o gráfico desta linha.');
              }
          }

function truncateText(text, limit = 70) {
    if (!text) return '';
    const normalizedLimit = Math.max(10, limit);
    return text.length > normalizedLimit ? `${text.slice(0, normalizedLimit - 3)}...` : text;
}

        function mergeAdjacentBlocks(blocks, gapMs = 15 * 60 * 1000) {
            const merged = [];
            blocks.forEach(block => {
                const existing = merged[merged.length - 1];
                if (existing
                    && existing.label === block.label
                    && block.startMs <= existing.endMs + gapMs
                    && block.startMs >= existing.startMs
                ) {
                    existing.endMs = Math.max(existing.endMs, block.endMs);
                    existing.previsto += block.previsto;
                    existing.realizado += block.realizado;
                } else {
                    merged.push({ ...block });
                }
            });
            return merged;
        }

        function renderSequenceChart(blocks) {
            const groupedBlocks = new Map();

            blocks.forEach(block => {
                const startDate = parseDateIso(block.start);
                const endDate = parseDateIso(block.end);
                if (!startDate || !endDate) {
                    return;
                }

                const dateLabel = block.date_label ?? '';
                const weekdayValue = (block.weekday_abbrev || block.day || '').toString().trim();
                const weekdayLabel = weekdayValue ? weekdayValue.toLowerCase() : '';
                const productLabel = block.description || block.op || 'Detalhe';
                const prefixParts = [dateLabel, weekdayLabel].filter(Boolean);
                const labelPrefix = prefixParts.join(' ');
                const truncatedDescription = truncateText(productLabel, 80);
                const labelKey = labelPrefix ? `${labelPrefix} · ${truncatedDescription}` : truncatedDescription;
                const startMs = startDate.getTime();
                const endMs = endDate.getTime();

                if (!groupedBlocks.has(labelKey)) {
                    groupedBlocks.set(labelKey, {
                        label: labelKey,
                        startMs,
                        endMs,
                        previsto: block.previsto ?? 0,
                        realizado: block.realizado ?? 0
                    });
                } else {
                    const existing = groupedBlocks.get(labelKey);
                    existing.startMs = Math.min(existing.startMs, startMs);
                    existing.endMs = Math.max(existing.endMs, endMs);
                    existing.previsto += block.previsto ?? 0;
                    existing.realizado += block.realizado ?? 0;
                }
            });

            const aggregated = Array.from(groupedBlocks.values()).sort((a, b) => a.startMs - b.startMs);
            const smoothed = mergeAdjacentBlocks(aggregated);

            const plannedData = [];
            const realizedData = [];

            smoothed.forEach(block => {
                const durationMs = Math.max(60000, block.endMs - block.startMs);
                const ratio = block.previsto > 0 ? (block.realizado / block.previsto) : (block.realizado > 0 ? 1 : 0.1);
                const clampedRatio = Math.min(Math.max(ratio, 0.1), 2);
                const realizedDuration = Math.max(60000, durationMs * clampedRatio);
                const realizedEndMs = block.startMs + realizedDuration;

                plannedData.push({
                    x: block.label,
                    y: [block.startMs, block.endMs],
                    meta: block
                });
                realizedData.push({
                    x: block.label,
                    y: [block.startMs, realizedEndMs],
                    meta: block
                });
            });

            if (!plannedData.length) {
                showSequencePlaceholder('Não há dados válidos para renderizar este gráfico.');
                return;
            }

            const totalPrevisto = smoothed.reduce((sum, item) => sum + (item.previsto || 0), 0);
            const totalRealizado = smoothed.reduce((sum, item) => sum + (item.realizado || 0), 0);
            updateSequenceLegend(totalPrevisto, totalRealizado);

            const series = [
                { name: 'Previsto', data: plannedData },
                { name: 'Realizado', data: realizedData }
            ];

            const options = {
                chart: {
                    type: 'rangeBar',
                    height: 360,
                    toolbar: { show: false },
                    animations: { enabled: true }
                },
                plotOptions: {
                    bar: { horizontal: true, rangeBarGroupRows: true, barHeight: '60%' }
                },
                colors: ['#f97316', '#16a34a'],
                stroke: { colors: ['#ffffff'], width: 1 },
                dataLabels: { enabled: false },
                legend: { position: 'top' },
                tooltip: {
                    shared: true,
                    custom: ({ dataPointIndex }) => {
                        const dataPoint = plannedData[dataPointIndex] || realizedData[dataPointIndex];
                        const meta = dataPoint?.meta;
                        const previsto = meta?.previsto ?? 0;
                        const realizado = meta?.realizado ?? 0;
                        const start = formatDateTime(meta?.startMs);
                        const end = formatDateTime(meta?.endMs);
                        return `
                            <div class="tooltip-sequence">
                                <strong>${dataPoint?.x || 'Detalhe'}</strong><br>
                                Previsto: ${formatNumber(previsto)} un<br>
                                Realizado: ${formatNumber(realizado)} un
                                ${start && end ? `<br><em>${start} → ${end}</em>` : ''}
                            </div>
                        `;
                    }
                },
                xaxis: {
                    type: 'datetime',
                    tickAmount: Math.max(4, Math.min(8, smoothed.length)),
                    min: smoothed[0]?.startMs ?? undefined,
                    max: smoothed[smoothed.length - 1]?.endMs ?? undefined,
                    tickPlacement: 'between',
                    labels: {
                        formatter: formatChartDayLabel,
                        datetimeUTC: false,
                        style: {
                            fontSize: '13px',
                            fontWeight: 600
                        }
                    }
                },
                yaxis: {
                labels: {
                    align: 'left',
                    offsetX: 0,
                    trim: false,
                    maxWidth: 460,
                    style: {
                        fontSize: '13px',
                        fontWeight: 600,
                        fontFamily: 'Inter, "Trebuchet MS", sans-serif'
                    },
                    formatter: (value) => value
                }
            }
        };

            if (sequenceChart) {
                sequenceChart.updateOptions({ series }, true, true);
            } else {
                sequenceChart = new ApexCharts(document.getElementById('sequenceChart'), { ...options, series });
                sequenceChart.render();
            }

            showSequenceChartContainer();
        }

        // Filtrar por programação
        async function filtrarPorProgramacao(prg_id) {
            if (!prg_id) {
                // Restaurar dados originais
                Object.assign(dados, dadosOriginais);
                selectedProgram = null;
                currentPage = 1;
                currentFilter = '';
                renderTable();
                atualizarGraficos(statsOriginais, <?= $total_planejado ?>, <?= $total_realizado ?>, <?= $pct_medio ?>);
                return;
            }

            try {
                const response = await fetch(`api_programacoes.php?action=filtrar&prg_id=${prg_id}`);
                const result = await response.json();
                
                if (result.success) {
                    selectedProgram = prg_id;
                    
                    // Atualizar cards
                    document.querySelector('.stat-box:nth-child(1) .value').textContent = 
                        result.previsto.total.toLocaleString('pt-BR', {minimumFractionDigits: 0, maximumFractionDigits: 0});
                    document.querySelector('.stat-box:nth-child(2) .value').textContent = 
                        result.realizado.total.toLocaleString('pt-BR', {minimumFractionDigits: 0, maximumFractionDigits: 0});
                    
                    const diffText = (result.diferenca >= 0 ? '+' : '') + result.diferenca.toLocaleString('pt-BR', {minimumFractionDigits: 0, maximumFractionDigits: 0});
                    document.querySelector('.stat-box:nth-child(3) .value').textContent = diffText;
                    
                    document.querySelector('.stat-box:nth-child(4) .value').textContent = 
                        result.percentual.toFixed(1) + '%';
                    
                    // Recalcular gráficos
                    recalcularGraficosPorPrograma(prg_id);
                    
                    currentPage = 1;
                    currentFilter = '';
                    renderTable();
                }
            } catch (e) {
                console.error('Erro ao filtrar programação:', e);
            }
        }

        // Recalcular gráficos por programa
        async function recalcularGraficosPorPrograma(prg_id) {
            try {
                const response = await fetch(`api_programacoes.php?action=filtrar&prg_id=${prg_id}`);
                const result = await response.json();
                
                if (result.success) {
                    // Para simplificar, vamos apenas mostrar a mensagem
                    // Em produção, você recalcularia os arrays dos gráficos
                    atualizarGraficos(
                        {cumprida: 0, excedida: 0, nao_cumprida: 0, so_previsto: 0, so_realizado: 0},
                        result.previsto.total,
                        result.realizado.total,
                        result.percentual
                    );
                }
            } catch (e) {
                console.error('Erro ao recalcular gráficos:', e);
            }
        }

        // Função auxiliar para atualizar gráficos
        function atualizarGraficos(stats, totalPrev, totalReal, pctMedio) {
            // Esta função pode ser expandida conforme necessário
            console.log('Gráficos atualizados para:', {stats, totalPrev, totalReal, pctMedio});
        }

        // GRÁFICO: Status (Pie)
        new ApexCharts(document.getElementById('chartStatus'), {
            series: [<?= $stats['cumprida'] ?>, <?= $stats['excedida'] ?>, <?= $stats['nao_cumprida'] ?>, <?= $stats['so_previsto'] ?>, <?= $stats['so_realizado'] ?>],
            chart: { type: 'donut', sparkline: { enabled: false } },
            labels: ['Cumprida', 'Excedida', 'Não Cumprida', 'Só Previsto', 'Só Realizado'],
            colors: ['#28a745', '#0c5460', '#a94732', '#c97f2d', '#5f6e64'],
        }).render();

        // GRÁFICO: Performance (Bar)
        new ApexCharts(document.getElementById('chartPerf'), {
            series: [{ data: [<?= $chart_perf['0-50%'] ?>, <?= $chart_perf['50-100%'] ?>, <?= $chart_perf['100%+'] ?>] }],
            chart: { type: 'bar', sparkline: { enabled: false } },
            xaxis: { categories: ['0-50%', '50-100%', '100%+'] },
            colors: ['#a94732', '#c97f2d', '#1f6a5a'],
        }).render();

        // GRÁFICO: Comparação (Bar)
        new ApexCharts(document.getElementById('chartComparison'), {
            series: [
                { name: 'Previsto', data: <?= json_encode($chart_prev_real['previsto']) ?> },
                { name: 'Realizado', data: <?= json_encode($chart_prev_real['realizado']) ?> }
            ],
            chart: { type: 'bar', stacked: false },
            xaxis: { categories: <?= json_encode($chart_prev_real['labels']) ?> },
            colors: ['#c97f2d', '#1f6a5a'],
        }).render();

        // FILTRO E TABELA
        function getFilteredData() {
            let filtered = dados;
            if (currentFilter) {
                filtered = filtered.filter(d => d.status === currentFilter);
            }
            const search = document.getElementById('searchInput')?.value.toLowerCase() || '';
            if (search) {
                filtered = filtered.filter(d => d.op_norm.includes(search));
            }
            return filtered;
        }

        function renderTable() {
            const filtered = getFilteredData();
            const start = (currentPage - 1) * itemsPerPage;
            const pageData = filtered.slice(start, start + itemsPerPage);
            
            if (pageData.length === 0) {
                document.getElementById('tableContainer').innerHTML = 
                    '<div class="no-results">Nenhuma OP encontrada com os filtros selecionados</div>';
                document.getElementById('pagination').innerHTML = '';
                return;
            }

            let html = `<table><thead><tr>
                <th>OP</th><th class="num">Previsto</th><th class="num">Realizado</th>
                <th class="num">Diferença</th><th class="num">%</th><th>Status</th>
            </tr></thead><tbody>`;

            pageData.forEach(d => {
                const statusClass = 'status-' + d.status.toLowerCase().replace(/\s+/g, '-');
                html += `<tr>
                    <td class="op-cell">${d.op_norm}</td>
                    <td class="num">${Math.round(d.prev).toLocaleString('pt-BR')}</td>
                    <td class="num">${Math.round(d.real).toLocaleString('pt-BR')}</td>
                    <td class="num ${d.diff >= 0 ? 'positive' : 'negative'}">
                        ${(d.diff >= 0 ? '+' : '') + Math.round(d.diff).toLocaleString('pt-BR')}
                    </td>
                    <td class="num">${d.pct.toFixed(1)}%</td>
                    <td><span class="status-badge ${statusClass}">${d.status}</span></td>
                </tr>`;
            });

            html += '</tbody></table>';
            document.getElementById('tableContainer').innerHTML = html;

            // Paginação
            const totalPages = Math.ceil(filtered.length / itemsPerPage);
            let paginationHtml = '';
            if (totalPages > 1) {
                paginationHtml += `<button onclick="goToPage(1)">« Primeira</button>`;
                if (currentPage > 1) paginationHtml += `<button onclick="goToPage(${currentPage - 1})">‹ Anterior</button>`;
                for (let i = Math.max(1, currentPage - 2); i <= Math.min(totalPages, currentPage + 2); i++) {
                    paginationHtml += `<button ${i === currentPage ? 'style="background: #1f6a5a; color: white;"' : ''} onclick="goToPage(${i})">${i}</button>`;
                }
                if (currentPage < totalPages) paginationHtml += `<button onclick="goToPage(${currentPage + 1})">Próxima ›</button>`;
                paginationHtml += `<button onclick="goToPage(${totalPages})">Última »</button>`;
            }
            document.getElementById('pagination').innerHTML = paginationHtml;
        }

        function filterByStatus(status) {
            currentFilter = status;
            currentPage = 1;
            document.querySelectorAll('.filter-btn').forEach(btn => btn.classList.remove('active'));
            event?.target?.classList.add('active');
            renderTable();
        }

        function goToPage(page) {
            currentPage = page;
            renderTable();
        }

        document.getElementById('searchInput')?.addEventListener('keyup', () => {
            currentPage = 1;
            renderTable();
        });

        // Event listener para seleção de programação
        document.getElementById('programacaoSelect')?.addEventListener('change', (e) => {
            filtrarPorProgramacao(e.target.value);
        });

        // Carregar programações e linhas ao inicializar
        carregarProgramacoes();
        carregarLinhas();
        resetSequenceSection();

        // Renderizar inicial
        renderTable();
    </script>

    <!-- DEBUG PANEL -->
    <div style="position: fixed; bottom: 20px; right: 20px; width: 350px; max-height: 400px; background: #1e1e1e; color: #d4d4d4; border-radius: 8px; border: 1px solid #404040; box-shadow: 0 10px 40px rgba(0,0,0,0.3); font-size: 11px; font-family: 'Courier New', monospace; z-index: 9999; display: none; flex-direction: column;">
        <div style="background: #2d2d2d; padding: 10px; border-bottom: 1px solid #404040; font-weight: bold; cursor: pointer;" onclick="this.parentElement.style.display='none';">🔧 DEBUG PANEL (clique para fechar)</div>
        <div style="overflow-y: auto; padding: 10px; flex: 1;">
            <div><strong style="color: #4ec9b0;">[INFO]</strong> Excel Path: <code style="color: #ce9178; background: #2d2d2d; padding: 2px 4px; border-radius: 3px;">c:\dadosCodi\relatorio_api_2026.xlsx</code></div>
            <div style="margin-top: 8px;"><strong style="color: #4ec9b0;">[PREVISTO]</strong> OPs: <span style="color: #b5cea8;"><?= count($previsto) ?></span>, Total: <span style="color: #b5cea8;"><?= number_format($total_planejado, 0, ',', '.') ?></span></div>
            <div style="margin-top: 8px;"><strong style="color: #4ec9b0;">[REALIZADO]</strong> OPs: <span style="color: #b5cea8;"><?= count($realizado) ?></span>, Total: <span style="color: #b5cea8;"><?= number_format($total_realizado, 0, ',', '.') ?></span></div>
            <div style="margin-top: 8px;"><strong style="color: #4ec9b0;">[MERGE]</strong> OPs Totais: <span style="color: #b5cea8;"><?= count($comparativo) ?></span></div>
            <hr style="border: none; border-top: 1px solid #404040; margin: 8px 0;">
            <div style="margin-top: 8px; max-height: 200px; overflow-y: auto;">
                <div style="color: #6a9955; font-weight: bold; margin-bottom: 4px;">LOG HISTÓRICO:</div>
                <?php foreach ($logs as $log): ?>
                    <div style="margin: 4px 0; line-height: 1.3;">
                        <span style="color: <?= $log['type'] === 'error' ? '#f48771' : ($log['type'] === 'success' ? '#6a9955' : ($log['type'] === 'warning' ? '#dcdcaa' : '#6a9955')) ?>;">[<?= strtoupper($log['type']) ?>]</span>
                        <span style="color: #858585;"><?= htmlspecialchars($log['time']) ?></span>
                        <span style="color: #d4d4d4;"><?= htmlspecialchars($log['msg']) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</body>
</html>
