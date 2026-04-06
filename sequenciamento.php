<?php

declare(strict_types=1);

require __DIR__ . '/src/bootstrap.php';

use App\Auth\Auth;

Auth::startSession();
Auth::requireLogin();

// Se tem ID na query, passa para o JS
$prgId = (int) ($_GET['id'] ?? 0);

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gráfico de Sequenciamento - ControlePCP</title>
    
    <!-- Frappe Gantt CDN -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/frappe-gantt@0.6.1/dist/frappe-gantt.css">
    <script src="https://cdn.jsdelivr.net/npm/frappe-gantt@0.6.1/dist/frappe-gantt.js"></script>

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            padding: 20px;
            color: #333;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
        }

        header {
            background: white;
            padding: 24px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            margin-bottom: 24px;
        }

        header h1 {
            font-size: 28px;
            margin-bottom: 8px;
            color: #1a202c;
        }

        header p {
            color: #718096;
            font-size: 14px;
        }

        .header-controls {
            display: flex;
            gap: 12px;
            margin-top: 16px;
            flex-wrap: wrap;
            align-items: center;
        }

        .header-controls select {
            padding: 8px 12px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            font-size: 14px;
            background: white;
            cursor: pointer;
            min-width: 200px;
        }

        .header-controls select:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .btn {
            padding: 8px 16px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            background: white;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.2s;
            font-weight: 500;
            color: #4b5563;
        }

        .btn:hover {
            background: #f1f5f9;
            border-color: #94a3b8;
        }

        .btn.primary {
            background: #3b82f6;
            color: white;
            border-color: #3b82f6;
        }

        .btn.primary:hover {
            background: #2563eb;
            border-color: #2563eb;
        }

        .main-content {
            display: grid;
            grid-template-columns: 250px 1fr;
            gap: 20px;
        }

        .sidebar {
            background: white;
            padding: 16px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            height: fit-content;
            max-height: 75vh;
            overflow-y: auto;
        }

        .sidebar h3 {
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #718096;
            margin-bottom: 12px;
            font-weight: 600;
        }

        .sidebar-list {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .sidebar-item {
            padding: 10px 12px;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.2s;
            font-size: 13px;
            background: #f8fafc;
        }

        .sidebar-item:hover {
            background: #eef2f5;
            border-color: #cbd5e1;
        }

        .sidebar-item.active {
            background: #3b82f6;
            color: white;
            border-color: #3b82f6;
        }

        .sidebar-item-op {
            font-weight: 600;
            color: #1a202c;
        }

        .sidebar-item-meta {
            display: block;
            font-size: 12px;
            color: #a0aec0;
            margin-top: 2px;
        }

        .sidebar-item.active .sidebar-item-meta {
            color: rgba(255, 255, 255, 0.8);
        }

        .content-area {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .content-header {
            margin-bottom: 20px;
            padding-bottom: 16px;
            border-bottom: 1px solid #e2e8f0;
        }

        .content-header h2 {
            font-size: 18px;
            color: #1a202c;
            margin-bottom: 8px;
        }

        .content-meta {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 12px;
            font-size: 13px;
        }

        .meta-item {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #f1f5f9;
        }

        .meta-label {
            color: #718096;
            font-weight: 500;
        }

        .meta-value {
            color: #1a202c;
            font-weight: 600;
        }

        .gantt-container {
            margin-top: 20px;
            background: #f8fafc;
            border-radius: 8px;
            overflow: hidden;
            border: 1px solid #e2e8f0;
        }

        #gantt {
            width: 100%;
            background: white;
        }

        /* Frappe Gantt customization */
        .gantt-container ::-webkit-scrollbar {
            height: 8px;
            width: 8px;
        }

        .gantt-container ::-webkit-scrollbar-track {
            background: #f1f5f9;
        }

        .gantt-container ::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 4px;
        }

        .gantt-container ::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }

        /* Task colors */
        .bar.task-setup {
            background: #EA580C !important;
            stroke: #D94600 !important;
        }

        .bar.task-produção {
            background: #3B82F6 !important;
            stroke: #2563EB !important;
        }

        .bar.task-pausa {
            background: #F8B4D1 !important;
            stroke: #F08DB8 !important;
        }

        .bar.task-manutencao {
            background: #8B5CF6 !important;
            stroke: #7C3AED !important;
        }

        .bar-label {
            font-size: 12px;
            font-weight: 500;
        }

        /* Falha ao carregar */
        .error-state {
            padding: 40px;
            text-align: center;
            color: #dc2626;
        }

        .error-state svg {
            width: 64px;
            height: 64px;
            margin-bottom: 16px;
            opacity: 0.5;
        }

        .loading-state {
            padding: 40px;
            text-align: center;
            color: #64748b;
        }

        .spinner {
            display: inline-block;
            width: 32px;
            height: 32px;
            border: 3px solid #e2e8f0;
            border-top-color: #3b82f6;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
            margin-bottom: 16px;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .legend {
            display: flex;
            gap: 20px;
            margin-bottom: 16px;
            flex-wrap: wrap;
            font-size: 13px;
        }

        .legend-item {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .legend-color {
            width: 20px;
            height: 12px;
            border-radius: 2px;
            border: 1px solid rgba(0, 0, 0, 0.1);
        }

        @media (max-width: 768px) {
            .main-content {
                grid-template-columns: 1fr;
            }

            .sidebar {
                max-height: none;
            }

            .header-controls {
                flex-direction: column;
            }

            .header-controls select,
            .btn {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1>📊 Gráfico de Sequenciamento</h1>
            <p>Visualize a sequência de produção em timeline interativa com dados do CODI</p>
            
            <div class="header-controls">
                <select id="programacaoSelect" onchange="carregarProgramacao()">
                    <option value="">-- Selecione uma programação --</option>
                </select>
                <button class="btn primary" onclick="atualizarGantt()">Atualizar</button>
                <button class="btn" onclick="exportarPDF()">Exportar PDF</button>
            </div>
        </header>

        <div class="main-content">
            <aside class="sidebar">
                <h3>Programações com Dados</h3>
                <div class="sidebar-list" id="sidebarList">
                    <div style="color: #a0aec0; font-size: 12px; padding: 8px; text-align: center;">
                        Carregando...
                    </div>
                </div>
            </aside>

            <div class="content-area">
                <div id="contentHeader" style="display: none;">
                    <div class="content-header">
                        <h2 id="programacaoTitulo">Programação</h2>
                        <div class="content-meta">
                            <div class="meta-item">
                                <span class="meta-label">OP</span>
                                <span class="meta-value" id="metaOp">-</span>
                            </div>
                            <div class="meta-item">
                                <span class="meta-label">Linha</span>
                                <span class="meta-value" id="metaLinha">-</span>
                            </div>
                            <div class="meta-item">
                                <span class="meta-label">Eficiência</span>
                                <span class="meta-value" id="metaEficiencia">-</span>
                            </div>
                            <div class="meta-item">
                                <span class="meta-label">Status</span>
                                <span class="meta-value" id="metaStatus">-</span>
                            </div>
                        </div>
                    </div>

                    <div class="legend">
                        <div class="legend-item">
                            <div class="legend-color" style="background: #3B82F6; border-color: #2563EB;"></div>
                            <span>Produção</span>
                        </div>
                        <div class="legend-item">
                            <div class="legend-color" style="background: #EA580C; border-color: #D94600;"></div>
                            <span>Setup</span>
                        </div>
                        <div class="legend-item">
                            <div class="legend-color" style="background: #F8B4D1; border-color: #F08DB8;"></div>
                            <span>Pausa</span>
                        </div>
                        <div class="legend-item">
                            <div class="legend-color" style="background: #8B5CF6; border-color: #7C3AED;"></div>
                            <span>Manutenção</span>
                        </div>
                    </div>

                    <div class="gantt-container">
                        <div id="gantt"></div>
                    </div>
                </div>

                <div id="emptyState" class="loading-state">
                    <p style="color: #94a3b8;">Selecione uma programação para visualizar o gráfico</p>
                </div>
            </div>
        </div>
    </div>

    <script>
        let ganttInstance = null;
        
        // Detectar caminho correto dinamicamente
        // Suporta: /sequenciamento.php ou /controlepcp_sandbox/sequenciamento.php
        const currentPath = window.location.pathname;
        const pathParts = currentPath.split('/').filter(p => p && p !== 'sequenciamento.php');
        
        // Se tem pasta antes, usa ela. Se não, usa raiz
        const apiBase = pathParts.length > 0 
            ? '/' + pathParts.join('/') + '/api/sequenciamento.php'
            : '/api/sequenciamento.php';
        
        // Capturar prgId da URL
        const urlParams = new URLSearchParams(window.location.search);
        window.urlPrgId = parseInt(urlParams.get('id') || 0);
        
        console.log('🔹 Current Path:', currentPath);
        console.log('🔹 Path Parts:', pathParts);
        console.log('📌 API Base:', apiBase);

        /**
         * Selecionar programação pela sidebar
         */
        function selecionarProgramacao(id) {
            const select = document.getElementById('programacaoSelect');
            select.value = id;
            atualizarGantt();

            // Atualizar sidebar visualmente
            document.querySelectorAll('.sidebar-item').forEach(el => {
                el.classList.remove('active');
            });
            event.target.closest('.sidebar-item').classList.add('active');
        }

        /**
         * Carregar programação do select
         */
        function carregarProgramacao() {
            atualizarGantt();
        }

        /**
         * Atualizar Gantt com programação selecionada
         */
        async function atualizarGantt() {
            const select = document.getElementById('programacaoSelect');
            const prgId = parseInt(select.value);

            if (!prgId || prgId <= 0) {
                document.getElementById('contentHeader').style.display = 'none';
                document.getElementById('emptyState').style.display = 'block';
                return;
            }

            document.getElementById('emptyState').innerHTML = `
                <div class="spinner"></div>
                <p>Carregando programação...</p>
            `;
            document.getElementById('emptyState').style.display = 'block';
            document.getElementById('contentHeader').style.display = 'none';

            try {
                // Buscar dados formatados para Gantt
                const response = await fetch(`${apiBase}?action=gantt&id=${prgId}`);
                const json = await response.json();

                if (!json.sucesso) {
                    throw new Error(json.erro || 'Erro ao carregar');
                }

                const prog = json.programacao;
                const tasks = json.tasks || [];

                // Atualizar metadados
                document.getElementById('programacaoTitulo').textContent = 
                    `Programação ${prog.numero_op || 'N/A'} - ${prog.linha || 'N/A'}`;
                document.getElementById('metaOp').textContent = prog.numero_op || '-';
                document.getElementById('metaLinha').textContent = prog.linha || '-';
                document.getElementById('metaEficiencia').textContent = 
                    Number(prog.eficiencia || 0).toFixed(1) + '%';
                document.getElementById('metaStatus').textContent = 
                    prog.status ? prog.status.charAt(0).toUpperCase() + prog.status.slice(1) : '-';

                // Renderizar Gantt
                renderGantt(tasks);

                // Mostrar conteúdo
                document.getElementById('emptyState').style.display = 'none';
                document.getElementById('contentHeader').style.display = 'block';

            } catch (error) {
                console.error('Erro:', error);
                document.getElementById('emptyState').innerHTML = `
                    <div class="error-state">
                        <p>❌ Erro ao carregar: ${error.message}</p>
                    </div>
                `;
                document.getElementById('emptyState').style.display = 'block';
                document.getElementById('contentHeader').style.display = 'none';
            }
        }

        /**
         * Renderizar gráfico Frappe Gantt
         */
        function renderGantt(tasks) {
            // Destruir gantt anterior se existir
            if (ganttInstance) {
                ganttInstance = null;
                document.getElementById('gantt').innerHTML = '';
            }

            if (!tasks || tasks.length === 0) {
                document.getElementById('gantt').innerHTML = 
                    '<div style="padding: 40px; text-align: center; color: #94a3b8;">Nenhuma tarefa encontrada</div>';
                return;
            }

            // Transformar dados para Frappe Gantt
            const ganttTasks = tasks.map(task => ({
                id: task.id,
                name: task.name,
                start: new Date(task.start),
                end: new Date(task.end),
                progress: task.progress || 0,
                dependencies: task.dependencies || '',
                custom_class: task.custom_class || '',
            }));

            // Criar Gantt
            ganttInstance = new Gantt('#gantt', ganttTasks, {
                header_height: 50,
                column_width: 30,
                step: 24,
                view_modes: ['Quarter Day', 'Half Day', 'Day', 'Week'],
                bar_height: 28,
                bar_corner_radius: 4,
                arrow_curve: 5,
                padding: 18,
                view_mode: 'Week',
                date_format: 'DD MMM',
                popup_trigger: 'click',
                dependencies: true,
                on_click: handleTaskClick,
                on_date_change: handleDateChange,
                on_progress_change: handleProgressChange,
                on_view_change: handleViewChange,
            });

            // Adicionar tooltips customizados
            document.querySelectorAll('.bar').forEach((bar, index) => {
                const task = tasks[index];
                if (task && task.tooltip) {
                    const tooltipText = Object.entries(task.tooltip)
                        .map(([k, v]) => `${k}: ${v}`)
                        .join('\n');
                    bar.title = tooltipText;
                    bar.style.cursor = 'pointer';
                }
            });
        }

        /**
         * Event handlers do Gantt
         */
        function handleTaskClick(task) {
            console.log('Task clicked:', task);
        }

        function handleDateChange(task, start, end) {
            console.log('Date changed:', task.id, start, end);
        }

        function handleProgressChange(task, progress) {
            console.log('Progress changed:', task.id, progress);
        }

        function handleViewChange(mode) {
            console.log('View changed:', mode);
        }

        /**
         * Carregando lista de programações da API
         */
        async function carregarProgramacoes() {
            try {
                console.log('═══════════════════════════════════════');
                console.log('➡️ Iniciando carregamento de programações...');
                console.log('🔗 API Base:', apiBase);
                console.log('═══════════════════════════════════════');
                
                // 1. Teste de conectividade simples
                console.log('\n📋 [ETAPA 1] Testando connectividade básica...');
                const testUrl = apiBase.replace('sequenciamento.php', 'ping.php');
                console.log('🧪 URL de teste:', testUrl);
                
                try {
                    const testResponse = await fetch(testUrl);
                    console.log('✅ Status PING:', testResponse.status, testResponse.statusText);
                } catch (pingErr) {
                    console.warn('⚠️ Ping failed (não é fatal):', pingErr.message);
                }
                
                // 2. Teste de banco de dados
                console.log('\n📋 [ETAPA 2] Testando conexão com banco...');
                const dbTestUrl = apiBase.replace('sequenciamento.php', 'test_db.php');
                console.log('🧪 URL db test:', dbTestUrl);
                
                try {
                    const dbResponse = await fetch(dbTestUrl);
                    const dbData = await dbResponse.json();
                    console.log('✅ DB Test:', dbResponse.status, dbData);
                    if (dbData.sucesso) {
                        console.log('   📊 prg_programas:', dbData.banco.prg_programas);
                        console.log('   📊 sch_linhas:', dbData.banco.sch_linhas);
                    } else {
                        console.warn('⚠️ DB Test falhou:', dbData.erro);
                    }
                } catch (dbErr) {
                    console.warn('⚠️ DB Test error:', dbErr.message);
                }
                
                // 3. Teste de status da API
                console.log('\n📋 [ETAPA 3] Checando status da API...');
                const statusUrl = apiBase + '?action=status';
                console.log('🧪 URL status:', statusUrl);
                
                try {
                    const statusResponse = await fetch(statusUrl);
                    console.log('✅ Status API:', statusResponse.status);
                    const statusData = await statusResponse.json();
                    console.log('   ✅', statusData);
                } catch (statusErr) {
                    console.warn('⚠️ Status check falhou:', statusErr.message);
                }
                
                // 4. Chamar API principal - LISTAR programações
                console.log('\n📋 [ETAPA 4] Buscando programações...');
                const mainUrl = apiBase + '?action=listar&limit=50';
                console.log('📡 URL completa:', mainUrl);
                
                const response = await fetch(mainUrl);
                console.log('📊 Response Status:', response.status, response.statusText);
                
                if (!response.ok) {
                    const errorText = await response.text();
                    console.error('❌ Response Error Body:');
                    console.error(errorText);
                    throw new Error(`API ${response.status}: ${response.statusText}`);
                }
                
                const text = await response.text();
                console.log('✅ Raw Response (primeiros 300 chars):');
                console.log(text.substring(0, 300));
                
                // Verificar se é JSON válido
                let json;
                try {
                    json = JSON.parse(text);
                    console.log('✅ JSON parse OK');
                } catch (jsonErr) {
                    console.error('❌ JSON Parse Error:', jsonErr.message);
                    console.error('❌ Raw text:', text);
                    throw jsonErr;
                }

                if (!json.sucesso) {
                    console.error('❌ API retornou sucesso=false:', json);
                    throw new Error(json.erro || 'Erro desconhecido da API');
                }

                if (!json.data || json.data.length === 0) {
                    console.warn('⚠️ API retornou vazio - pode não ter dados no banco');
                    json.data = [];
                }

                console.log('✅ Total de programações recebidas:', json.data.length);

                const programacoes = json.data;
                const select = document.getElementById('programacaoSelect');
                const sidebar = document.getElementById('sidebarList');

                // Popular select
                const optsHtml = programacoes.map(p => 
                    `<option value="${p.id}" ${window.urlPrgId === p.id ? 'selected' : ''}>
                        ${p.numero_op} (${p.linha} - ${Number(p.eficiencia).toFixed(1)}%)
                    </option>`
                ).join('');
                
                select.innerHTML = '<option value="">-- Selecione uma programação --</option>' + optsHtml;

                // Popular sidebar
                const sidebarHtml = programacoes.slice(0, 15).map(p =>
                    `<div class="sidebar-item ${window.urlPrgId === p.id ? 'active' : ''}" onclick="selecionarProgramacao(${p.id})">
                        <span class="sidebar-item-op">${p.numero_op}</span>
                        <span class="sidebar-item-meta">${p.linha} · ${Number(p.eficiencia).toFixed(1)}%</span>
                    </div>`
                ).join('');

                sidebar.innerHTML = sidebarHtml || '<div style="color: #a0aec0; font-size: 12px; padding: 8px;">Nenhuma programação com histórico</div>';

                // Se havia prgId na URL, carregar automaticamente
                if (window.urlPrgId && window.urlPrgId > 0) {
                    select.value = window.urlPrgId;
                    setTimeout(() => atualizarGantt(), 300);
                }
                
                console.log('✅ Carregamento concluído com sucesso!');

            } catch (error) {
                console.error('❌ Erro completo:', error);
                document.getElementById('sidebarList').innerHTML = 
                    `<div style="color: #dc2626; font-size: 12px; padding: 8px;">❌ Erro: ${error.message}<br><small>Verifique console (F12) para detalhes</small></div>`;
            }
        }

        /**
         * Exportar para PDF
         */
        function exportarPDF() {
            const select = document.getElementById('programacaoSelect');
            const prgId = parseInt(select.value);
            
            if (!prgId || prgId <= 0) {
                alert('Selecione uma programação primeiro');
                return;
            }

            // Abrir preview em nova janela
            window.open(`/controlepcp_sandbox/assets/js/app.js?action=openHistoryPreview&prgId=${prgId}`, '_blank');
        }

        // Carregar programação atual se tiver ID na URL
        window.addEventListener('load', () => {
            carregarProgramacoes();
        });
    </script>
</body>
</html>
