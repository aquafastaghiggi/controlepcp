<!DOCTYPE html>
<?php require __DIR__ . '/src/bootstrap.php'; ?>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard CODI - Dados Sincronizados</title>
    <style>
        .app-build-badge {
            display: inline-flex;
            align-items: center;
            margin-left: 10px;
            padding: 2px 8px;
            border-radius: 999px;
            border: 1px solid rgba(255, 255, 255, 0.22);
            background: rgba(255, 255, 255, 0.12);
            color: rgba(255, 255, 255, 0.92);
            font-size: 11px;
            font-weight: 700;
            letter-spacing: .08em;
            text-transform: uppercase;
            line-height: 1;
            white-space: nowrap;
        }

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
            max-width: 1400px;
            margin: 0 auto;
        }

        header {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        h1 {
            color: #333;
            margin-bottom: 5px;
        }

        .status-line {
            color: #666;
            font-size: 14px;
            margin-top: 10px;
        }

        .status-line span {
            display: inline-block;
            margin-right: 20px;
            padding: 5px 10px;
            background: #f0f0f0;
            border-radius: 4px;
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .card-stat {
            text-align: center;
            border-left: 4px solid #667eea;
        }

        .card-stat .number {
            font-size: 36px;
            font-weight: bold;
            color: #667eea;
            margin: 10px 0;
        }

        .card-stat .label {
            color: #666;
            font-size: 14px;
        }

        .nav-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .nav-tabs button {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            background: white;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        .nav-tabs button.active {
            background: #667eea;
            color: white;
        }

        .nav-tabs button:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.15);
        }

        .tab-content {
            display: none;
            animation: fadeIn 0.3s;
        }

        .tab-content.active {
            display: block;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
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
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }

        .badge.badge-success {
            background: #d4edda;
            color: #155724;
        }

        .badge.badge-info {
            background: #d1ecf1;
            color: #0c5460;
        }

        .badge.badge-warning {
            background: #fff3cd;
            color: #856404;
        }

        .timeline-item {
            display: flex;
            gap: 15px;
            padding: 15px;
            background: white;
            border-left: 4px solid #667eea;
            margin-bottom: 10px;
            border-radius: 4px;
        }

        .timeline-item .date {
            font-weight: bold;
            color: #667eea;
            min-width: 100px;
        }

        .timeline-item .resource {
            color: #333;
        }

        .timeline-item .time {
            color: #999;
            font-size: 13px;
        }

        .filter-section {
            display: flex;
            gap: 10px;
            margin-bottom: 15px;
            flex-wrap: wrap;
            align-items: center;
        }

        select, input {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }

        button.filter-btn {
            padding: 8px 16px;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 500;
            transition: background 0.3s;
        }

        button.filter-btn:hover {
            background: #764ba2;
        }

        .no-data {
            text-align: center;
            padding: 40px;
            color: #999;
        }

        .grid-2 {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 15px;
        }

        .list-item {
            background: white;
            padding: 15px;
            border-radius: 4px;
            border-left: 4px solid #667eea;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .list-item-name {
            font-weight: 600;
            color: #333;
        }

        .list-item-meta {
            color: #999;
            font-size: 13px;
        }

        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 12px;
            border-radius: 4px;
            margin-bottom: 20px;
            border: 1px solid #f5c6cb;
        }

        .success {
            background: #d4edda;
            color: #155724;
            padding: 12px;
            border-radius: 4px;
            margin-bottom: 20px;
            border: 1px solid #c3e6cb;
        }

        footer {
            text-align: center;
            color: white;
            margin-top: 30px;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1>📊 Dashboard CODI - Dados Sincronizados <?= render_app_build_badge() ?></h1>
            <div class="status-line">
                <span>✅ Banco: controlepcp_sandbox</span>
                <span>🕐 Última atualização: <?php echo date('d/m/Y H:i:s'); ?></span>
                <span>🔗 Servidor: 192.168.8.246:8080</span>
            </div>
        </header>

        <!-- Estatísticas -->
        <div class="stats">
            <div class="card card-stat">
                <div class="label">Recursos (Máquinas)</div>
                <div class="number"><?php echo isset($stats['recursos']) ? $stats['recursos'] : '?'; ?></div>
            </div>
            <div class="card card-stat">
                <div class="label">Calendário Fabril</div>
                <div class="number"><?php echo isset($stats['calendario']) ? $stats['calendario'] : '?'; ?></div>
            </div>
            <div class="card card-stat">
                <div class="label">Performance Data</div>
                <div class="number"><?php echo isset($stats['performance']) ? $stats['performance'] : '?'; ?></div>
            </div>
            <div class="card card-stat">
                <div class="label">Datas Únicas</div>
                <div class="number"><?php echo isset($stats['data_count']) ? $stats['data_count'] : '?'; ?></div>
            </div>
        </div>

        <!-- Abas de Navegação -->
        <div class="nav-tabs">
            <button class="tab-btn active" data-tab="recursos">📋 Recursos</button>
            <button class="tab-btn" data-tab="calendario">📅 Calendário</button>
            <button class="tab-btn" data-tab="performance">⚙️ Performance</button>
            <button class="tab-btn" data-tab="timeline">📍 Timeline</button>
            <button class="tab-btn" data-tab="analise">📊 Análises</button>
        </div>

        <!-- TAB 1: RECURSOS -->
        <div id="recursos" class="tab-content active card">
            <h2>Recursos (Máquinas/Linhas de Produção)</h2>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Código CODI</th>
                        <th>Nome</th>
                        <th>Status</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody id="recursos-tbody">
                    <tr><td colspan="5" class="no-data">Carregando...</td></tr>
                </tbody>
            </table>
        </div>

        <!-- TAB 2: CALENDÁRIO -->
        <div id="calendario" class="tab-content card">
            <h2>Calendário Fabril</h2>
            <div class="filter-section">
                <select id="recurso-filter">
                    <option value="">Todos os recursos</option>
                </select>
                <input type="date" id="data-inicio-filter">
                <input type="date" id="data-fim-filter">
                <button class="filter-btn" onclick="filtrarCalendario()">Filtrar</button>
                <button class="filter-btn" onclick="resetFiltros()">Reset</button>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>Data</th>
                        <th>Recurso</th>
                        <th>Hora Início</th>
                        <th>Hora Fim</th>
                        <th>Turno</th>
                        <th>Duração</th>
                        <th>OP / Item ID</th>
                    </tr>
                </thead>
                <tbody id="calendario-tbody">
                    <tr><td colspan="7" class="no-data">Carregando...</td></tr>
                </tbody>
            </table>
        </div>

        <!-- TAB 3: PERFORMANCE -->
        <div id="performance" class="tab-content card">
            <h2>Performance Data</h2>
            <table>
                <thead>
                    <tr>
                        <th>Código</th>
                        <th>Recurso ID</th>
                        <th>Item ID</th>
                        <th>Item Nome</th>
                        <th>Ordem Produção</th>
                    </tr>
                </thead>
                <tbody id="performance-tbody">
                    <tr><td colspan="5" class="no-data">Carregando...</td></tr>
                </tbody>
            </table>
        </div>

        <!-- TAB 4: TIMELINE -->
        <div id="timeline" class="tab-content card">
            <h2>Timeline de Eventos</h2>
            <div id="timeline-list">
                <p class="no-data">Carregando...</p>
            </div>
        </div>

        <!-- TAB 5: ANÁLISES -->
        <div id="analise" class="tab-content card">
            <h2>Análises e Agregações</h2>
            
            <h3 style="margin-top: 20px; margin-bottom: 10px;">Distribuição por Recurso</h3>
            <table>
                <thead>
                    <tr>
                        <th>Recurso</th>
                        <th>Períodos</th>
                        <th>Dias</th>
                        <th>Primeira Data</th>
                        <th>Última Data</th>
                    </tr>
                </thead>
                <tbody id="analise-recursos-tbody">
                    <tr><td colspan="5" class="no-data">Carregando...</td></tr>
                </tbody>
            </table>

            <h3 style="margin-top: 20px; margin-bottom: 10px;">Items Mais Executados</h3>
            <table>
                <thead>
                    <tr>
                        <th>Item ID</th>
                        <th>Total Execuções</th>
                        <th>Recursos</th>
                        <th>%</th>
                    </tr>
                </thead>
                <tbody id="items-top-tbody">
                    <tr><td colspan="4" class="no-data">Carregando...</td></tr>
                </tbody>
            </table>

            <h3 style="margin-top: 20px; margin-bottom: 10px;">Distribuição Temporal</h3>
            <table>
                <thead>
                    <tr>
                        <th>Período</th>
                        <th>Total Períodos</th>
                        <th>Dias Únicos</th>
                        <th>Primeiro Horário</th>
                        <th>Último Horário</th>
                    </tr>
                </thead>
                <tbody id="temporal-tbody">
                    <tr><td colspan="5" class="no-data">Carregando...</td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <footer>
        <p>Dashboard CODI - Integração com controlepcp_sandbox | Dados sincronizados do servidor 192.168.8.246:8080</p>
    </footer>

    <script>
        // Variáveis globais
        let dadosGlobais = {
            recursos: [],
            calendario: [],
            performance: []
        };

        // Carregar dados ao iniciar
        document.addEventListener('DOMContentLoaded', function() {
            carregarDados();
            setupTabs();
        });

        // Setup das abas
        function setupTabs() {
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    const tabName = this.getAttribute('data-tab');
                    
                    document.querySelectorAll('.tab-content').forEach(tab => {
                        tab.classList.remove('active');
                    });
                    document.getElementById(tabName).classList.add('active');
                    
                    document.querySelectorAll('.tab-btn').forEach(b => {
                        b.classList.remove('active');
                    });
                    this.classList.add('active');
                });
            });
        }

        // Carregar todos os dados via fetch
        function carregarDados() {
            console.log('Carregando dados...');
            
            const baseUrl = 'api_dashboard.php?action=';

            // Carregar estatísticas
            fetch(baseUrl + 'stats')
                .then(r => {
                    if (!r.ok) throw new Error('HTTP ' + r.status);
                    return r.json();
                })
                .then(data => {
                    document.querySelectorAll('.card-stat .number').forEach((el, idx) => {
                        const values = [data.recursos, data.calendario, data.performance, data.data_count];
                        el.textContent = values[idx] || '?';
                    });
                })
                .catch(err => console.error('Erro stats:', err));

            // Carregar recursos
            fetch(baseUrl + 'recursos')
                .then(r => r.json())
                .then(renderRecursos)
                .catch(err => console.error('Erro recursos:', err));

            // Carregar calendário
            fetch(baseUrl + 'calendario&limit=100')
                .then(r => r.json())
                .then(renderCalendario)
                .catch(err => console.error('Erro calendario:', err));

            // Carregar performance
            fetch(baseUrl + 'performance&limit=100')
                .then(r => r.json())
                .then(renderPerformance)
                .catch(err => console.error('Erro performance:', err));

            // Carregar timeline
            fetch(baseUrl + 'timeline')
                .then(r => r.json())
                .then(renderTimeline)
                .catch(err => console.error('Erro timeline:', err));

            // Carregar análises
            fetch(baseUrl + 'analise')
                .then(r => r.json())
                .then(renderAnalise)
                .catch(err => console.error('Erro analise:', err));
        }

        // Renderizar recursos
        function renderRecursos(data) {
            const tbody = document.getElementById('recursos-tbody');
            dadosGlobais.recursos = data;

            // Popular select de filtro
            const select = document.getElementById('recurso-filter');
            data.forEach(r => {
                const opt = document.createElement('option');
                opt.value = r.codigo_codi;
                opt.textContent = r.nome;
                select.appendChild(opt);
            });

            if (data.length === 0) {
                tbody.innerHTML = '<tr><td colspan="5" class="no-data">Nenhum recurso encontrado</td></tr>';
                return;
            }

            tbody.innerHTML = data.map(r => `
                <tr>
                    <td>${r.id}</td>
                    <td><strong>${r.codigo_codi}</strong></td>
                    <td>${r.nome}</td>
                    <td><span class="badge badge-success">Ativo</span></td>
                    <td><button class="filter-btn" onclick="verCalendarioRecurso(${r.codigo_codi})">Ver Calendário</button></td>
                </tr>
            `).join('');
        }

        // Renderizar calendário
        function renderCalendario(data) {
            const tbody = document.getElementById('calendario-tbody');
            
            if (data.length === 0) {
                tbody.innerHTML = '<tr><td colspan="7" class="no-data">Nenhum calendário encontrado</td></tr>';
                return;
            }

            tbody.innerHTML = data.map(c => {
                const dataObj = new Date(c.data + 'T' + c.hora_inicio);
                const duracao = calcularDuracao(c.hora_inicio, c.hora_fim);
                
                // Identificador para cruzar com gráficos
                let identifier = '';
                if (c.item_id) {
                    identifier = `<strong>Item: ${c.item_id}</strong>`;
                } else {
                    identifier = '—';
                }
                
                // Tooltip com informações completas
                const tooltip = `Recurso: ${c.recurso_nome} | Item: ${c.item_id || 'N/A'} | Data: ${c.data}`;
                
                return `
                    <tr title="${tooltip}">
                        <td><strong>${c.data}</strong></td>
                        <td>${c.recurso_nome || 'N/A'}</td>
                        <td>${c.hora_inicio}</td>
                        <td>${c.hora_fim}</td>
                        <td><span class="badge badge-info">Turno ${c.turno_id || '?'}</span></td>
                        <td>${duracao}</td>
                        <td>${identifier}</td>
                    </tr>
                `;
            }).join('');

            dadosGlobais.calendario = data;
        }

        // Renderizar performance
        function renderPerformance(data) {
            const tbody = document.getElementById('performance-tbody');
            
            if (data.length === 0) {
                tbody.innerHTML = '<tr><td colspan="5" class="no-data">Nenhuma performance encontrada</td></tr>';
                return;
            }

            tbody.innerHTML = data.map(p => `
                <tr>
                    <td>${p.codigo}</td>
                    <td>${p.recurso_id || 'N/A'}</td>
                    <td>${p.item_id || 'N/A'}</td>
                    <td>${p.item_nome || 'N/A'}</td>
                    <td>${p.ordem_producao || '—'}</td>
                </tr>
            `).join('');

            dadosGlobais.performance = data;
        }

        // Renderizar timeline
        function renderTimeline(data) {
            const list = document.getElementById('timeline-list');
            
            if (data.length === 0) {
                list.innerHTML = '<p class="no-data">Nenhum evento encontrado</p>';
                return;
            }

            list.innerHTML = data.map(t => `
                <div class="timeline-item">
                    <div class="date">${t.data}</div>
                    <div>
                        <div class="resource">📍 ${t.recurso}</div>
                        <div class="time">${t.periodo}</div>
                    </div>
                </div>
            `).join('');
        }

        // Renderizar análises
        function renderAnalise(data) {
            // Distribuição por recurso
            const distribuicaoTbody = document.getElementById('analise-recursos-tbody');
            if (data.distribuicao && data.distribuicao.length > 0) {
                distribuicaoTbody.innerHTML = data.distribuicao.map(r => `
                    <tr>
                        <td><strong>${r.recurso}</strong></td>
                        <td>${r.total_periodos}</td>
                        <td>${r.dias_diferentes}</td>
                        <td>${r.primeira_data}</td>
                        <td>${r.ultima_data}</td>
                    </tr>
                `).join('');
            }

            // Items top
            const itemsTBody = document.getElementById('items-top-tbody');
            if (data.items_top && data.items_top.length > 0) {
                const totalItems = data.items_top.reduce((sum, item) => sum + item.total_execucoes, 0);
                itemsTBody.innerHTML = data.items_top.map(item => {
                    const percent = ((item.total_execucoes / totalItems) * 100).toFixed(1);
                    return `
                        <tr>
                            <td>${item.item_id}</td>
                            <td><strong>${item.total_execucoes}</strong></td>
                            <td>${item.recursos_diferentes}</td>
                            <td>${percent}%</td>
                        </tr>
                    `;
                }).join('');
            }

            // Temporal
            const temporalTBody = document.getElementById('temporal-tbody');
            if (data.temporal && data.temporal.length > 0) {
                temporalTBody.innerHTML = data.temporal.map(t => `
                    <tr>
                        <td><strong>${t.mes}</strong></td>
                        <td>${t.total}</td>
                        <td>${t.dias}</td>
                        <td>${t.primeiro_horario}</td>
                        <td>${t.ultimo_horario}</td>
                    </tr>
                `).join('');
            }
        }

        // Funções auxiliares
        function calcularDuracao(inicio, fim) {
            const [h1, m1, s1] = inicio.split(':').map(Number);
            const [h2, m2, s2] = fim.split(':').map(Number);
            const min1 = h1 * 60 + m1;
            const min2 = h2 * 60 + m2;
            const duracao = min2 - min1;
            return duracao + ' min';
        }

        function verCalendarioRecurso(recursoId) {
            document.querySelector('button[data-tab="calendario"]').click();
            document.getElementById('recurso-filter').value = recursoId;
            filtrarCalendario();
        }

        function filtrarCalendario() {
            const recursoId = document.getElementById('recurso-filter').value;
            const dataInicio = document.getElementById('data-inicio-filter').value;
            const dataFim = document.getElementById('data-fim-filter').value;

            let url = 'api_dashboard.php?action=calendario&limit=200';
            if (recursoId) url += '&recurso=' + recursoId;
            if (dataInicio) url += '&data_inicio=' + dataInicio;
            if (dataFim) url += '&data_fim=' + dataFim;

            fetch(url)
                .then(r => r.json())
                .then(renderCalendario)
                .catch(err => console.error('Erro filtrar:', err));
        }

        function resetFiltros() {
            document.getElementById('recurso-filter').value = '';
            document.getElementById('data-inicio-filter').value = '';
            document.getElementById('data-fim-filter').value = '';
            fetch('api_dashboard.php?action=calendario&limit=100')
                .then(r => r.json())
                .then(renderCalendario)
                .catch(err => console.error('Erro reset:', err));
        }
    </script>
</body>
</html>
