<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FASE 6 - Dashboard Integrado</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 1600px;
            margin: 0 auto;
        }

        .header {
            background: white;
            padding: 30px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .header h1 {
            color: #333;
            margin-bottom: 5px;
        }

        .header p {
            color: #666;
            font-size: 14px;
        }

        .tabs {
            display: flex;
            gap: 10px;
            background: white;
            padding: 10px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .tab-button {
            padding: 10px 20px;
            border: none;
            background: #f0f0f0;
            cursor: pointer;
            border-radius: 4px;
            font-weight: 600;
            transition: all 0.3s;
        }

        .tab-button.active {
            background: #667eea;
            color: white;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .card h3 {
            color: #333;
            font-size: 14px;
            font-weight: 600;
            text-transform: uppercase;
            margin-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
            padding-bottom: 10px;
        }

        .metric {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #f0f0f0;
        }

        .metric:last-child {
            border-bottom: none;
            margin-bottom: 0;
        }

        .metric-label {
            color: #666;
            font-size: 13px;
        }

        .metric-value {
            color: #333;
            font-size: 16px;
            font-weight: 600;
        }

        .metric-value.ok {
            color: #28a745;
        }

        .metric-value.warning {
            color: #ffc107;
        }

        .metric-value.error {
            color: #dc3545;
        }

        .progress-bar {
            width: 100%;
            height: 8px;
            background: #e9ecef;
            border-radius: 4px;
            overflow: hidden;
            margin-top: 5px;
        }

        .progress-fill {
            height: 100%;
            transition: width 0.3s;
        }

        .progress-fill.ok {
            background: #28a745;
        }

        .progress-fill.warning {
            background: #ffc107;
        }

        .progress-fill.error {
            background: #dc3545;
        }

        .table-wrapper {
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        thead {
            background: #f8f9fa;
            border-bottom: 2px solid #e9ecef;
        }

        th, td {
            padding: 12px 15px;
            text-align: left;
            font-size: 13px;
        }

        th {
            font-weight: 600;
            color: #333;
            text-transform: uppercase;
        }

        tbody tr:hover {
            background: #f8f9fa;
        }

        tbody tr:nth-child(even) {
            background: #fafbfc;
        }

        .status-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 3px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-ok {
            background: #d4edda;
            color: #155724;
        }

        .status-warning {
            background: #fff3cd;
            color: #856404;
        }

        .status-error {
            background: #f8d7da;
            color: #721c24;
        }

        .chart-container {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }

        .chart-container h3 {
            margin-bottom: 15px;
            color: #333;
            font-size: 14px;
            font-weight: 600;
        }

        .mini-bars {
            display: flex;
            align-items: flex-end;
            gap: 5px;
            height: 150px;
        }

        .bar {
            flex: 1;
            background: linear-gradient(to top, #667eea, #764ba2);
            border-radius: 3px 3px 0 0;
            position: relative;
        }

        .bar-label {
            position: absolute;
            bottom: -25px;
            left: 0;
            right: 0;
            text-align: center;
            font-size: 11px;
            color: #666;
        }

        .loading {
            text-align: center;
            padding: 40px;
            color: white;
        }

        .error-message {
            background: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }

        .filter-row {
            background: white;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            gap: 10px;
            align-items: flex-end;
            flex-wrap: wrap;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
        }

        .filter-group label {
            font-size: 12px;
            font-weight: 600;
            margin-bottom: 5px;
            color: #555;
        }

        .filter-group input, .filter-group select {
            padding: 8px 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 13px;
        }

        .btn {
            background: #667eea;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 600;
            transition: background 0.3s;
        }

        .btn:hover {
            background: #5568d3;
        }

        .summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }

        .summary-card {
            background: white;
            padding: 15px;
            border-radius: 8px;
            text-align: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .summary-card .value {
            font-size: 28px;
            font-weight: bold;
            color: #667eea;
            margin-bottom: 5px;
        }

        .summary-card .label {
            font-size: 12px;
            color: #666;
            text-transform: uppercase;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📊 FASE 6 - Dashboard Integrado CODI</h1>
            <p>Monitoramento em tempo real de eficiência, programações e performance</p>
        </div>

        <div class="tabs">
            <button class="tab-button active" onclick="mudarTab('resumo')">📈 Resumo</button>
            <button class="tab-button" onclick="mudarTab('eficiencia')">⚙️ Eficiência</button>
            <button class="tab-button" onclick="mudarTab('criticos')">🚨 Críticos</button>
            <button class="tab-button" onclick="mudarTab('recursos')">🏭 Recursos</button>
            <button class="tab-button" onclick="mudarTab('tendencia')">📊 Tendência</button>
        </div>

        <!-- TAB: RESUMO -->
        <div class="tab-content active" id="tab-resumo">
            <div class="filter-row">
                <div class="filter-group">
                    <label>Data Início</label>
                    <input type="date" id="filtro-data-inicio" value="<?php echo date('Y-m-d', strtotime('-30 days')); ?>">
                </div>
                <div class="filter-group">
                    <label>Data Fim</label>
                    <input type="date" id="filtro-data-fim" value="<?php echo date('Y-m-d'); ?>">
                </div>
                <button class="btn" onclick="carregarResumo()">Carregar</button>
            </div>

            <div id="resumo-container">
                <div class="loading" style="color: white;">
                    <p>⏳ Carregando resumo...</p>
                </div>
            </div>
        </div>

        <!-- TAB: EFICIÊNCIA -->
        <div class="tab-content" id="tab-eficiencia">
            <div class="filter-row">
                <div class="filter-group">
                    <label>Período (dias)</label>
                    <select id="filtro-periodo">
                        <option value="7">Últimos 7 dias</option>
                        <option value="14">Últimos 14 dias</option>
                        <option value="30">Últimos 30 dias</option>
                    </select>
                </div>
                <button class="btn" onclick="carregarEficiencia()">Carregar</button>
            </div>

            <div id="eficiencia-container">
                <div class="loading" style="color: white;">
                    <p>⏳ Carregando dados...</p>
                </div>
            </div>
        </div>

        <!-- TAB: CRÍTICOS -->
        <div class="tab-content" id="tab-criticos">
            <div class="filter-row">
                <div class="filter-group">
                    <label>Últimos (dias)</label>
                    <select id="filtro-criticos-dias">
                        <option value="7">7 dias</option>
                        <option value="14">14 dias</option>
                        <option value="30">30 dias</option>
                    </select>
                </div>
                <button class="btn" onclick="carregarCriticos()">Carregar</button>
            </div>

            <div id="criticos-container">
                <div class="loading" style="color: white;">
                    <p>⏳ Carregando críticos...</p>
                </div>
            </div>
        </div>

        <!-- TAB: RECURSOS -->
        <div class="tab-content" id="tab-recursos">
            <div class="filter-row">
                <div class="filter-group">
                    <label>Recurso ID</label>
                    <input type="number" id="filtro-recurso" placeholder="Deixar vazio para todos" min="1">
                </div>
                <button class="btn" onclick="carregarRecursos()">Carregar</button>
            </div>

            <div id="recursos-container">
                <div class="loading" style="color: white;">
                    <p>⏳ Carregando recursos...</p>
                </div>
            </div>
        </div>

        <!-- TAB: TENDÊNCIA -->
        <div class="tab-content" id="tab-tendencia">
            <div class="filter-row">
                <div class="filter-group">
                    <label>Período (dias)</label>
                    <input type="number" id="filtro-tendencia-dias" value="30" min="1" max="365">
                </div>
                <button class="btn" onclick="carregarTendencia()">Carregar</button>
            </div>

            <div id="tendencia-container">
                <div class="loading" style="color: white;">
                    <p>⏳ Carregando tendência...</p>
                </div>
            </div>
        </div>
    </div>

    <script>
        const apiBase = 'http://localhost/controlepcp_sandbox/api/codi_eficiencia.php';

        function mudarTab(tab) {
            // Esconder todas as abas
            document.querySelectorAll('.tab-content').forEach(el => {
                el.classList.remove('active');
            });
            document.querySelectorAll('.tab-button').forEach(el => {
                el.classList.remove('active');
            });

            // Mostrar aba selecionada
            document.getElementById('tab-' + tab).classList.add('active');
            event.target.classList.add('active');

            // Carregar dados da aba
            setTimeout(() => {
                switch(tab) {
                    case 'resumo': carregarResumo(); break;
                    case 'eficiencia': carregarEficiencia(); break;
                    case 'criticos': carregarCriticos(); break;
                    case 'recursos': carregarRecursos(); break;
                    case 'tendencia': carregarTendencia(); break;
                }
            }, 100);
        }

        async function carregarResumo() {
            const dataInicio = document.getElementById('filtro-data-inicio').value;
            const dataFim = document.getElementById('filtro-data-fim').value;
            const container = document.getElementById('resumo-container');

            container.innerHTML = '<div class="loading" style="color: white;"><p>⏳ Carregando...</p></div>';

            try {
                const response = await fetch(`${apiBase}?action=resumo&data_inicio=${dataInicio}&data_fim=${dataFim}`);
                const json = await response.json();

                if (!json.sucesso) throw new Error(json.erro);

                const dados = json.dados;
                let html = '<div class="summary-grid">';

                html += `<div class="summary-card"><div class="value">${dados.resumo.total_registros}</div><div class="label">Registros</div></div>`;
                html += `<div class="summary-card"><div class="value">${(dados.resumo.oee_media || 0).toFixed(1)}%</div><div class="label">OEE Médio</div></div>`;
                html += `<div class="summary-card"><div class="value">${(dados.resumo.eficiencia_media || 0).toFixed(1)}%</div><div class="label">Eficiência</div></div>`;
                html += `<div class="summary-card"><div class="value">${dados.resumo.total_criticos}</div><div class="label">Críticos</div></div>`;

                html += '</div>';

                // Tabela de top recursos
                html += '<div class="table-wrapper"><table>';
                html += '<thead><tr><th>Recurso</th><th>Medições</th><th>OEE Médio</th></tr></thead><tbody>';

                dados.top_recursos.forEach(r => {
                    html += `<tr>
                        <td>#${r.recurso_id}</td>
                        <td>${r.quantidade}</td>
                        <td>${(r.oee_medio || 0).toFixed(1)}%</td>
                    </tr>`;
                });

                html += '</tbody></table></div>';

                container.innerHTML = html;
            } catch (error) {
                container.innerHTML = `<div class="error-message">❌ ${error.message}</div>`;
            }
        }

        async function carregarEficiencia() {
            const periodo = document.getElementById('filtro-periodo').value;
            const container = document.getElementById('eficiencia-container');

            container.innerHTML = '<div class="loading" style="color: white;"><p>⏳ Carregando...</p></div>';

            try {
                const response = await fetch(`${apiBase}?action=listar&periodo=${periodo}&por_pagina=50`);
                const json = await response.json();

                if (!json.sucesso) throw new Error(json.erro);

                const registros = json.dados.registros;
                let html = '<div class="table-wrapper"><table>';
                html += '<thead><tr><th>Prog</th><th>Recurso</th><th>Eficiência</th><th>Performance</th><th>Disp</th><th>OEE</th><th>Produt</th><th>Status</th><th>Data</th></tr></thead><tbody>';

                registros.forEach(r => {
                    const statusClass = r.status_geral === 'ok' ? 'ok' : (r.status_geral === 'aviso' ? 'warning' : 'error');
                    const badgeClass = r.status_geral === 'ok' ? 'status-ok' : (r.status_geral === 'aviso' ? 'status-warning' : 'status-error');
                    
                    html += `<tr>
                        <td>#${r.programacao_id}</td>
                        <td>${r.recurso_id}</td>
                        <td>${(r.taxa_eficiencia || 0).toFixed(1)}%</td>
                        <td>${(r.taxa_performance || 0).toFixed(1)}%</td>
                        <td>${(r.taxa_disponibilidade || 0).toFixed(1)}%</td>
                        <td>${(r.oee || 0).toFixed(1)}%</td>
                        <td>${(r.produtividade || 0).toFixed(1)}</td>
                        <td><span class="status-badge ${badgeClass}">${r.status_geral}</span></td>
                        <td>${r.data_medicao}</td>
                    </tr>`;
                });

                html += '</tbody></table></div>';
                container.innerHTML = html;
            } catch (error) {
                container.innerHTML = `<div class="error-message">❌ ${error.message}</div>`;
            }
        }

        async function carregarCriticos() {
            const dias = document.getElementById('filtro-criticos-dias').value;
            const container = document.getElementById('criticos-container');

            container.innerHTML = '<div class="loading" style="color: white;"><p>⏳ Carregando...</p></div>';

            try {
                const response = await fetch(`${apiBase}?action=criticos&dias=${dias}`);
                const json = await response.json();

                if (!json.sucesso) throw new Error(json.erro);

                const registros = json.dados.registros;
                
                if (registros.length === 0) {
                    container.innerHTML = '<div style="background: white; padding: 20px; border-radius: 8px; text-align: center; color: #28a745; font-weight: 600;">✓ Nenhum registro crítico encontrado!</div>';
                    return;
                }

                let html = '<div class="table-wrapper"><table>';
                html += '<thead><tr><th>Prog</th><th>Recurso</th><th>Eficiência</th><th>OEE</th><th>Desvio Qtd</th><th>Dias Atraso</th><th>Data</th></tr></thead><tbody>';

                registros.forEach(r => {
                    html += `<tr style="background: #fff5f5;">
                        <td>#${r.programacao_id}</td>
                        <td>${r.recurso_id}</td>
                        <td><strong style="color: #dc3545;">${(r.taxa_eficiencia || 0).toFixed(1)}%</strong></td>
                        <td><strong style="color: #dc3545;">${(r.oee || 0).toFixed(1)}%</strong></td>
                        <td>${r.desvio_quantidade > 0 ? '+' : ''}${r.desvio_quantidade}</td>
                        <td>${r.desvio_dias > 0 ? '+' : ''}${r.desvio_dias}</td>
                        <td>${r.data_medicao}</td>
                    </tr>`;
                });

                html += '</tbody></table></div>';
                container.innerHTML = html;
            } catch (error) {
                container.innerHTML = `<div class="error-message">❌ ${error.message}</div>`;
            }
        }

        async function carregarRecursos() {
            const recursoId = document.getElementById('filtro-recurso').value;
            const container = document.getElementById('recursos-container');

            container.innerHTML = '<div class="loading" style="color: white;"><p>⏳ Carregando...</p></div>';

            try {
                const url = recursoId ? `${apiBase}?action=por_recurso&recurso_id=${recursoId}` : `${apiBase}?action=resumo`;
                const response = await fetch(url);
                const json = await response.json();

                if (!json.sucesso) throw new Error(json.erro);

                let html = '<div class="dashboard-grid">';

                if (recursoId) {
                    const dados = json.dados;
                    html += `<div class="card">
                        <h3>📊 Recurso ${dados.recurso_id}</h3>
                        <div class="metric"><span class="metric-label">Total de Medições</span><span class="metric-value">${dados.total_registros}</span></div>
                    </div>`;
                } else {
                    json.dados.top_recursos.forEach(r => {
                        html += `<div class="card">
                            <h3>Recurso #${r.recurso_id}</h3>
                            <div class="metric">
                                <span class="metric-label">Medições</span>
                                <span class="metric-value">${r.quantidade}</span>
                            </div>
                            <div class="metric">
                                <span class="metric-label">OEE Médio</span>
                                <span class="metric-value ok">${(r.oee_medio || 0).toFixed(1)}%</span>
                            </div>
                            <div class="progress-bar">
                                <div class="progress-fill ok" style="width: ${r.oee_medio || 0}%"></div>
                            </div>
                        </div>`;
                    });
                }

                html += '</div>';
                container.innerHTML = html;
            } catch (error) {
                container.innerHTML = `<div class="error-message">❌ ${error.message}</div>`;
            }
        }

        async function carregarTendencia() {
            const dias = document.getElementById('filtro-tendencia-dias').value;
            const container = document.getElementById('tendencia-container');

            container.innerHTML = '<div class="loading" style="color: white;"><p>⏳ Carregando...</p></div>';

            try {
                const response = await fetch(`${apiBase}?action=tendencia&dias=${dias}`);
                const json = await response.json();

                if (!json.sucesso) throw new Error(json.erro);

                const dados = json.dados;
                let html = `<div class="card">
                    <h3>📈 Tendência ${dias} dias</h3>
                    <div class="metric">
                        <span class="metric-label">Total de Pontos</span>
                        <span class="metric-value">${dados.total_pontos}</span>
                    </div>
                    <div class="metric">
                        <span class="metric-label">Variação Absoluta</span>
                        <span class="metric-value">${dados.tendencia.variacao_absoluta > 0 ? '+' : ''}${dados.tendencia.variacao_absoluta}%</span>
                    </div>
                    <div class="metric">
                        <span class="metric-label">Direção</span>
                        <span class="metric-value ${dados.tendencia.direcao === 'positiva' ? 'ok' : 'error'}">${dados.tendencia.direcao.toUpperCase()}</span>
                    </div>
                </div>`;

                container.innerHTML = html;
            } catch (error) {
                container.innerHTML = `<div class="error-message">❌ ${error.message}</div>`;
            }
        }

        // Carregar resumo ao abrir
        window.addEventListener('load', carregarResumo);
    </script>
</body>
</html>
