<?php
declare(strict_types=1);

require __DIR__ . '/src/bootstrap.php';

use App\Auth\Auth;

Auth::startSession();
Auth::requireLogin();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gráfico de Sequenciamento</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: #f5f7fa;
            color: #2d3748;
        }

        .container {
            display: flex;
            height: 100vh;
            overflow: hidden;
        }

        /* ========== SIDEBAR ESQUERDO ========== */
        .sidebar {
            width: 280px;
            background: white;
            border-right: 1px solid #e2e8f0;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
        }

        .sidebar-header {
            padding: 16px;
            border-bottom: 1px solid #e2e8f0;
            background: #f7fafc;
        }

        .sidebar-header h3 {
            font-size: 14px;
            font-weight: 600;
            text-transform: uppercase;
            color: #4a5568;
            letter-spacing: 0.5px;
        }

        .sidebar-list {
            flex: 1;
            overflow-y: auto;
        }

        .sidebar-item {
            padding: 12px 16px;
            border-bottom: 1px solid #edf2f7;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .sidebar-item:hover {
            background: #edf2f7;
        }

        .sidebar-item.active {
            background: #e6fffa;
            border-left: 3px solid #14b8a6;
            padding-left: 13px;
        }

        .sidebar-item-op {
            font-weight: 600;
            font-size: 13px;
            color: #2d3748;
        }

        .sidebar-item-meta {
            font-size: 12px;
            color: #718096;
        }

        /* ========== ÁREA PRINCIPAL ========== */
        .main-content {
            flex: 1;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .toolbar {
            padding: 16px;
            background: white;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            gap: 16px;
            align-items: center;
            flex-wrap: wrap;
        }

        .toolbar-title {
            font-size: 18px;
            font-weight: 600;
            color: #2d3748;
        }

        .periodo-select {
            display: flex;
            gap: 8px;
            background: #f7fafc;
            padding: 8px;
            border-radius: 6px;
        }

        .periodo-btn {
            padding: 8px 12px;
            border: 1px solid #cbd5e0;
            background: white;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 500;
            transition: all 0.2s;
            color: #4a5568;
        }

        .periodo-btn:hover {
            background: #edf2f7;
        }

        .periodo-btn.active {
            background: #14b8a6;
            color: white;
            border-color: #14b8a6;
        }

        /* ========== MODO SELECT (Planejado vs Históricos) ========== */
        .modo-btn {
            padding: 6px 12px;
            border: 1px solid #cbd5e0;
            background: white;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 500;
            transition: all 0.2s;
            color: #4a5568;
        }

        .modo-btn:hover {
            background: #edf2f7;
        }

        .modo-btn.active {
            background: #0ea5e9;
            color: white;
            border-color: #0ea5e9;
        }

        /* ========== TIMELINE ========== */
        .timeline-wrapper {
            flex: 1;
            overflow: auto;
            background: white;
        }

        .timeline-container {
            display: flex;
            flex-direction: column;
            padding: 16px;
        }

        /* ========== TIMELINE HEADER - SEMANAS ========== */
        .timeline-weeks {
            display: flex;
            font-size: 12px;
            font-weight: 600;
            color: #4a5568;
            padding: 8px 0;
            border-bottom: 1px solid #cbd5e0;
            margin-bottom: 8px;
        }

        .timeline-weeks-col {
            width: 200px;
            flex-shrink: 0;
            padding-right: 16px;
        }

        .timeline-weeks-scroll {
            flex: 1;
            overflow-x: hidden;
        }

        .timeline-weeks-content {
            display: flex;
        }

        .timeline-week {
            min-width: 240px;
            flex-shrink: 0;
            padding-left: 20px;
            color: #718096;
        }

        /* ========== TIMELINE HEADER - HORAS ========== */
        .timeline-header {
            display: flex;
            margin-bottom: 0;
            background: #f7fafc;
            border-bottom: 2px solid #cbd5e0;
        }

        .timeline-labels-col {
            width: 200px;
            flex-shrink: 0;
            padding: 12px 16px;
            font-weight: 600;
            border-right: 1px solid #cbd5e0;
        }

        .timeline-header-scroll {
            flex: 1;
            overflow-x: auto;
            overflow-y: hidden;
        }

        .timeline-header-content {
            display: flex;
        }

        .timeline-hour {
            min-width: 60px;
            flex-shrink: 0;
            padding: 12px 0;
            text-align: center;
            border-right: 1px solid #e2e8f0;
            font-size: 11px;
            font-weight: 600;
            color: #4a5568;
            background: #f7fafc;
        }

        .timeline-hour:nth-child(4n) {
            background: #edf2f7;
            border-right-color: #cbd5e0;
        }

        /* ========== TIMELINE LINHAS ========== */
        .timeline-body {
            flex: 1;
            overflow-x: auto;
            overflow-y: auto;
        }

        .timeline-row {
            display: flex;
            min-height: 48px;
            border-bottom: 1px solid #e5e7eb;
            align-items: stretch;
            transition: background-color 0.2s ease;
        }

        .timeline-row:hover {
            background: #fafbfc;
        }

        .timeline-row.primeira-prog {
            border-top: 2px solid #cbd5e0;
        }

        .timeline-row-label {
            width: 200px;
            flex-shrink: 0;
            padding: 10px 16px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            border-right: 1px solid #e5e7eb;
            background: white;
            cursor: pointer;
        }

        .timeline-row-label:hover {
            background: #f3f4f6;
        }

        .timeline-row-label-op {
            font-size: 13px;
            font-weight: 700;
            color: #1f2937;
            line-height: 1.2;
        }

        .timeline-row-label-meta {
            font-size: 11px;
            color: #6b7280;
            margin-top: 2px;
        }

        .timeline-row-content {
            flex: 1;
            position: relative;
            background: linear-gradient(90deg, transparent 0%, transparent calc(100% - 1px), #f0f1f3 calc(100% - 1px));
            background-size: 60px 100%;
        }

        /* Barras */
        .timeline-bar {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            height: 32px;
            border-radius: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 11px;
            font-weight: 600;
            color: white;
            cursor: pointer;
            transition: all 0.2s;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            white-space: nowrap;
            padding: 0 6px;
            min-width: 20px;
        }

        .timeline-bar:hover {
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
            z-index: 10;
        }

        /* Cores por tipo/status */
        .timeline-bar.sequenciado {
            background: linear-gradient(135deg, #FBBF24 0%, #F59E0B 100%);
        }

        .timeline-bar.realizado {
            background: linear-gradient(135deg, #3B82F6 0%, #2563EB 100%);
        }

        .timeline-bar.producao {
            background: linear-gradient(135deg, #10B981 0%, #059669 100%);
        }

        .timeline-bar.setup {
            background: linear-gradient(135deg, #F97316 0%, #EA580C 100%);
        }

        .timeline-bar.pausa {
            background: linear-gradient(135deg, #EF4444 0%, #DC2626 100%);
        }

        /* ========== DESVIOS HISTÓRICOS ========== */
        .timeline-bar.on-time {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%) !important;
            border: 1px solid #047857;
            box-shadow: 0 2px 4px rgba(16, 185, 129, 0.2);
        }

        .timeline-bar.on-time:hover {
            box-shadow: 0 4px 8px rgba(16, 185, 129, 0.4);
            transform: translateY(-2px);
        }

        .timeline-bar.delayed {
            background: linear-gradient(135deg, #f97316 0%, #ea580c 100%) !important;
            border: 2px solid #dc2626;
            animation: pulse-delayed 2.5s infinite;
            box-shadow: 0 2px 6px rgba(220, 38, 38, 0.3);
        }

        .timeline-bar.delayed:hover {
            box-shadow: 0 4px 10px rgba(220, 38, 38, 0.5);
            transform: translateY(-2px);
        }

        .timeline-bar.early {
            background: linear-gradient(135deg, #06b6d4 0%, #0891b2 100%) !important;
            border: 1px solid #0e7490;
            box-shadow: 0 2px 4px rgba(6, 182, 212, 0.2);
        }

        .timeline-bar.early:hover {
            box-shadow: 0 4px 8px rgba(6, 182, 212, 0.4);
            transform: translateY(-2px);
        }

        @keyframes pulse-delayed {
            0%, 100% { box-shadow: 0 2px 6px rgba(220, 38, 38, 0.3); }
            50% { box-shadow: 0 4px 12px rgba(220, 38, 38, 0.5); }
        }

        .timeline-bar {
            transition: all 0.2s ease;
        }

        .timeline-bar.selected {
            box-shadow: 0 0 0 3px rgba(14, 165, 233, 0.5);
            z-index: 100 !important;
        }

        /* ========== TOOLTIPS (FASE 2) ========== */
        .tooltip {
            position: absolute;
            background: #1f2937;
            color: #fff;
            padding: 12px 16px;
            border-radius: 6px;
            font-size: 12px;
            pointer-events: none;
            z-index: 1000;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
            display: none;
            max-width: 400px;
            white-space: normal;
        }

        .tooltip.visible {
            display: block;
        }

        .tooltip-line {
            margin: 2px 0;
            display: flex;
            justify-content: space-between;
            gap: 16px;
        }

        .tooltip-label {
            font-weight: 600;
            color: #9ca3af;
        }

        .tooltip-value {
            color: #fff;
        }

        .tooltip-section {
            margin-top: 8px;
            padding-top: 8px;
            border-top: 1px solid #4b5563;
        }

        /* ========== EXPANDED DETALHES (FASE 3) ========== */
        .timeline-row.expanded .timeline-row-content {
            background: #edf2f7;
            border-bottom: 2px solid #cbd5e0;
        }

        .timeline-row-expanded {
            display: none;
            background: #f7fafc;
            border-bottom: 1px solid #edf2f7;
            padding: 12px 16px;
        }

        .timeline-row.expanded + .timeline-row-expanded {
            display: block;
        }

        .timeline-row-expanded-content {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 12px;
        }

        .timeline-row-expanded-item {
            padding: 8px;
            background: white;
            border-radius: 4px;
            border-left: 3px solid #0ea5e9;
        }

        .timeline-row-expanded-label {
            font-weight: 600;
            font-size: 11px;
            color: #718096;
            text-transform: uppercase;
        }

        .timeline-row-expanded-value {
            font-size: 14px;
            color: #2d3748;
            margin-top: 4px;
            word-break: break-word;
        }

        .timeline-row-expanded-value.executado {
            color: #059669;
            font-weight: 600;
        }

        /* ========== FILTROS (FASE 4) ========== */
        .toolbar-filters {
            display: flex;
            gap: 12px;
            align-items: center;
            margin-right: 12px;
            flex: 1;
            min-width: 300px;
        }

        .filter-group {
            display: flex;
            gap: 6px;
            align-items: center;
        }

        .filter-group label {
            font-size: 12px;
            font-weight: 600;
            color: #718096;
            text-transform: uppercase;
        }

        .filter-group input,
        .filter-group select {
            padding: 6px 10px;
            border: 1px solid #cbd5e0;
            border-radius: 4px;
            font-size: 12px;
            background: white;
            color: #2d3748;
        }

        .filter-group input:focus,
        .filter-group select:focus {
            outline: none;
            border-color: #0ea5e9;
            box-shadow: 0 0 0 2px rgba(14, 165, 233, 0.1);
        }

        .filter-group input[type="text"] {
            flex: 1;
            min-width: 150px;
        }

        .filter-btn {
            padding: 6px 12px;
            background: #e5e7eb;
            border: none;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
            color: #374151;
            cursor: pointer;
            transition: all 0.2s;
        }

        .filter-btn:hover {
            background: #d1d5db;
        }

        .filter-btn.active {
            background: #0ea5e9;
            color: white;
        }

        .filter-status {
            font-size: 12px;
            color: #718096;
            white-space: nowrap;
        }

        /* ========== SCROLLBAR ========== */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }

        ::-webkit-scrollbar-track {
            background: #f1f1f1;
        }

        ::-webkit-scrollbar-thumb {
            background: #cbd5e0;
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: #a0aec0;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- SIDEBAR -->
        <div class="sidebar">
            <div class="sidebar-header">
                <h3>Programações</h3>
            </div>
            <div class="sidebar-list" id="sidebarList">
                <div class="loading">
                    <div class="spinner"></div>
                    Carregando...
                </div>
            </div>
        </div>

        <!-- MAIN CONTENT -->
        <div class="main-content">
            <!-- TOOLBAR -->
            <div class="toolbar">
                <span class="toolbar-title">Gráfico de Sequenciamento</span>
                
                <!-- SELECTOR DE MODO (Planejado vs Históricos) -->
                <div class="modo-select" style="display: flex; gap: 4px; background: #f7fafc; padding: 6px; border-radius: 6px; margin-left: 16px;">
                    <button class="modo-btn active" data-modo="planejado" title="Visualizar programação planejada">📅 Planejado</button>
                    <button class="modo-btn" data-modo="historicos" title="Visualizar históricos executados">📊 Históricos</button>
                </div>
                
                <!-- FILTROS (FASE 4) -->
                <div class="toolbar-filters">
                    <div class="filter-group">
                        <input type="text" id="searchSku" placeholder="Buscar SKU..." style="width: 150px;">
                    </div>
                    <div class="filter-group">
                        <label>Tipo:</label>
                        <select id="filterTipo">
                            <option value="">Todos</option>
                            <option value="setup">Setup</option>
                            <option value="producao">Produção</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>Status:</label>
                        <select id="filterStatusSelect">
                            <option value="">Todos</option>
                            <option value="executado">Executado</option>
                            <option value="planejado">Planejado</option>
                        </select>
                    </div>
                    <button class="filter-btn" id="filterClear" title="Limpar filtros">✕ Limpar</button>
                    <div class="filter-status" id="filterStatusInfo" style="margin-left: auto;"></div>
                </div>
                
                <div class="periodo-select">
                    <button class="periodo-btn active" data-periodo="4h">4h</button>
                    <button class="periodo-btn" data-periodo="8h">8h</button>
                    <button class="periodo-btn" data-periodo="12h">12h</button>
                    <button class="periodo-btn" data-periodo="24h">24h</button>
                    <button class="periodo-btn" data-periodo="tudo">Tudo</button>
                </div>
            </div>

            <!-- TIMELINE -->
            <div class="timeline-wrapper">
                <div class="timeline-container">
                    <!-- SEMANAS -->
                    <div class="timeline-weeks" id="timelineWeeks">
                        <div class="timeline-weeks-col"></div>
                        <div class="timeline-weeks-scroll">
                            <div class="timeline-weeks-content" id="timelineWeeksContent">
                                <!-- Gerado por JavaScript -->
                            </div>
                        </div>
                    </div>

                    <!-- HEADER COM HORAS -->
                    <div class="timeline-header">
                        <div class="timeline-labels-col">Programação</div>
                        <div class="timeline-header-scroll" id="headerScroll">
                            <div class="timeline-header-content" id="timelineHeaderContent">
                                <!-- Gerado por JavaScript -->
                            </div>
                        </div>
                    </div>

                    <!-- BODY COM LINHAS -->
                    <div class="timeline-body" id="timelineBody">
                        <div class="loading">
                            <div class="spinner"></div>
                            Carregando dados...
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // ====== STATE (FASES 2-4) ======
        let currentPeriodo = '4h';
        let currentModo = 'planejado'; // 'planejado' ou 'historicos'
        let syncScroll = true;
        let allLinhasData = []; // Armazena todos os dados para filtro
        let currentFilters = {
            sku: '',
            tipo: '',
            status: ''
        };
        let selectedBarId = null;

        // ====== API BASE ======
        const apiBase = (() => {
            const pathParts = window.location.pathname.split('/').filter(p => p && p !== 'sequenciamento.php');
            return pathParts.length > 0 
                ? '/' + pathParts.join('/') + '/api/sequenciamento.php'
                : '/api/sequenciamento.php';
        })();

        // ====== INICIALIZAR ======
        document.addEventListener('DOMContentLoaded', () => {
            console.log('🚀 Inicializando Sequenciamento...');
            console.log('📡 API Base:', apiBase);

            // Setup modo buttons
            document.querySelectorAll('.modo-btn').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    document.querySelectorAll('.modo-btn').forEach(b => b.classList.remove('active'));
                    e.target.classList.add('active');
                    currentModo = e.target.dataset.modo;
                    console.log('🔄 Modo alterado para:', currentModo);
                    renderTimeline();
                });
            });

            // Setup periodo buttons
            document.querySelectorAll('.periodo-btn').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    document.querySelectorAll('.periodo-btn').forEach(b => b.classList.remove('active'));
                    e.target.classList.add('active');
                    currentPeriodo = e.target.dataset.periodo;
                    renderTimeline();
                });
            });

            // Setup filtros (FASE 4)
            document.getElementById('searchSku')?.addEventListener('keyup', (e) => {
                currentFilters.sku = e.target.value.toUpperCase();
                applyFilters();
            });

            document.getElementById('filterTipo')?.addEventListener('change', (e) => {
                currentFilters.tipo = e.target.value;
                applyFilters();
            });

            document.getElementById('filterStatusSelect')?.addEventListener('change', (e) => {
                currentFilters.status = e.target.value;
                applyFilters();
            });

            document.getElementById('filterClear')?.addEventListener('click', () => {
                currentFilters = { sku: '', tipo: '', status: '' };
                document.getElementById('searchSku').value = '';
                document.getElementById('filterTipo').value = '';
                document.getElementById('filterStatusSelect').value = '';
                applyFilters();
            });

            // Setup scroll sync
            const headerScroll = document.getElementById('headerScroll');
            const timelineBody = document.getElementById('timelineBody');

            if (headerScroll && timelineBody) {
                headerScroll.addEventListener('scroll', () => {
                    if (syncScroll) {
                        syncScroll = false;
                        timelineBody.scrollLeft = headerScroll.scrollLeft;
                        syncScroll = true;
                    }
                });

                timelineBody.addEventListener('scroll', () => {
                    if (syncScroll) {
                        syncScroll = false;
                        headerScroll.scrollLeft = timelineBody.scrollLeft;
                        syncScroll = true;
                    }
                });
            }

            // Load initial data
            loadProgramacoes();
            renderTimeline();
        });

        // ====== CARREGAR PROGRAMAÇÕES (SIDEBAR) ======
        async function loadProgramacoes() {
            try {
                console.log('📋 Carregando programações para sidebar...');
                const response = await fetch(apiBase + '?action=listar&limit=50');
                
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}`);
                }

                const json = await response.json();
                
                if (!json.sucesso || !json.data) {
                    throw new Error(json.erro || 'Erro desconhecido');
                }

                console.log('✅ Programações carregadas:', json.data.length);

                const sidebar = document.getElementById('sidebarList');
                sidebar.innerHTML = json.data.map(p => `
                    <div class="sidebar-item" onclick="selecionarProgramacao(${p.id})">
                        <div class="sidebar-item-op">${p.numero_op}</div>
                        <div class="sidebar-item-meta">${p.linha} · ${Number(p.eficiencia).toFixed(1)}%</div>
                    </div>
                `).join('');

            } catch (err) {
                console.error('❌ Erro ao carregar programações:', err);
                document.getElementById('sidebarList').innerHTML = `
                    <div class="loading" style="color: #dc2626;">
                        Erro ao carregar
                    </div>
                `;
            }
        }

        // ====== HELPER: Parsear data do formato "YYYY-MM-DD HH:mm:ss" ======
        function parseDataPHP(dataStr) {
            if (!dataStr) return null;
            // Converter "2026-04-06 16:00:00" para "2026-04-06T16:00:00"
            const isoStr = dataStr.replace(' ', 'T');
            const date = new Date(isoStr);
            if (isNaN(date.getTime())) {
                console.error('❌ Erro ao parsear data:', dataStr);
                return null;
            }
            return date;
        }

        // ====== RENDERIZAR TIMELINE (COM SUPORTE A MODO) ======
        async function renderTimeline() {
            try {
                console.log('📊 Renderizando timeline - Modo:', currentModo, 'Período:', currentPeriodo);

                // Determinar URL baseado no modo
                let url;
                if (currentModo === 'historicos') {
                    // Modo históricos: usar action=historicos com período em dias
                    const periodos = {
                        '4h': undefined,  // Não faz sentido em históricos
                        '8h': undefined,
                        '12h': undefined,
                        '24h': '1d',
                        'tudo': '7d'
                    };
                    const periodo = periodos[currentPeriodo] || '7d';
                    url = apiBase + '?action=historicos&periodo=' + periodo;
                    console.log('🔍 URL (primeira tentativa):', url);
                } else {
                    // Modo planejado: usar action=timeline como antes
                    url = apiBase + '?action=timeline&periodo=' + currentPeriodo;
                    console.log('🔍 URL:', url);
                }

                const response = await fetch(url);
                
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}`);
                }

                let json = await response.json();
                
                if (!json.sucesso) {
                    throw new Error(json.erro || 'Erro desconhecido');
                }

                console.log('✅ Dados recebidos:', json);
                
                // Se modo históricos e sem dados, tentar COM fallback (todos=1)
                if (currentModo === 'historicos' && (!json.historicos || json.historicos.length === 0)) {
                    console.log('⚠️ Sem dados no período, tentando fallback (todos=1)...');
                    const urlFallback = apiBase + '?action=historicos&todos=1';
                    const responseFallback = await fetch(urlFallback);
                    if (responseFallback.ok) {
                        const jsonFallback = await responseFallback.json();
                        if (jsonFallback.sucesso && jsonFallback.historicos && jsonFallback.historicos.length > 0) {
                            console.log('✅ Fallback retornou dados:', jsonFallback.historicos.length, 'itens');
                            json = jsonFallback;
                        }
                    }
                }

                // Renderizar baseado no modo
                if (currentModo === 'historicos') {
                    renderTimelineHistoricos(json);
                } else {
                    renderTimelinePlanejado(json);
                }

            } catch (err) {
                console.error('❌ Erro ao renderizar timeline:', err);
                document.getElementById('timelineBody').innerHTML = `
                    <div class="loading" style="color: #dc2626;">
                        Erro ao carregar timeline: ${err.message}
                    </div>
                `;
            }
        }

        // ====== RENDERIZAR TIMELINE PLANEJADA (modo atual) ======
        function renderTimelinePlanejado(json) {
            console.log('📊 Renderizando timeline PLANEJADA');

            // Parsear datas
            const dataIni = parseDataPHP(json.data_ini);
            const dataFim = parseDataPHP(json.data_fim);
            
            if (!dataIni || !dataFim) {
                throw new Error('Datas inválidas na resposta da API');
            }
            
            console.log('✅ Datas parseadas:', dataIni, dataFim);

            // Renderizar cabeçalho de horas
            renderTimelineHeader(dataIni, dataFim);

            // Renderizar linhas
            renderTimelineRows(json.programacoes, dataIni, dataFim);
        }

        // ====== RENDERIZAR TIMELINE HISTÓRICOS ======
        function renderTimelineHistoricos(json) {
            console.log('📊 Renderizando timeline HISTÓRICOS');
            console.log('🔍 Dados recebidos:', json);
            
            if (!json.historicos || json.historicos.length === 0) {
                document.getElementById('timelineBody').innerHTML = '<div class="loading">Sem dados históricos para este período</div>';
                return;
            }

            // Mostrar resumo
            if (json.resumo) {
                console.log('📈 Resumo:', json.resumo);
            }

            // Converter históricos para formato compatível com timeline
            const dataIni = new Date();
            const dataFim = new Date();
            
            // Usar período baseado nos dados
            const datesInData = json.historicos.map(h => {
                const dataStr = h.data_execucao ? h.data_execucao.substring(0, 10) : '2026-04-01';
                const horaStr = h.hora_inicio_realizado || h.hora_inicio_planejada || '00:00';
                return new Date(`${dataStr}T${horaStr}:00`);
            });
            const minDate = new Date(Math.min(...datesInData.map(d => d.getTime())));
            const maxDate = new Date(Math.max(...datesInData.map(d => d.getTime())));
            
            dataIni.setTime(minDate.getTime());
            dataFim.setTime(maxDate.getTime());
            dataFim.setHours(dataFim.getHours() + 1);
            
            console.log('📅 Período dos históricos:', dataIni.toLocaleString(), 'até', dataFim.toLocaleString());
            
            // Renderizar cabeçalho
            renderTimelineHeader(dataIni, dataFim);
            
            // Agrupar históricos por programa
            const porPrograma = {};
            json.historicos.forEach(h => {
                if (!porPrograma[h.prg_id]) {
                    porPrograma[h.prg_id] = {
                        id: h.prg_id,
                        numero_op: h.numero_op,
                        linha: h.linha,
                        eficiencia: h.eficiencia_prg,
                        linhas: []
                    };
                }
                porPrograma[h.prg_id].linhas.push(h);
            });
            
            const programacoes = Object.values(porPrograma);
            console.log('✅ Programas agrupadas:', programacoes.length, '| Total itens:', json.historicos.length);
            
            // Renderizar linhas (históricos)
            renderTimelineRowsHistoricos(programacoes, dataIni, dataFim);
        }

        // ====== RENDERIZAR CABEÇALHO ======
        function renderTimelineHeader(dataIni, dataFim) {
            renderTimelineWeeks(dataIni, dataFim);
            
            const container = document.getElementById('timelineHeaderContent');
            let html = '';

            let current = new Date(dataIni);
            while (current < dataFim) {
                const horas = current.getHours().toString().padStart(2, '0') + ':00';
                html += `<div class="timeline-hour">${horas}</div>`;
                current.setHours(current.getHours() + 1);
            }

            container.innerHTML = html;
        }

        // ====== RENDERIZAR SEMANAS ======
        function renderTimelineWeeks(dataIni, dataFim) {
            const container = document.getElementById('timelineWeeksContent');
            let html = '';

            let current = new Date(dataIni);
            let weekNum = getWeekNumber(current);
            let currentWeekStart = new Date(current);
            let weekWidth = 0;

            while (current < dataFim) {
                const newWeek = getWeekNumber(current);
                if (newWeek !== weekNum) {
                    // Encerrar semana anterior
                    html += `<div class="timeline-week" style="width: ${weekWidth * 60}px;">Week ${weekNum}</div>`;
                    weekNum = newWeek;
                    weekWidth = 0;
                }
                weekWidth++;
                current.setHours(current.getHours() + 1);
            }

            // Última semana
            if (weekWidth > 0) {
                html += `<div class="timeline-week" style="width: ${weekWidth * 60}px;">Week ${weekNum}</div>`;
            }

            container.innerHTML = html;
        }

        // ====== UTIL: Número da semana ======
        function getWeekNumber(date) {
            const d = new Date(Date.UTC(date.getFullYear(), date.getMonth(), date.getDate()));
            const dayNum = d.getUTCDay() || 7;
            d.setUTCDate(d.getUTCDate() + 4 - dayNum);
            const yearStart = new Date(Date.UTC(d.getUTCFullYear(), 0, 1));
            return Math.ceil((((d - yearStart) / 86400000) + 1) / 7);
        }

        // ====== RENDERIZAR LINHAS (COM SUPORTE A FILTROS) ======
        function renderTimelineRows(programacoes, dataIni, dataFim) {
            const duracao = dataFim - dataIni; // ms

            const container = document.getElementById('timelineBody');
            let html = '';

            // Flatten e armazenar todos os dados para filtro
            allLinhasData = [];

            programacoes.forEach(prog => {
                prog.linhas.forEach(linha => {
                    allLinhasData.push({
                        prg_id: prog.id,
                        numero_op: prog.numero_op,
                        linha_codigo: prog.linha,
                        eficiencia: prog.eficiencia,
                        sch_id: linha.id,
                        ...linha
                    });
                });
                
                // Linha principal com programação
                html += `
                    <div class="timeline-row" data-prg-id="${prog.id}">
                        <div class="timeline-row-label">
                            <div class="timeline-row-label-op">${prog.numero_op}</div>
                            <div class="timeline-row-label-meta">${prog.linha} · ${Number(prog.eficiencia).toFixed(0)}%</div>
                        </div>
                        <div class="timeline-row-content" id="content-${prog.id}">
                            ${renderScheduleLinhas(prog.linhas, dataIni, dataFim, duracao)}
                        </div>
                    </div>
                    <div class="timeline-row-expanded" id="expanded-${prog.id}"></div>
                `;
            });

            container.innerHTML = html || '<div class="loading">Sem dados para este período</div>';
            
            // Attach listeners após renderizar
            attachTimelineEventListeners();
            applyFilters();
        }

        // ====== RENDERIZAR BARRAS DE SCHEDULE (COM TOOLTIPS E INTERATIVIDADE) ======
        function renderScheduleLinhas(linhas, dataIni, dataFim, duracao) {
            let html = '';

            linhas.forEach((linha, idx) => {
                try {
                    // Parsear data - formato: "2026-04-06"
                    const dataStr = linha.data_inicio ? linha.data_inicio.substring(0, 10) : '2026-04-06';
                    const horaIniStr = linha.hora_inicio || '00:00';
                    const horaFimStr = linha.hora_fim || '00:00';
                    
                    // Montar datas
                    const dataInicio = new Date(`${dataStr}T${horaIniStr}:00`);
                    const dataFimLinha = new Date(`${dataStr}T${horaFimStr}:00`);

                    // Validar
                    if (isNaN(dataInicio.getTime()) || isNaN(dataFimLinha.getTime())) {
                        console.warn('⚠️ Data inválida:', linha);
                        return;
                    }

                    // Se fim for menor que início (passou de meia-noite), adiciona um dia
                    if (dataFimLinha < dataInicio) {
                        dataFimLinha.setDate(dataFimLinha.getDate() + 1);
                    }

                    // Fora do período?
                    if (dataFimLinha < dataIni || dataInicio > dataFim) {
                        return;
                    }

                    const distInicio = Math.max(0, dataInicio - dataIni);
                    const distFim = Math.min(duracao, dataFimLinha - dataIni);
                    const duracaoVis = distFim - distInicio;

                    if (duracaoVis <= 0) return;

                    const posLeft = (distInicio / duracao) * 100;
                    const width = (duracaoVis / duracao) * 100;

                    const tipos = {
                        'setup': 'setup',
                        'producao': 'producao',
                        'Setup': 'setup',
                        'Produção': 'producao',
                        'Pausa': 'pausa',
                        'Manutenção': 'manutencao'
                    };

                    const tipo = tipos[linha.tipo] || 'producao';
                    const label = `${linha.sku} (${formataDuracao(linha.duracao_minutos)})`;
                    const barId = `bar-${linha.id}-${idx}`;
                    const statusExe = linha.foi_executado ? 'executado' : 'planejado';

                    html += `
                        <div class="timeline-bar ${tipo}" 
                             id="${barId}"
                             data-sch-id="${linha.id}"
                             data-sku="${linha.sku ? linha.sku.toUpperCase() : ''}"
                             data-tipo="${tipo}"
                             data-status="${statusExe}"
                             style="left: ${posLeft}%; width: ${Math.max(1, width)}%;" 
                             title="${label}"
                             onmouseover="mostrarTooltip(event, ${JSON.stringify(linha).replace(/"/g, '&quot;')})"
                             onmouseout="esconderTooltip()"
                             onclick="expandirLinha(event, ${linha.id})">
                            ${width > 8 ? label : ''}
                        </div>
                    `;
                } catch (err) {
                    console.error('❌ Erro ao renderizar barra:', err, linha);
                }
            });

            return html;
        }

        // ====== TOOLTIP RICO (FASE 2) ======
        function mostrarTooltip(event, linha) {
            const x = event.clientX;
            const y = event.clientY;

            const duraçãoReal = linha.fim_producao 
                ? calcularDuracao(linha.inicio_producao, linha.fim_producao)
                : null;

            let html = `
                <div class="tooltip-line">
                    <span class="tooltip-label">SKU:</span>
                    <span class="tooltip-value">${linha.sku || 'N/A'}</span>
                </div>
                <div class="tooltip-line">
                    <span class="tooltip-label">Tipo:</span>
                    <span class="tooltip-value">${linha.tipo}</span>
                </div>
                <div class="tooltip-line">
                    <span class="tooltip-label">Duração (plan.):</span>
                    <span class="tooltip-value">${formataDuracao(linha.duracao_minutos)}</span>
                </div>
            `;

            if (linha.quantidade) {
                html += `
                    <div class="tooltip-line">
                        <span class="tooltip-label">Qtd.:</span>
                        <span class="tooltip-value">${Number(linha.quantidade).toFixed(0)}</span>
                    </div>
                `;
            }

            if (linha.sku_anterior) {
                html += `
                    <div class="tooltip-line">
                        <span class="tooltip-label">De:</span>
                        <span class="tooltip-value">${linha.sku_anterior}</span>
                    </div>
                `;
            }

            if (duraçãoReal) {
                html += `
                    <div class="tooltip-section">
                        <div class="tooltip-line">
                            <span class="tooltip-label">⏱️ Duração (real):</span>
                            <span class="tooltip-value">${duraçãoReal}</span>
                        </div>
                    </div>
                `;
            }

            if (linha.foi_executado) {
                html += `
                    <div class="tooltip-section">
                        <div class="tooltip-line">
                            <span class="tooltip-label">✓ Executado:</span>
                            <span class="tooltip-value">Sim</span>
                        </div>
                        ${linha.produzido_estimado ? `
                        <div class="tooltip-line">
                            <span class="tooltip-label">Produzido:</span>
                            <span class="tooltip-value">${Number(linha.produzido_estimado).toFixed(0)}</span>
                        </div>
                        ` : ''}
                    </div>
                `;
            }

            let tooltip = document.getElementById('tooltip');
            if (!tooltip) {
                tooltip = document.createElement('div');
                tooltip.id = 'tooltip';
                tooltip.className = 'tooltip';
                document.body.appendChild(tooltip);
            }

            tooltip.innerHTML = html;
            tooltip.classList.add('visible');
            tooltip.style.left = x + 10 + 'px';
            tooltip.style.top = y + 10 + 'px';
        }

        function esconderTooltip() {
            const tooltip = document.getElementById('tooltip');
            if (tooltip) {
                tooltip.classList.remove('visible');
            }
        }

        // ====== CLICK INTERACTIONS (FASE 3) ======
        function expandirLinha(event, prgId) {
            event.stopPropagation();
            
            const row = document.querySelector(`[data-prg-id="${prgId}"]`);
            const expanded = document.getElementById(`expanded-${prgId}`);
            
            if (!row || !expanded) return;

            // Toggle expanded state
            const wasExpanded = row.classList.contains('expanded');
            
            // Close all others
            document.querySelectorAll('.timeline-row.expanded').forEach(r => {
                r.classList.remove('expanded');
            });
            document.querySelectorAll('.timeline-row-expanded').forEach(e => {
                e.innerHTML = '';
            });

            if (wasExpanded) {
                row.classList.remove('expanded');
                return;
            }

            row.classList.add('expanded');

            // Fetch detailed data
            fetcheExpanidoDados(prgId, expanded);
        }

        async function fetcheExpanidoDados(prgId, container) {
            try {
                const response = await fetch(apiBase + '?action=detalhe&id=' + prgId);
                const json = await response.json();

                if (!json.sucesso || !json.programacao) {
                    container.innerHTML = '<div style="color: #dc2626;">Erro ao carregar detalhes</div>';
                    return;
                }

                const prog = json.programacao;
                let html = '<div class="timeline-row-expanded-content">';

                html += `
                    <div class="timeline-row-expanded-item">
                        <div class="timeline-row-expanded-label">OP</div>
                        <div class="timeline-row-expanded-value">${prog.numero_op}</div>
                    </div>
                    <div class="timeline-row-expanded-item">
                        <div class="timeline-row-expanded-label">Linha</div>
                        <div class="timeline-row-expanded-value">${prog.linha}</div>
                    </div>
                    <div class="timeline-row-expanded-item">
                        <div class="timeline-row-expanded-label">Eficiência</div>
                        <div class="timeline-row-expanded-value">${Number(prog.eficiencia).toFixed(1)}%</div>
                    </div>
                    <div class="timeline-row-expanded-item">
                        <div class="timeline-row-expanded-label">Status</div>
                        <div class="timeline-row-expanded-value">${prog.status}</div>
                    </div>
                `;

                if (json.schedule && Array.isArray(json.schedule)) {
                    const executados = json.schedule.filter(s => s.sch_fim_producao).length;
                    html += `
                        <div class="timeline-row-expanded-item">
                            <div class="timeline-row-expanded-label">Itens Executados</div>
                            <div class="timeline-row-expanded-value executado">${executados}/${json.schedule.length}</div>
                        </div>
                    `;
                }

                html += '</div>';
                container.innerHTML = html;

            } catch (err) {
                console.error('❌ Erro ao buscar detalhes:', err);
                container.innerHTML = `<div style="color: #dc2626;">Erro: ${err.message}</div>`;
            }
        }

        // ====== FILTROS (FASE 4) ======
        function applyFilters() {
            let rows = 0, hidden = 0;
            
            document.querySelectorAll('.timeline-row').forEach(row => {
                const bars = row.querySelectorAll('.timeline-bar');
                let hasVisibleBar = false;

                bars.forEach(bar => {
                    const sku = bar.dataset.sku || '';
                    const tipo = bar.dataset.tipo || '';
                    const status = bar.dataset.status || '';

                    const matchSku = !currentFilters.sku || sku.includes(currentFilters.sku);
                    const matchTipo = !currentFilters.tipo || tipo === currentFilters.tipo;
                    const matchStatus = !currentFilters.status || status === currentFilters.status;

                    if (matchSku && matchTipo && matchStatus) {
                        bar.style.opacity = '1';
                        hasVisibleBar = true;
                    } else {
                        bar.style.opacity = '0.3';
                    }
                });

                rows++;
                if (hasVisibleBar) {
                    row.style.display = 'flex';
                } else {
                    row.style.display = 'none';
                    hidden++;
                }
            });

            // Update filter status
            let statusText = '';
            if (currentFilters.sku) statusText += `SKU: ${currentFilters.sku} | `;
            if (currentFilters.tipo) statusText += `Tipo: ${currentFilters.tipo} | `;
            if (currentFilters.status) statusText += `Status: ${currentFilters.status} | `;
            
            if (statusText) {
                statusText += `${rows - hidden}/${rows} visíveis`;
            }

            const statusInfo = document.getElementById('filterStatusInfo');
            if (statusInfo) {
                statusInfo.textContent = statusText;
            }
        }

        function attachTimelineEventListeners() {
            document.querySelectorAll('.timeline-bar').forEach(bar => {
                bar.addEventListener('mouseover', (e) => {
                    e.currentTarget.style.zIndex = '50';
                });
                bar.addEventListener('mouseout', (e) => {
                    e.currentTarget.style.zIndex = 'auto';
                });
            });
        }

        // ====== RENDERIZAR BARRAS HISTÓRICAS (COM DESVIOS) ======
        function renderTimelineRowsHistoricos(programacoes, dataIni, dataFim) {
            const duracaoMs = dataFim - dataIni;
            const container = document.getElementById('timelineBody');
            const containerParent = container.parentElement;
            
            // Verificar se "hoje" está no período
            const hoje = new Date();
            let hojeContido = false;
            let posHojePerc = 0;
            if (dataIni <= hoje && hoje <= dataFim) {
                hojeContido = true;
                posHojePerc = ((hoje - dataIni) / duracaoMs) * 100;
            }
            
            let html = '';

            programacoes.forEach((prog, progIdx) => {
                const progId = prog.id || prog.prg_id;
                const statusClass = prog.linhas?.length > 0 ? 'expandible' : '';
                const classePrimeira = progIdx === 0 ? ' primeira-prog' : '';
                
                html += `
                    <div class="timeline-row${classePrimeira} ${statusClass}" data-prg-id="${progId}" onclick="selecionarProgramacao(${progId})">
                        <div class="timeline-row-label" style="cursor: pointer; background: #f7fafc; border-right: 2px solid #cbd5e0;">
                            <div class="timeline-row-label-op">${prog.numero_op || 'OP-?'}</div>
                            <div class="timeline-row-label-meta">${prog.linha || ''} • ${prog.linhas?.length || 0} itens</div>
                        </div>
                        <div class="timeline-row-content" style="position: relative; background: linear-gradient(90deg, transparent 0%, transparent calc(100% - 1px), #e2e8f0 calc(100% - 1px)); background-size: 60px 100%;">
                            ${renderHistoricoBars(prog.linhas || [], dataIni, dataFim, duracaoMs)}
                        </div>
                    </div>
                    <div class="timeline-row-expanded" id="expanded-${progId}"></div>
                `;
            });

            container.innerHTML = html || '<div class="loading" style="padding: 20px; text-align: center; color: #718096;">Sem dados históricos para este período</div>';
            
            // Adicionar linha de referência "hoje" com Z-index alto
            if (hojeContido) {
                const linhaHoje = document.createElement('div');
                linhaHoje.className = 'linha-hoje-referencia';
                linhaHoje.style.cssText = `
                    position: absolute;
                    left: calc(200px + ${posHojePerc}%);
                    top: 0;
                    width: 2px;
                    height: 100%;
                    background: #dc2626;
                    z-index: 100;
                    opacity: 0.9;
                    box-shadow: 1px 0 3px rgba(220, 38, 38, 0.4);
                `;
                containerParent.style.position = 'relative';
                containerParent.appendChild(linhaHoje);
            }
            
            // Attach listeners após renderizar
            attachTimelineEventListeners();
            applyFilters();
        }

        // ====== RENDERIZAR BARRAS DE HISTÓRICO (COM DESVIOS) ======
        function renderHistoricoBars(linhas, dataIni, dataFim, duracao) {
            let html = '';

            linhas.forEach((linha, idx) => {
                try {
                    // Usar sch_inicio_planejado se disponível, senão construir a partir de data + hora
                    let dataInicio;
                    if (linha.sch_inicio_planejado) {
                        dataInicio = new Date(linha.sch_inicio_planejado.replace(' ', 'T'));
                    } else {
                        const dataStr = linha.data_execucao ? linha.data_execucao.substring(0, 10) : null;
                        const horaIniStr = linha.hora_inicio_planejada || '00:00';
                        if (!dataStr) {
                            console.warn('⚠️ Sem data de execução:', linha);
                            return;
                        }
                        dataInicio = new Date(`${dataStr}T${horaIniStr}:00`);
                    }
                    
                    // Usar duração real se disponível
                    const duracaoMinutos = linha.duracao_real_minutos || 0;
                    const dataFimLinha = new Date(dataInicio.getTime() + duracaoMinutos * 60 * 1000);

                    // Validar
                    if (isNaN(dataInicio.getTime()) || isNaN(dataFimLinha.getTime())) {
                        console.warn('⚠️ Data inválida:', linha);
                        return;
                    }

                    // Fora do período?
                    if (dataFimLinha < dataIni || dataInicio > dataFim) {
                        return;
                    }

                    const distInicio = Math.max(0, dataInicio - dataIni);
                    const distFim = Math.min(duracao, dataFimLinha - dataIni);
                    const duracaoVis = distFim - distInicio;

                    if (duracaoVis <= 0) return;

                    const posLeft = (distInicio / duracao) * 100;
                    const width = (duracaoVis / duracao) * 100;

                    // Determinar classe de desvio
                    let desvioClass = 'on-time';
                    let desvioLabel = 'No prazo';
                    let corFundo = '#10b981';
                    let icone = '✓';
                    
                    const desvio = linha.desvio_minutos || 0;
                    const desvioPerc = linha.desvio_percentual || 0;
                    
                    if (desvio > 5) {
                        desvioClass = 'delayed';
                        desvioLabel = `+${desvio}m`;
                        corFundo = '#f97316';
                        icone = '⚠ ';
                    } else if (desvio < -5) {
                        desvioClass = 'early';
                        desvioLabel = `${desvio}m`;
                        corFundo = '#06b6d4';
                        icone = '↑ ';
                    }

                    const skuLabel = linha.sku || 'SKU';
                    const tempoLabel = `${duracaoMinutos}m`;
                    const displayLabel = width > 12 ? `${icone}${skuLabel}` : '';
                    const barId = `bar-hist-${linha.sch_id}-${idx}`;

                    html += `
                        <div class="timeline-bar ${desvioClass}" 
                             id="${barId}"
                             data-sch-id="${linha.sch_id}"
                             data-sku="${skuLabel.toUpperCase()}"
                             data-tipo="${linha.tipo || 'producao'}"
                             data-status="executado"
                             style="left: ${posLeft}%; width: ${Math.max(2, width)}%; background: ${corFundo};" 
                             title="${skuLabel} | ${tempoLabel} | ${desvioLabel}"
                             onmouseover="mostrarTooltipHistorico(event, ${JSON.stringify(linha).replace(/"/g, '&quot;')})"
                             onmouseout="esconderTooltip()"
                             onclick="event.stopPropagation(); expandirLinhaHistorica(event, ${linha.sch_id})">
                            <span style="font-size: 11px; font-weight: 600;">${displayLabel}</span>
                        </div>
                    `;
                } catch (err) {
                    console.error('❌ Erro ao renderizar barra histórica:', err, linha);
                }
            });

            return html;
        }

        // ====== TOOLTIP PARA HISTÓRICOS ======
        function mostrarTooltipHistorico(event, linha) {
            const x = event.clientX;
            const y = event.clientY;

            const desvio = linha.desvio_minutos || 0;
            const desvioPerc = linha.desvio_percentual || 0;
            const duraçãoReal = linha.duracao_real_minutos || 0;
            const duraçãoPlanejada = linha.duracao_planejada_minutos || 0;
            
            // Detectar se há dados incompletos
            let alertaData = '';
            if (duraçãoReal === 0 && duraçãoPlanejada > 0) {
                alertaData = '<div style="color: #fca5a5; margin-bottom: 6px; font-size: 11px;">⚠️ Duração real não calculada (dados incompletos)</div>';
            }
            
            const statusColor = desvio > 5 ? '#ef4444' : (desvio < -5 ? '#06b6d4' : '#10b981');
            const statusText = desvio > 5 ? '⚠ Atrasado' : (desvio < -5 ? '↑ Adiantado' : '✓ No prazo');

            let html = `
                <div style="padding: 12px 14px; background: #1f2937; color: #f3f4f6; border-radius: 6px; max-width: 320px; font-size: 12px; z-index: 9999; box-shadow: 0 4px 12px rgba(0,0,0,0.3); border: 1px solid #374151;">
                    <div style="font-weight: bold; font-size: 13px; margin-bottom: 8px; color: #fff;">${linha.sku} | ${linha.numero_op}</div>
                    
                    ${alertaData}
                    
                    <div style="background: #111827; padding: 8px; border-radius: 4px; margin-bottom: 8px;">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 4px;">
                            <span style="color: #9ca3af;">Planejado:</span>
                            <strong style="color: #fff;">${duraçãoPlanejada}m</strong>
                        </div>
                        <div style="display: flex; justify-content: space-between; color: ${duraçãoReal === 0 ? '#fca5a5' : '#f3f4f6'};">
                            <span style="color: #9ca3af;">Realizado:</span>
                            <strong>${duraçãoReal}m ${duraçãoReal === 0 ? '(sem dados)' : ''}</strong>
                        </div>
                    </div>
                    
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 6px; background: ${statusColor}33; border-radius: 4px; border-left: 3px solid ${statusColor};">
                        <div>
                            <div style="color: #9ca3af; font-size: 11px;">Desvio</div>
                            <strong style="color: ${statusColor}; font-size: 13px;">${duraçãoReal === 0 ? '—' : (desvio > 0 ? '+' : '') + desvio + 'm (' + desvioPerc.toFixed(1) + '%)'}</strong>
                        </div>
                        <div style="color: ${statusColor}; font-weight: bold; font-size: 11px; text-align: right;">${statusText}</div>
                    </div>
                </div>
            `;

            const tooltip = document.getElementById('tooltip') || document.createElement('div');
            tooltip.id = 'tooltip';
            tooltip.style.cssText = `
                position: fixed;
                left: ${Math.min(x, window.innerWidth - 340)}px;
                top: ${y - 10}px;
                pointer-events: none;
                z-index: 9999;
            `;
            tooltip.innerHTML = html;
            document.body.appendChild(tooltip);
        }

        // ====== EXPANDIR LINHA HISTÓRICA ======
        function expandirLinhaHistorica(event, linhaId) {
            console.log('📍 Expandir linha histórica:', linhaId);
            event.stopPropagation();
            // Implementar conforme necessário
        }

        // ====== RENDERIZAR REFERÊNCIA ======
        function renderTimelineReference(dataIniStr, dataFimStr) {
            const dataIni = new Date(dataIniStr);
            const dataFim = new Date(dataFimStr);
            
            const container = document.getElementById('timelineRefContent');
            let html = '';

            let current = new Date(dataIni);
            while (current < dataFim) {
                const dia = current.getDate().toString().padStart(2, '0');
                const mes = (current.getMonth() + 1).toString().padStart(2, '0');
                const ref = `${dia}/${mes}`;
                html += `<div class="timeline-ref-mark">${ref}</div>`;
                current.setHours(current.getHours() + 1);
            }

            container.innerHTML = html;
        }

        // ====== UTIL: Formatar duração ======
        function formataDuracao(minutos) {
            if (!minutos) return '0m';
            const h = Math.floor(minutos / 60);
            const m = minutos % 60;
            if (h > 0) {
                return `${h}h${m > 0 ? m + 'm' : ''}`;
            }
            return `${m}m`;
        }

        // ====== UTIL: Calcular duração entre datas ======
        function calcularDuracao(dataInicio, dataFim) {
            if (!dataInicio || !dataFim) return null;
            try {
                const ini = typeof dataInicio === 'string' ? new Date(dataInicio.replace(' ', 'T')) : dataInicio;
                const fim = typeof dataFim === 'string' ? new Date(dataFim.replace(' ', 'T')) : dataFim;
                const minutos = Math.floor((fim - ini) / (1000 * 60));
                return formataDuracao(minutos);
            } catch (e) {
                return null;
            }
        }

        // ====== Selecionar programação ======
        function selecionarProgramacao(prgId) {
            console.log('📌 Selecionado programação:', prgId);
            // Highlight na timeline
            document.querySelectorAll(`[data-prg-id]`).forEach(el => {
                el.style.backgroundColor = el.dataset.prgId == prgId ? '#edf2f7' : '';
            });
        }
    </script>
</body>
</html>
