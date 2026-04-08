<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Previsto vs Realizado - CODI</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 1600px;
            margin: 0 auto;
        }

        header {
            background: white;
            padding: 25px;
            border-radius: 10px;
            margin-bottom: 25px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        h1 {
            color: #333;
            margin-bottom: 10px;
            font-size: 32px;
        }

        .header-info {
            color: #666;
            font-size: 14px;
            margin-top: 10px;
        }

        .header-info span {
            display: inline-block;
            margin-right: 25px;
            padding: 5px 10px;
            background: #f0f0f0;
            border-radius: 4px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            border-left: 5px solid #667eea;
        }

        .stat-label {
            color: #999;
            font-size: 13px;
            text-transform: uppercase;
            margin-bottom: 10px;
            font-weight: 600;
        }

        .stat-value {
            font-size: 32px;
            font-weight: bold;
            color: #667eea;
            margin-bottom: 5px;
        }

        .stat-sub {
            color: #bbb;
            font-size: 12px;
        }

        .charts-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 30px;
        }

        .chart-container {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .chart-title {
            font-size: 16px;
            font-weight: 600;
            color: #333;
            margin-bottom: 15px;
        }

        .chart-canvas {
            position: relative;
            height: 300px;
        }

        .table-container {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }

        thead {
            background: #f5f5f5;
            border-bottom: 2px solid #ddd;
        }

        th {
            padding: 12px;
            text-align: left;
            font-weight: 600;
            color: #333;
            font-size: 13px;
            text-transform: uppercase;
        }

        td {
            padding: 12px;
            border-bottom: 1px solid #eee;
            font-size: 14px;
        }

        tbody tr:hover {
            background: #f9f9f9;
        }

        .badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .badge-positive {
            background: #d4edda;
            color: #155724;
        }

        .badge-negative {
            background: #f8d7da;
            color: #721c24;
        }

        .badge-neutral {
            background: #d1ecf1;
            color: #0c5460;
        }

        .loading {
            text-align: center;
            padding: 40px;
            color: #999;
        }

        @media (max-width: 1024px) {
            .charts-grid {
                grid-template-columns: 1fr;
            }
        }

        .timeline {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            margin-top: 30px;
        }

        .timeline-item {
            padding: 15px;
            border-left: 4px solid #667eea;
            margin-bottom: 10px;
            background: #f9f9f9;
            border-radius: 4px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .timeline-date {
            font-weight: bold;
            color: #667eea;
            min-width: 120px;
        }

        .timeline-info {
            flex: 1;
            margin: 0 20px;
        }

        .timeline-count {
            color: #333;
            font-size: 14px;
        }

        .timeline-recursos {
            color: #999;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1>📊 Previsto vs Realizado - CODI</h1>
            <div class="header-info">
                <span>📅 Período: Março 2026 → Dezembro 2026</span>
                <span>🏭 Banco: controlepcp_sandbox</span>
                <span>🕐 Atualizado: <span id="hora"></span></span>
            </div>
        </header>

        <!-- ESTATÍSTICAS GERAIS -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-label">Planejado (Períodos)</div>
                <div class="stat-value" id="stat-previsto">-</div>
                <div class="stat-sub">Itens planejados para produção</div>
            </div>

            <div class="stat-card">
                <div class="stat-label">Realizado (Períodos)</div>
                <div class="stat-value" id="stat-realizado">-</div>
                <div class="stat-sub">Períodos executados no CODI</div>
            </div>

            <div class="stat-card">
                <div class="stat-label">Tempo Planejado</div>
                <div class="stat-value" id="stat-tempo-previsto">-</div>
                <div class="stat-sub">Total em minutos</div>
            </div>

            <div class="stat-card">
                <div class="stat-label">Tempo Realizado</div>
                <div class="stat-value" id="stat-tempo-realizado">-</div>
                <div class="stat-sub">Total em minutos</div>
            </div>

            <div class="stat-card">
                <div class="stat-label">Quantidade Planejada</div>
                <div class="stat-value" id="stat-quantidade">-</div>
                <div class="stat-sub">Unidades a produzir</div>
            </div>
        </div>

        <!-- GRÁFICOS -->
        <div class="charts-grid">
            <div class="chart-container">
                <div class="chart-title">Planejado vs Realizado - Períodos</div>
                <div class="chart-canvas">
                    <canvas id="chart-periodos"></canvas>
                </div>
            </div>

            <div class="chart-container">
                <div class="chart-title">Comparação de Tempo (Minutos)</div>
                <div class="chart-canvas">
                    <canvas id="chart-tempo"></canvas>
                </div>
            </div>
        </div>

        <!-- TABELA POR RECURSO -->
        <div class="table-container">
            <h3 style="margin-bottom: 20px; color: #333;">Comparativo por Recurso</h3>
            <table>
                <thead>
                    <tr>
                        <th>Recurso</th>
                        <th>Previsto (Períodos)</th>
                        <th>Realizado (Períodos)</th>
                        <th>Variação %</th>
                        <th>Tempo Planejado</th>
                        <th>Tempo Realizado</th>
                        <th>Quantidade Planejada</th>
                    </tr>
                </thead>
                <tbody id="table-recursos">
                    <tr><td colspan="7" class="loading">Carregando...</td></tr>
                </tbody>
            </table>
        </div>

        <!-- TIMELINE -->
        <div class="timeline">
            <h3 style="margin-bottom: 20px; color: #333;">Timeline Realizado - Últimos 30 Dias</h3>
            <div id="timeline-list">
                <div class="loading">Carregando...</div>
            </div>
        </div>
    </div>

    <script>
        // Variáveis globais
        let charts = {};

        // Carregar dados ao iniciar
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('hora').textContent = new Date().toLocaleString('pt-BR');
            carregarDados();
        });

        // Carregar todos os dados
        function carregarDados() {
            console.log('Carregando dados...');

            // Stats
            fetch('api_previsto_realizado.php?action=stats')
                .then(r => r.json())
                .then(renderStats)
                .catch(err => console.error('Erro stats:', err));

            // Recursos
            fetch('api_previsto_realizado.php?action=recursos')
                .then(r => r.json())
                .then(renderRecursos)
                .catch(err => console.error('Erro recursos:', err));

            // Timeline
            fetch('api_previsto_realizado.php?action=timeline')
                .then(r => r.json())
                .then(renderTimeline)
                .catch(err => console.error('Erro timeline:', err));
        }

        // Renderizar estatísticas
        function renderStats(data) {
            document.getElementById('stat-previsto').textContent = data.previsto_count.toLocaleString('pt-BR');
            document.getElementById('stat-realizado').textContent = data.realizado_count.toLocaleString('pt-BR');
            document.getElementById('stat-tempo-previsto').textContent = (data.previsto_duracao_minutos).toLocaleString('pt-BR');
            document.getElementById('stat-tempo-realizado').textContent = (data.realizado_duracao_minutos).toLocaleString('pt-BR');
            document.getElementById('stat-quantidade').textContent = data.previsto_quantidade.toLocaleString('pt-BR', {maximumFractionDigits: 0});

            // Gráficos comparativos
            const ctx1 = document.getElementById('chart-periodos').getContext('2d');
            if (charts.periodos) charts.periodos.destroy();
            charts.periodos = new Chart(ctx1, {
                type: 'bar',
                data: {
                    labels: ['Períodos'],
                    datasets: [
                        {
                            label: 'Planejado',
                            data: [data.previsto_count],
                            backgroundColor: '#667eea',
                            borderRadius: 5
                        },
                        {
                            label: 'Realizado',
                            data: [data.realizado_count],
                            backgroundColor: '#764ba2',
                            borderRadius: 5
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: true,
                            position: 'bottom'
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });

            const ctx2 = document.getElementById('chart-tempo').getContext('2d');
            if (charts.tempo) charts.tempo.destroy();
            charts.tempo = new Chart(ctx2, {
                type: 'bar',
                data: {
                    labels: ['Tempo (Minutos)'],
                    datasets: [
                        {
                            label: 'Planejado',
                            data: [data.previsto_duracao_minutos],
                            backgroundColor: '#667eea',
                            borderRadius: 5
                        },
                        {
                            label: 'Realizado',
                            data: [data.realizado_duracao_minutos],
                            backgroundColor: '#764ba2',
                            borderRadius: 5
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: true,
                            position: 'bottom'
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
        }

        // Renderizar por recurso
        function renderRecursos(data) {
            const tbody = document.getElementById('table-recursos');

            if (data.length === 0) {
                tbody.innerHTML = '<tr><td colspan="7" class="loading">Nenhum recurso encontrado</td></tr>';
                return;
            }

            tbody.innerHTML = data.map(r => {
                const badge = r.variacao_percentual > 0 
                    ? `<span class="badge badge-negative">+${r.variacao_percentual}%</span>`
                    : r.variacao_percentual < 0
                    ? `<span class="badge badge-positive">${r.variacao_percentual}%</span>`
                    : `<span class="badge badge-neutral">0%</span>`;

                return `
                    <tr>
                        <td><strong>${r.recurso}</strong></td>
                        <td>${r.previsto_count}</td>
                        <td>${r.realizado_count}</td>
                        <td>${badge}</td>
                        <td>${r.previsto_minutos} min</td>
                        <td>${r.realizado_minutos} min</td>
                        <td>${r.previsto_quantidade.toLocaleString('pt-BR', {maximumFractionDigits: 2})}</td>
                    </tr>
                `;
            }).join('');
        }

        // Renderizar timeline
        function renderTimeline(data) {
            const list = document.getElementById('timeline-list');

            if (data.length === 0) {
                list.innerHTML = '<p class="loading">Nenhum dado encontrado</p>';
                return;
            }

            list.innerHTML = data.map(t => `
                <div class="timeline-item">
                    <div class="timeline-date">${t.data}</div>
                    <div class="timeline-info">
                        <div class="timeline-count">📊 ${t.realizado_count} períodos | ⏱️ ${t.realizado_horas} horas</div>
                        <div class="timeline-recursos">🏭 ${t.recursos_utilizados} máquina(s) ativa(s)</div>
                    </div>
                </div>
            `).join('');
        }
    </script>
</body>
</html>
