<?php
declare(strict_types=1);

require __DIR__ . '/src/bootstrap.php';

use App\Auth\Auth;

Auth::startSession();
Auth::requireLogin();

$isSandbox = (getenv('APP_ENV') ?: '') === 'sandbox';
if (!$isSandbox) {
    http_response_code(403);
    die('Este relatório está disponível apenas no ambiente SANDBOX.');
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gráfico de Sequenciamento - Previsto vs Realizado</title>
    <link rel="stylesheet" href="assets/css/app.css">
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
            --radius: 8px;
            --font: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: var(--font);
            background: var(--bg);
            color: var(--ink);
            line-height: 1.6;
        }

        .container {
            max-width: 1600px;
            margin: 0 auto;
            padding: 20px;
        }

        /* HEADER */
        .header {
            background: white;
            border-radius: var(--radius);
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }

        .header h1 {
            font-size: 28px;
            margin-bottom: 12px;
            color: var(--ink);
        }

        .header p {
            font-size: 13px;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 0.08em;
        }

        /* TOOLBAR */
        .toolbar {
            background: white;
            border-radius: var(--radius);
            padding: 16px;
            margin-bottom: 20px;
            display: flex;
            gap: 16px;
            flex-wrap: wrap;
            align-items: center;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .form-group label {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--muted);
            font-weight: 600;
        }

        .form-group select,
        .form-group input {
            padding: 8px 12px;
            border: 1px solid #d1d5db;
            border-radius: 4px;
            font-family: var(--font);
            font-size: 13px;
            background: white;
            color: var(--ink);
        }

        .form-group select:focus,
        .form-group input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(31, 106, 90, 0.1);
        }

        .btn {
            padding: 8px 16px;
            border: 1px solid #d1d5db;
            border-radius: 4px;
            font-family: var(--font);
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            background: white;
            color: var(--ink);
        }

        .btn:hover {
            border-color: var(--primary);
            background: var(--primary);
            color: white;
        }

        .btn.btn-primary {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }

        .btn.btn-primary:hover {
            background: #0f3f35;
        }

        .btn-group {
            display: flex;
            gap: 8px;
        }

        /* METRICS CARDS */
        .metrics {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 12px;
            margin-bottom: 20px;
        }

        .metric-card {
            background: white;
            border-radius: var(--radius);
            padding: 16px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }

        .metric-card label {
            display: block;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--muted);
            font-weight: 600;
            margin-bottom: 6px;
        }

        .metric-card .value {
            display: block;
            font-size: 20px;
            font-weight: 700;
            color: var(--primary);
        }

        .metric-card .subtitle {
            display: block;
            font-size: 12px;
            color: var(--muted);
            margin-top: 4px;
        }

        /* RESUMO OPERACIONAL */
        .resumo {
            background: linear-gradient(135deg, #f0f9f7, #f9f5f0);
            border-radius: var(--radius);
            padding: 16px;
            margin-bottom: 20px;
            border-left: 4px solid var(--primary);
        }

        .resumo-title {
            font-size: 13px;
            font-weight: 600;
            text-transform: uppercase;
            color: var(--ink);
            margin-bottom: 8px;
        }

        .resumo-item {
            font-size: 13px;
            margin: 4px 0;
            color: var(--ink);
        }

        .resumo-item strong {
            color: var(--primary);
            font-weight: 700;
        }

        /* TIMELINE CONTAINER */
        .timeline-container {
            background: white;
            border-radius: var(--radius);
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }

        .timeline-header {
            font-size: 16px;
            font-weight: 600;
            color: var(--ink);
            margin-bottom: 16px;
        }

        .timeline-controls {
            display: flex;
            gap: 12px;
            margin-bottom: 16px;
            flex-wrap: wrap;
            align-items: center;
        }

        .semana-selector {
            display: flex;
            gap: 4px;
            align-items: center;
        }

        .semana-btn {
            padding: 6px 10px;
            border: 1px solid #d1d5db;
            border-radius: 4px;
            background: white;
            color: var(--ink);
            font-size: 12px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .semana-btn:hover {
            border-color: var(--primary);
        }

        .semana-btn.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }

        #timelineChart {
            min-height: 520px;
            position: relative;
        }

        /* LEGEND */
        .legend {
            display: flex;
            gap: 30px;
            flex-wrap: wrap;
            margin-top: 16px;
            padding-top: 16px;
            border-top: 1px solid var(--line);
            font-size: 12px;
        }

        .legend-item {
            display: flex;
            align-items: center;
            gap: 6px;
            margin: 6px 0;
        }

        .legend-dot {
            width: 16px;
            height: 16px;
            border-radius: 2px;
            display: inline-block;
            border: 1px solid rgba(0,0,0,0.1);
        }

        .legend-dot.done { background-color: #64748b; }
        .legend-dot.setup { background-color: #fb923c; }
        .legend-dot.running { background-color: #22c55e; }
        .legend-dot.planned { background-color: #3b82f6; }

        /* DETAIL PANEL */
        .detail-panel {
            background: white;
            border-radius: var(--radius);
            padding: 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            min-height: 360px;
        }

        .detail-header {
            font-size: 16px;
            font-weight: 600;
            color: var(--ink);
            margin-bottom: 16px;
        }

        .detail-row {
            margin-bottom: 12px;
        }

        .detail-label {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--muted);
            font-weight: 600;
            margin-bottom: 4px;
        }

        .detail-value {
            font-size: 14px;
            color: var(--ink);
            font-weight: 500;
        }

        .layout-grid {
            display: none;
            margin-top: 20px;
            gap: 20px;
            align-items: flex-start;
        }

        .timeline-column {
            flex: 1;
        }

        .detail-column {
            width: 320px;
            flex-shrink: 0;
        }

        .detail-column .detail-panel {
            position: sticky;
            top: 20px;
            align-self: flex-start;
        }

        #detailContainer {
            display: block;
        }

        @media (max-width: 1024px) {
            .layout-grid {
                flex-direction: column;
            }

            .detail-column {
                width: 100%;
            }

            .detail-column .detail-panel {
                position: relative;
                top: auto;
            }
        }

        /* LOADING & MESSAGES */
        .loading {
            text-align: center;
            padding: 40px;
            color: var(--muted);
        }

        .spinner {
            display: inline-block;
            width: 24px;
            height: 24px;
            border: 3px solid var(--line);
            border-top-color: var(--primary);
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .message {
            padding: 16px;
            border-radius: var(--radius);
            margin-bottom: 16px;
            font-size: 13px;
        }

        .message.error {
            background: #fee2e2;
            color: #991b1b;
            border-left: 4px solid #dc2626;
        }

        .message.success {
            background: #dcfce7;
            color: #166534;
            border-left: 4px solid #22c55e;
        }

        /* RESPONSIVE */
        @media (max-width: 1024px) {
            .metrics {
                grid-template-columns: repeat(2, 1fr);
            }

            .timeline-container {
                padding: 16px;
            }
        }

        @media (max-width: 768px) {
            .metrics {
                grid-template-columns: 1fr;
            }

            .toolbar {
                flex-direction: column;
                align-items: stretch;
            }

            .form-group {
                width: 100%;
            }

            .timeline-header {
                font-size: 14px;
            }

            #timelineChart {
                min-height: 300px;
                font-size: 11px;
            }
        }

        /* ===============================
           NOVO LAYOUT (BASEADO NO MOCK)
           =============================== */
        body {
            background: #f8fafc;
            color: #0f172a;
        }

        .pcp-page {
            min-height: 100vh;
            padding: 24px;
        }

        .pcp-shell {
            max-width: 1280px;
            margin: 0 auto;
            display: flex;
            flex-direction: column;
            gap: 24px;
        }

        .pcp-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.06);
        }

        .pcp-card-header {
            padding: 16px 18px;
            border-bottom: 1px solid #e2e8f0;
        }

        .pcp-card-title {
            font-size: 18px;
            font-weight: 700;
            margin: 0;
        }

        .pcp-title {
            font-size: 24px;
            font-weight: 800;
            margin: 0;
        }

        .pcp-card-content {
            padding: 16px 18px;
        }

        /* Deixar o seletor (toolbar) com cara de mock, sem "card dentro de card". */
        .pcp-card .toolbar {
            background: transparent;
            box-shadow: none;
            padding: 0;
            border-radius: 0;
            margin-bottom: 0;
        }

        .pcp-metrics-grid {
            display: grid;
            gap: 12px;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            margin-top: 12px;
        }

        .pcp-metric {
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            background: #ffffff;
            padding: 12px;
        }

        .pcp-metric-label {
            font-size: 11px;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: #64748b;
            font-weight: 600;
        }

        .pcp-metric-value {
            margin-top: 6px;
            font-size: 18px;
            font-weight: 700;
            color: #0f172a;
        }

        .pcp-summary {
            margin-top: 14px;
            border-radius: 16px;
            background: #f1f5f9;
            padding: 14px 14px;
            color: #334155;
            font-size: 13px;
        }

        .pcp-summary-title {
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 6px;
        }

        .pcp-grid {
            display: grid;
            gap: 24px;
            grid-template-columns: 1.75fr 0.85fr;
            align-items: start;
        }

        .pcp-scroll {
            width: 100%;
            overflow: auto;
            max-height: 680px;
            border-radius: 14px;
            border: 1px solid #e2e8f0;
        }

        .pcp-timeline-min {
            min-width: 1700px;
        }

        .pcp-weekbands {
            display: flex;
            margin-left: 180px;
            position: sticky;
            top: 0;
            z-index: 40;
            background: #f8fafc;
        }

        .pcp-weekband {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-bottom: 0;
            padding: 8px 10px;
            font-size: 12px;
            font-weight: 700;
            color: #475569;
            white-space: nowrap;
        }

        .pcp-dayheader {
            display: flex;
            background: #ffffff;
            border-bottom: 1px solid #e2e8f0;
            position: sticky;
            top: 38px; /* abaixo da faixa de semanas */
            z-index: 39;
        }

        .pcp-sidebar-header {
            width: 180px;
            flex-shrink: 0;
            background: #f8fafc;
            border-right: 1px solid #e2e8f0;
            padding: 12px 10px;
            font-size: 14px;
            font-weight: 700;
            color: #334155;
        }

        .pcp-dayheaders {
            display: flex;
            width: calc(100% - 180px);
        }

        .pcp-daycell {
            border-right: 1px solid #e2e8f0;
            padding: 12px 8px;
            text-align: center;
            font-size: 12px;
            color: #475569;
        }

        .pcp-rows-wrapper {
            position: relative;
            background: #ffffff;
        }

        .pcp-row {
            display: flex;
            border-bottom: 1px solid #e2e8f0;
        }

        .pcp-row:last-child {
            border-bottom: 0;
        }

        .pcp-row-left {
            width: 180px;
            flex-shrink: 0;
            height: 56px;
            background: #f8fafc;
            border-right: 1px solid #e2e8f0;
            padding: 8px 10px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .pcp-row-op {
            font-size: 12px;
            font-weight: 700;
            color: #0f172a;
        }

        .pcp-row-nome {
            font-size: 12px;
            color: #475569;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .pcp-row-right {
            position: relative;
            height: 56px;
            flex: 1;
            min-width: 0;
        }

        .pcp-dayband-even {
            background: rgba(251, 113, 133, 0.18); /* rose */
        }

        .pcp-dayband-odd {
            background: rgba(96, 165, 250, 0.18); /* blue */
        }

        .pcp-bar {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            border: 0;
            border-radius: 10px;
            color: #ffffff;
            padding: 6px 10px;
            min-height: 32px;
            text-align: left;
            cursor: pointer;
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.18);
            transition: transform 0.12s ease, filter 0.12s ease;
            overflow: hidden;
            border: 2px solid rgba(15, 23, 42, 0.16);
        }

        .pcp-bar:hover {
            transform: translateY(-50%) scale(1.01);
            filter: brightness(1.02);
        }

        .pcp-bar-title {
            font-size: 12px;
            font-weight: 800;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .pcp-bar-sub {
            font-size: 11px;
            opacity: 0.95;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .pcp-bar--running { background: #22c55e; box-shadow: 0 0 0 2px #15803d inset, 0 1px 2px rgba(15, 23, 42, 0.18); }
        .pcp-bar--done { background: #64748b; }
        .pcp-bar--setup { background: #fb923c; }
        .pcp-bar--planned { background: #3b82f6; }
        .pcp-bar--selected { outline: 2px solid rgba(15, 23, 42, 0.35); outline-offset: 2px; }

        /* Estilo "gantt tradicional": barra compacta com preenchimento de progresso */
        .pcp-bar--compact {
            padding: 0;
            min-height: 26px;
            border-radius: 6px;
            text-align: center;
            border-color: rgba(15, 23, 42, 0.28);
        }

        .pcp-bar-progress {
            position: absolute;
            inset: 0;
            width: 0%;
            background: rgba(15, 23, 42, 0.28);
        }

        .pcp-bar--planned .pcp-bar-progress { background: rgba(30, 64, 175, 0.35); }
        .pcp-bar--running .pcp-bar-progress { background: rgba(20, 83, 45, 0.35); }
        .pcp-bar--done .pcp-bar-progress { background: rgba(15, 23, 42, 0.25); }
        .pcp-bar--setup .pcp-bar-progress { background: rgba(124, 45, 18, 0.20); }

        .pcp-bar-label {
            position: relative;
            z-index: 2;
            font-size: 11px;
            font-weight: 800;
            letter-spacing: 0.2px;
            line-height: 26px;
            padding: 0 6px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .pcp-late {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            z-index: 12;
            background: #fee2e2;
            color: #b91c1c;
            font-size: 11px;
            font-weight: 800;
            padding: 3px 8px;
            border-radius: 999px;
        }

        .pcp-now {
            position: absolute;
            top: 0;
            bottom: 0;
            z-index: 20;
            width: 0;
            border-left: 2px solid #ef4444;
            pointer-events: none;
        }

        .pcp-now-label {
            position: absolute;
            top: 8px;
            margin-left: -18px;
            background: #ef4444;
            color: #ffffff;
            font-size: 10px;
            font-weight: 900;
            padding: 2px 8px;
            border-radius: 8px;
            display: inline-block;
        }

        .pcp-badges {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 12px;
            color: #475569;
            font-size: 12px;
        }

        .pcp-badge {
            border: 1px solid #e2e8f0;
            border-radius: 999px;
            padding: 6px 10px;
            background: #ffffff;
            font-weight: 600;
        }

        .pcp-detail-box {
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 14px;
            font-size: 13px;
            color: #334155;
        }

        .pcp-detail-label {
            font-size: 11px;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: #64748b;
            font-weight: 700;
        }

        .pcp-detail-value {
            margin-top: 4px;
            font-weight: 800;
            color: #0f172a;
        }

        .pcp-detail-item {
            margin-bottom: 12px;
        }

        @media (max-width: 1024px) {
            .pcp-grid {
                grid-template-columns: 1fr;
            }

            .pcp-metrics-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 640px) {
            .pcp-page {
                padding: 14px;
            }

            .pcp-metrics-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="pcp-page">
        <div class="pcp-shell">
            <div class="pcp-card">
                <div class="pcp-card-header">
                    <h1 class="pcp-title">Gráfico de Sequenciamento</h1>
                </div>
                <div class="pcp-card-content">
                    <!-- TOOLBAR -->
                    <div class="toolbar" style="margin-bottom: 0;">
                        <div class="form-group" style="flex: 1; min-width: 260px;">
                            <label for="programacaoSelect">Programação</label>
                            <select id="programacaoSelect">
                                <option value="">Carregando programações...</option>
                            </select>
                        </div>

                        <div class="form-group" style="width: 150px;">
                            <label for="dataInicio">Data Início</label>
                            <input type="date" id="dataInicio">
                        </div>

                        <div class="form-group" style="width: 150px;">
                            <label for="dataFim">Data Fim</label>
                            <input type="date" id="dataFim">
                        </div>

                        <div class="btn-group" style="align-self: flex-end;">
                            <button id="aplicarBtn" class="btn btn-primary">Aplicar</button>
                            <button id="restaurarBtn" class="btn">Restaurar</button>
                        </div>
                    </div>

                    <!-- MESSAGES -->
                    <div id="messagesContainer" style="margin-top: 12px;"></div>

                    <!-- TOP CARD CONTENT -->
                    <div id="mainContent" style="display: none;">
                        <div class="pcp-metrics-grid">
                            <div class="pcp-metric">
                                <div class="pcp-metric-label">Linha</div>
                                <div class="pcp-metric-value" id="metricLinha">—</div>
                            </div>
                            <div class="pcp-metric">
                                <div class="pcp-metric-label">Início</div>
                                <div class="pcp-metric-value" id="metricInicio">—</div>
                            </div>
                            <div class="pcp-metric">
                                <div class="pcp-metric-label">Fim previsto</div>
                                <div class="pcp-metric-value" id="metricFim">—</div>
                            </div>
                            <div class="pcp-metric">
                                <div class="pcp-metric-label">Ordens / Setups</div>
                                <div class="pcp-metric-value" id="metricOrdens">—</div>
                            </div>
                        </div>

                        <div class="pcp-summary">
                            <div class="pcp-summary-title">Resumo operacional</div>
                            <div>Produzindo agora: <b id="resumoAgora">Nenhuma OP em execução</b></div>
                            <div id="resumoInicioFim" style="margin-top: 2px;"></div>
                            <div id="resumoProxima" style="margin-top: 2px;"></div>
                        </div>
                    </div>
                </div>
            </div>

            <div id="gridContainer" class="pcp-grid" style="display: none;">
                <div class="pcp-card">
                    <div class="pcp-card-header">
                        <h2 class="pcp-card-title">Visão temporal</h2>
                    </div>
                    <div class="pcp-card-content">
                        <div id="timelineScroll" class="pcp-scroll">
                            <div class="pcp-timeline-min">
                                <div id="weekBands" class="pcp-weekbands"></div>
                                <div class="pcp-dayheader">
                                    <div class="pcp-sidebar-header">Recurso</div>
                                    <div id="dayHeaders" class="pcp-dayheaders"></div>
                                </div>
                                <div id="timelineRowsWrapper" class="pcp-rows-wrapper">
                                    <div id="nowMarker" class="pcp-now" style="display: none;">
                                        <div class="pcp-now-label">AGORA</div>
                                    </div>
                                    <div id="timelineRows"></div>
                                </div>
                            </div>
                        </div>

                        <div class="pcp-badges">
                            <span class="pcp-badge">Cinza = concluída</span>
                            <span class="pcp-badge">Azul = programada</span>
                            <span class="pcp-badge">Verde = em produção</span>
                            <span class="pcp-badge">Laranja = setup</span>
                        </div>
                    </div>
                </div>

                <div class="pcp-card">
                    <div class="pcp-card-header">
                        <h2 class="pcp-card-title">Detalhes da ordem</h2>
                    </div>
                    <div class="pcp-card-content">
                        <div class="pcp-detail-box">
                            <div class="pcp-detail-item">
                                <div class="pcp-detail-label">Produto</div>
                                <div class="pcp-detail-value" id="detailProduto">—</div>
                            </div>
                            <div class="pcp-detail-item">
                                <div class="pcp-detail-label">OP</div>
                                <div class="pcp-detail-value" id="detailOp">—</div>
                            </div>
                            <div class="pcp-detail-item">
                                <div class="pcp-detail-label">Tipo</div>
                                <div class="pcp-detail-value" id="detailTipo">—</div>
                            </div>
                            <div class="pcp-detail-item">
                                <div class="pcp-detail-label">Status</div>
                                <div class="pcp-detail-value" id="detailStatus">—</div>
                            </div>
                            <div class="pcp-detail-item">
                                <div class="pcp-detail-label">Início</div>
                                <div class="pcp-detail-value" id="detailInicio">—</div>
                            </div>
                            <div class="pcp-detail-item">
                                <div class="pcp-detail-label">Fim</div>
                                <div class="pcp-detail-value" id="detailFim">—</div>
                            </div>
                            <div class="pcp-detail-item" style="margin-bottom: 0;">
                                <div class="pcp-detail-label">Duração</div>
                                <div class="pcp-detail-value" id="detailDuracao">—</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="assets/js/sequenciamento_grafico_mock.js"></script>
</body>
</html>

