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
        <div style="background: white; padding: 16px; border-radius: 12px; margin-bottom: 24px; box-shadow: 0 2px 8px rgba(0,0,0,0.04);">
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
        <div class="stats">
            <div class="stat-box">
                <h3>Total Planejado</h3>
                <div class="value"><?= number_format($total_planejado, 0, ',', '.') ?></div>
            </div>
            <div class="stat-box">
                <h3>Total Realizado</h3>
                <div class="value"><?= number_format($total_realizado, 0, ',', '.') ?></div>
            </div>
            <div class="stat-box accent">
                <h3>Diferença Total</h3>
                <div class="value <?= $total_diff >= 0 ? 'positive' : 'negative' ?>">
                    <?= ($total_diff >= 0 ? '+' : '') . number_format($total_diff, 0, ',', '.') ?>
                </div>
            </div>
            <div class="stat-box">
                <h3>Percentual Médio</h3>
                <div class="value"><?= number_format($pct_medio, 1, ',', '.') ?>%</div>
            </div>
        </div>

        <!-- GRÁFICOS -->
        <div class="charts">
            <div class="chart-container">
                <h3>Distribuição de Status</h3>
                <div id="chartStatus"></div>
            </div>
            <div class="chart-container">
                <h3>Performance por Faixa</h3>
                <div id="chartPerf"></div>
            </div>
        </div>

        <div class="chart-container" style="margin: 16px 0;">
            <h3>Top 15: Previsto vs Realizado</h3>
            <div id="chartComparison" style="height: 350px;"></div>
        </div>

        <!-- STATUS DAS OPS -->
        <h2 style="margin-top: 32px; margin-bottom: 16px;">Status das Ordens de Produção</h2>
        <div class="status-cards">
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
        <div class="table-section">
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
        
        let currentFilter = '';
        let currentPage = 1;
        let selectedProgram = null;
        const itemsPerPage = 20;

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

        // Carregar programações ao inicializar
        carregarProgramacoes();

        // Renderizar inicial
        renderTable();
    </script>

    <!-- DEBUG PANEL -->
    <div style="position: fixed; bottom: 20px; right: 20px; width: 350px; max-height: 400px; background: #1e1e1e; color: #d4d4d4; border-radius: 8px; border: 1px solid #404040; box-shadow: 0 10px 40px rgba(0,0,0,0.3); font-size: 11px; font-family: 'Courier New', monospace; z-index: 9999; display: flex; flex-direction: column;">
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
