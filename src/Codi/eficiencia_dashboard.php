<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FASE 4 - Dashboard Eficiência</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
        }

        .header {
            background: white;
            padding: 30px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .header h1 {
            color: #333;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .header h1 .icon {
            font-size: 32px;
        }

        .header p {
            color: #666;
            font-size: 14px;
        }

        .controls {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: grid;
            grid-template-columns: 1fr 1fr 1fr 1fr auto;
            gap: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .controls .form-group {
            display: flex;
            flex-direction: column;
        }

        .controls label {
            font-size: 12px;
            font-weight: 600;
            color: #555;
            margin-bottom: 5px;
            text-transform: uppercase;
        }

        .controls input, .controls select {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 13px;
        }

        .controls button {
            background: #667eea;
            color: white;
            border: none;
            padding: 8px 20px;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 600;
            align-self: flex-end;
            transition: background 0.3s;
        }

        .controls button:hover {
            background: #5568d3;
        }

        .controls button:active {
            transform: scale(0.98);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }

        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .stat-card h3 {
            color: #666;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            margin-bottom: 10px;
        }

        .stat-card .value {
            font-size: 32px;
            font-weight: bold;
            color: #333;
        }

        .stat-card .subtext {
            font-size: 12px;
            color: #999;
            margin-top: 5px;
        }

        .table-container {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        thead {
            background: #f8f9fa;
            border-bottom: 2px solid #e9ecef;
        }

        th {
            padding: 15px;
            text-align: left;
            font-size: 12px;
            font-weight: 600;
            color: #333;
            text-transform: uppercase;
        }

        td {
            padding: 12px 15px;
            border-bottom: 1px solid #e9ecef;
            font-size: 13px;
        }

        tbody tr:hover {
            background: #f8f9fa;
        }

        .status-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-ok {
            background: #d4edda;
            color: #155724;
        }

        .status-aviso {
            background: #fff3cd;
            color: #856404;
        }

        .status-critico {
            background: #f8d7da;
            color: #721c24;
        }

        .kpi-bar {
            width: 100%;
            height: 6px;
            background: #e9ecef;
            border-radius: 3px;
            overflow: hidden;
            margin-top: 5px;
        }

        .kpi-bar-fill {
            height: 100%;
            transition: width 0.3s;
        }

        .kpi-fill-ok {
            background: #28a745;
        }

        .kpi-fill-aviso {
            background: #ffc107;
        }

        .kpi-fill-critico {
            background: #dc3545;
        }

        .loading {
            text-align: center;
            padding: 40px;
            color: white;
        }

        .loading-spinner {
            border: 4px solid rgba(255,255,255,0.3);
            border-top: 4px solid white;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 0 auto 20px;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }

        .empty-state {
            text-align: center;
            padding: 40px;
            color: #999;
        }

        .empty-state-icon {
            font-size: 48px;
            margin-bottom: 10px;
        }

        .chart-container {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .chart-container h3 {
            margin-bottom: 15px;
            color: #333;
            font-size: 14px;
            font-weight: 600;
        }

        .mini-chart {
            display: flex;
            align-items: flex-end;
            gap: 3px;
            height: 100px;
        }

        .mini-chart-bar {
            flex: 1;
            background: #667eea;
            border-radius: 2px 2px 0 0;
            transition: all 0.3s;
            position: relative;
        }

        .mini-chart-bar:hover {
            background: #5568d3;
        }

        .mini-chart-bar-label {
            position: absolute;
            bottom: -20px;
            left: 50%;
            transform: translateX(-50%);
            font-size: 10px;
            color: #666;
            white-space: nowrap;
        }

        .refresh-info {
            color: #999;
            font-size: 12px;
            margin-top: 10px;
            text-align: right;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>
                <span class="icon">📊</span>
                FASE 4 - Dashboard de Eficiência
            </h1>
            <p>Análise cruzada de programações (previsto) vs performance real (realizado)</p>
        </div>

        <div class="controls">
            <div class="form-group">
                <label>Data Início</label>
                <input type="date" id="dataInicio" value="<?php echo date('Y-m-d', strtotime('-7 days')); ?>">
            </div>
            <div class="form-group">
                <label>Data Fim</label>
                <input type="date" id="dataFim" value="<?php echo date('Y-m-d'); ?>">
            </div>
            <div class="form-group">
                <label>Recurso (opcional)</label>
                <input type="number" id="recursoId" placeholder="Deixar vazio para todos">
            </div>
            <div class="form-group">
                <label>Status</label>
                <select id="statusFiltro">
                    <option value="">Todos</option>
                    <option value="ok">✓ OK</option>
                    <option value="aviso">⚠ Aviso</option>
                    <option value="critico">✗ Crítico</option>
                </select>
            </div>
            <button onclick="carregarDados()">Carregar</button>
        </div>

        <div id="content">
            <div class="loading">
                <div class="loading-spinner"></div>
                <p>Carregando dados...</p>
            </div>
        </div>
    </div>

    <script>
        // Dados simulados para demonstração
        const dadosDemonstrativos = {
            "sucesso": true,
            "periodosProcessados": 12,
            "desviosCalculados": 24,
            "estatisticas": {
                "mediaOee": 82.5,
                "mediaEficiencia": 88.3,
                "registrosCriticos": 2,
                "registrosAviso": 4
            },
            "detalhes": [
                {
                    "id": 1,
                    "programacao_id": 101,
                    "recurso_id": 1,
                    "recurso_nome": "Máquina A",
                    "taxa_eficiencia": 92.5,
                    "taxa_performance": 95.0,
                    "taxa_disponibilidade": 98.0,
                    "oee": 86.1,
                    "produtividade": 125.5,
                    "status_geral": "ok",
                    "desvio_quantidade": 12,
                    "desvio_dias": 0,
                    "data_medicao": "2026-04-06 15:30:00"
                },
                {
                    "id": 2,
                    "programacao_id": 102,
                    "recurso_id": 2,
                    "recurso_nome": "Máquina B",
                    "taxa_eficiencia": 78.3,
                    "taxa_performance": 85.0,
                    "taxa_disponibilidade": 90.0,
                    "oee": 68.9,
                    "produtividade": 95.3,
                    "status_geral": "critico",
                    "desvio_quantidade": -45,
                    "desvio_dias": 3,
                    "data_medicao": "2026-04-06 14:15:00"
                },
                {
                    "id": 3,
                    "programacao_id": 103,
                    "recurso_id": 1,
                    "recurso_nome": "Máquina A",
                    "taxa_eficiencia": 84.2,
                    "taxa_performance": 88.0,
                    "taxa_disponibilidade": 92.0,
                    "oee": 77.4,
                    "produtividade": 110.2,
                    "status_geral": "aviso",
                    "desvio_quantidade": -18,
                    "desvio_dias": 1,
                    "data_medicao": "2026-04-06 13:00:00"
                }
            ]
        };

        function carregarDados() {
            const dataInicio = document.getElementById('dataInicio').value;
            const dataFim = document.getElementById('dataFim').value;
            const recursoId = document.getElementById('recursoId').value;
            const status = document.getElementById('statusFiltro').value;

            document.getElementById('content').innerHTML = '<div class="loading"><div class="loading-spinner"></div><p>Carregando dados...</p></div>';

            // Simular chamada à API
            setTimeout(() => {
                let dados = dadosDemonstrativos;

                // Aplicar filtro de status
                if (status) {
                    dados.detalhes = dados.detalhes.filter(d => d.status_geral === status);
                }

                renderizarDados(dados);
            }, 800);
        }

        function renderizarDados(dados) {
            if (!dados.sucesso) {
                document.getElementById('content').innerHTML = '<div class="error">❌ Erro ao carregar dados</div>';
                return;
            }

            let html = '';

            // Cards de estatísticas
            html += '<div class="stats-grid">';
            html += `
                <div class="stat-card">
                    <h3>Períodos Processados</h3>
                    <div class="value">${dados.periodosProcessados}</div>
                    <div class="subtext">programações analisadas</div>
                </div>
                <div class="stat-card">
                    <h3>OEE Médio</h3>
                    <div class="value">${dados.estatisticas.mediaOee.toFixed(1)}%</div>
                    <div class="subtext">eficiência global</div>
                </div>
                <div class="stat-card">
                    <h3>Eficiência Média</h3>
                    <div class="value">${dados.estatisticas.mediaEficiencia.toFixed(1)}%</div>
                    <div class="subtext">quantidade produzida</div>
                </div>
                <div class="stat-card">
                    <h3>Situação</h3>
                    <div class="value">${dados.estatisticas.registrosCriticos}</div>
                    <div class="subtext">críticos | ${dados.estatisticas.registrosAviso} avisos</div>
                </div>
            `;
            html += '</div>';

            // Tabela de detalhes
            html += '<div class="table-container">';
            html += '<table>';
            html += `
                <thead>
                    <tr>
                        <th>Programação</th>
                        <th>Recurso</th>
                        <th>Eficiência</th>
                        <th>Performance</th>
                        <th>Disponibilidade</th>
                        <th>OEE</th>
                        <th>Produtividade</th>
                        <th>Desvio Qtd</th>
                        <th>Desvio Dias</th>
                        <th>Status</th>
                        <th>Data</th>
                    </tr>
                </thead>
                <tbody>
            `;

            if (dados.detalhes.length === 0) {
                html += '<tr><td colspan="11" style="text-align: center; padding: 40px; color: #999;">Nenhum registro encontrado</td></tr>';
            } else {
                dados.detalhes.forEach(item => {
                    const statusClass = `status-${item.status_geral}`;
                    const kpiClass = item.oee >= 80 ? 'kpi-fill-ok' : (item.oee >= 60 ? 'kpi-fill-aviso' : 'kpi-fill-critico');
                    
                    html += `
                        <tr>
                            <td>#${item.programacao_id}</td>
                            <td>${item.recurso_nome}</td>
                            <td>${item.taxa_eficiencia.toFixed(1)}%<div class="kpi-bar"><div class="kpi-bar-fill" style="width: ${item.taxa_eficiencia}%"></div></div></td>
                            <td>${item.taxa_performance.toFixed(1)}%</td>
                            <td>${item.taxa_disponibilidade.toFixed(1)}%</td>
                            <td>${item.oee.toFixed(1)}%<div class="kpi-bar"><div class="kpi-bar-fill ${kpiClass}" style="width: ${item.oee}%"></div></div></td>
                            <td>${item.produtividade.toFixed(1)}</td>
                            <td>${item.desvio_quantidade > 0 ? '+' : ''}${item.desvio_quantidade}</td>
                            <td>${item.desvio_dias > 0 ? '+' : ''}${item.desvio_dias}</td>
                            <td><span class="status-badge ${statusClass}">${item.status_geral}</span></td>
                            <td>${item.data_medicao}</td>
                        </tr>
                    `;
                });
            }

            html += '</tbody></table></div>';

            document.getElementById('content').innerHTML = html;
        }

        // Carregar dados ao abrir página
        window.addEventListener('load', carregarDados);
    </script>
</body>
</html>
