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
            min-height: 44px;
            border-bottom: 1px solid #edf2f7;
            align-items: stretch;
        }

        .timeline-row:hover {
            background: #f7fafc;
        }

        .timeline-row-label {
            width: 200px;
            flex-shrink: 0;
            padding: 8px 16px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            border-right: 1px solid #cbd5e0;
            background: white;
        }

        .timeline-row-label-op {
            font-size: 12px;
            font-weight: 600;
            color: #2d3748;
        }

        .timeline-row-label-meta {
            font-size: 11px;
            color: #718096;
        }

        .timeline-row-content {
            flex: 1;
            position: relative;
            background: linear-gradient(90deg, transparent 0%, transparent calc(100% - 1px), #e2e8f0 calc(100% - 1px));
            background-size: 60px 100%;
        }

        /* Barras */
        .timeline-bar {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            height: 28px;
            border-radius: 3px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 10px;
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
        // ====== STATE ======
        let currentPeriodo = '4h';
        let syncScroll = true;

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

            // Setup periodo buttons
            document.querySelectorAll('.periodo-btn').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    document.querySelectorAll('.periodo-btn').forEach(b => b.classList.remove('active'));
                    e.target.classList.add('active');
                    currentPeriodo = e.target.dataset.periodo;
                    renderTimeline();
                });
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

        // ====== RENDERIZAR TIMELINE ======
        async function renderTimeline() {
            try {
                console.log('📊 Renderizando timeline para período:', currentPeriodo);

                const response = await fetch(apiBase + '?action=timeline&periodo=' + currentPeriodo);
                
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}`);
                }

                const json = await response.json();
                
                if (!json.sucesso) {
                    throw new Error(json.erro || 'Erro desconhecido');
                }

                console.log('✅ Timeline data:', json);
                console.log('📊 Programações:', json.programacoes);
                console.log('📅 Período:', json.data_ini, 'a', json.data_fim);

                // Renderizar cabeçalho de horas
                renderTimelineHeader(json.data_ini, json.data_fim);

                // Renderizar linhas
                renderTimelineRows(json.programacoes, json.data_ini, json.data_fim);

            } catch (err) {
                console.error('❌ Erro ao renderizar timeline:', err);
                document.getElementById('timelineBody').innerHTML = `
                    <div class="loading" style="color: #dc2626;">
                        Erro ao carregar timeline: ${err.message}
                    </div>
                `;
            }
        }

        // ====== RENDERIZAR CABEÇALHO ======
        function renderTimelineHeader(dataIniStr, dataFimStr) {
            const dataIni = new Date(dataIniStr);
            const dataFim = new Date(dataFimStr);
            
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

        // ====== RENDERIZAR LINHAS ======
        function renderTimelineRows(programacoes, dataIniStr, dataFimStr) {
            const dataIni = new Date(dataIniStr);
            const dataFim = new Date(dataFimStr);
            const duracao = dataFim - dataIni; // ms

            const container = document.getElementById('timelineBody');
            let html = '';

            programacoes.forEach(prog => {
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
                `;
            });

            container.innerHTML = html || '<div class="loading">Sem dados para este período</div>';
        }

        // ====== RENDERIZAR BARRAS DE SCHEDULE ======
        function renderScheduleLinhas(linhas, dataIni, dataFim, duracao) {
            let html = '';

            linhas.forEach(linha => {
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
                        'Produção': 'producao',
                        'Setup': 'setup',
                        'Pausa': 'pausa',
                        'Manutenção': 'manutencao'
                    };

                    const tipo = tipos[linha.tipo] || 'producao';
                    const label = `${linha.sku} (${formataDuracao(linha.duracao_minutos)})`;

                    html += `
                        <div class="timeline-bar ${tipo}" 
                             style="left: ${posLeft}%; width: ${Math.max(1, width)}%;" 
                             title="${label}">
                            ${width > 8 ? label : ''}
                        </div>
                    `;
                } catch (err) {
                    console.error('❌ Erro ao renderizar barra:', err, linha);
                }
            });

            return html;
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
            const h = Math.floor(minutos / 60);
            const m = minutos % 60;
            if (h > 0) {
                return `${h}h${m > 0 ? m + 'm' : ''}`;
            }
            return `${m}m`;
        }

        // ====== Selecionar programação ======
        function selecionarProgramacao(prgId) {
            console.log('📌 Selecionado programação:', prgId);
            // TODO: Highlight na timeline
        }
    </script>
</body>
</html>
