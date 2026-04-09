<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Comparativo Detalhado - Previsto vs Realizado</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f7fa;
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 1800px;
            margin: 0 auto;
        }

        header {
            background: white;
            padding: 30px;
            border-radius: 12px;
            margin-bottom: 25px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            border-top: 5px solid #667eea;
        }

        h1 {
            color: #1a202c;
            margin-bottom: 8px;
            font-size: 28px;
        }

        .header-sub {
            color: #718096;
            font-size: 14px;
            margin-bottom: 20px;
        }

        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }

        .stat-small {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px;
            border-radius: 8px;
            text-align: center;
        }

        .stat-small-value {
            font-size: 24px;
            font-weight: bold;
        }

        .stat-small-label {
            font-size: 12px;
            opacity: 0.9;
            margin-top: 5px;
        }

        .search-bar {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .search-bar input {
            flex: 1;
            min-width: 200px;
            padding: 10px 15px;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            font-size: 14px;
        }

        .search-bar button {
            padding: 10px 20px;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            transition: background 0.2s;
        }

        .search-bar button:hover {
            background: #5568d3;
        }

        .table-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            overflow: hidden;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        thead {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            position: sticky;
            top: 0;
            z-index: 10;
        }

        th {
            padding: 16px 12px;
            text-align: left;
            font-weight: 600;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        tbody tr {
            border-bottom: 1px solid #e2e8f0;
            transition: background 0.15s ease;
        }

        tbody tr:hover {
            background: #f7fafc;
        }

        tbody tr.executado {
            background: #f0fdf4;
        }

        tbody tr.nao-executado {
            background: #fef2f2;
        }

        td {
            padding: 13px 12px;
            font-size: 13px;
        }

        .sku-cell {
            font-weight: 700;
            color: #667eea;
            font-family: 'Monaco', 'Courier New', monospace;
            font-size: 11px;
            letter-spacing: 0.5px;
        }

        .produto-cell {
            color: #2d3748;
            font-weight: 500;
            max-width: 350px;
        }

        .status {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 700;
            text-align: center;
            min-width: 90px;
        }

        .status-executado {
            background: #dcfce7;
            color: #166534;
        }

        .status-nao-executado {
            background: #fee2e2;
            color: #991b1b;
        }

        .status-calculado {
            background: #cffafe;
            color: #0c4a6e;
        }

        .badge {
            display: inline-block;
            padding: 3px 6px;
            background: #edf2f7;
            border-radius: 3px;
            font-size: 10px;
            color: #2d3748;
            margin-right: 3px;
            font-weight: 500;
        }

        .number {
            text-align: right;
            font-family: 'Courier New', monospace;
            font-size: 12px;
            font-weight: 600;
            color: #1a202c;
        }

        .data-cell {
            font-size: 12px;
            color: #4a5568;
            font-weight: 500;
        }

        .diferenca {
            font-weight: 700;
            text-align: center;
            min-width: 80px;
        }

        .diferenca-positiva {
            color: #059669;
            background: #d1fae5;
            padding: 2px 6px;
            border-radius: 3px;
        }

        .diferenca-negativa {
            color: #dc2626;
            background: #fee2e2;
            padding: 2px 6px;
            border-radius: 3px;
        }

        .diferenca-zero {
            color: #0891b2;
            background: #cffafe;
            padding: 2px 6px;
            border-radius: 3px;
        }

        .diferenca-nao-exec {
            color: #6b7280;
            background: #f3f4f6;
            padding: 2px 6px;
            border-radius: 3px;
            font-weight: 500;
        }

        .loading {
            text-align: center;
            padding: 60px 20px;
            color: #718096;
            font-size: 16px;
        }

        .info-box {
            background: linear-gradient(135deg, #dbeafe 0%, #e0f2fe 100%);
            border-left: 4px solid #0284c7;
            padding: 14px 16px;
            margin-bottom: 20px;
            border-radius: 6px;
            color: #0c4a6e;
            font-size: 13px;
            line-height: 1.5;
        }

        .info-box strong {
            color: #075985;
        }

        .recursos-cell {
            display: flex;
            flex-wrap: wrap;
            gap: 3px;
        }

        .exec-count {
            background: #dbeafe;
            color: #0c4a6e;
            padding: 2px 6px;
            border-radius: 3px;
            font-weight: 600;
            font-size: 11px;
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1> Comparativo Detalhado - Previsto vs Realizado</h1>
            <div class="header-sub">Análise granular do planejamento (sch_linhas) × execução (codi_calendario)</div>
            
            <div class="info-box">
                <strong>ℹ️ Importante:</strong> Dados planejados (Mar 27 - Abr 11) vs dados realizados (Mai 29 - Dez 29). 
                Note o <strong>gap de 48 dias</strong> entre os períodos. Busque por SKU para filtrar produtos específicos.
            </div>

            <div class="stats-row" id="stats-row">
                <div class="stat-small">
                    <div class="stat-small-value">-</div>
                    <div class="stat-small-label">Total Planejado</div>
                </div>
                <div class="stat-small">
                    <div class="stat-small-value">-</div>
                    <div class="stat-small-label">Executado</div>
                </div>
                <div class="stat-small">
                    <div class="stat-small-value">-</div>
                    <div class="stat-small-label">Não Executado</div>
                </div>
                <div class="stat-small">
                    <div class="stat-small-value">-</div>
                    <div class="stat-small-label">Taxa de Execução</div>
                </div>
            </div>
            
            <div class="search-bar">
                <input type="text" id="search-sku" placeholder="🔍 Buscar por SKU, produto ou recurso..." />
                <button onclick="buscar()">Buscar</button>
                <button onclick="limpar()" style="background: #6b7280;">Limpar</button>
            </div>
        </header>

        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th style="width: 100px;">SKU</th>
                        <th style="width: 350px;">Produto</th>
                        <th style="width: 90px;">Qtd Plan.</th>
                        <th style="width: 80px;">Duração</th>
                        <th style="width: 120px;">Data Plan.</th>
                        <th style="width: 90px;">Status Plan.</th>
                        <th style="width: 60px;">Exec.</th>
                        <th style="width: 150px;">Período Real</th>
                        <th style="width: 120px;">Máquinas</th>
                        <th style="width: 90px;">Status Exec.</th>
                        <th style="width: 80px;">Diferença</th>
                    </tr>
                </thead>
                <tbody id="table-body">
                    <tr><td colspan="11" class="loading">⏳ Carregando dados...</td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        let allItems = [];

        function carregarItems(sku = null) {
            let url = 'api_comparacao_detalhada.php?action=items';
            if (sku) {
                url += '&sku=' + encodeURIComponent(sku);
            }

            fetch(url)
                .then(r => r.json())
                .then(data => {
                    if (data.status === 'ok') {
                        allItems = data.items;
                        renderTable(data.items);
                        atualizarStats(data.items);
                    } else {
                        alert('Erro: ' + data.message);
                    }
                })
                .catch(err => {
                    console.error(err);
                    document.getElementById('table-body').innerHTML = 
                        '<tr><td colspan="11" class="loading">❌ Erro ao carregar: ' + err.message + '</td></tr>';
                });
        }

        function atualizarStats(items) {
            if (items.length === 0) return;

            const totalPlanejado = items.length;
            const executado = items.filter(i => i.status_execucao === 'Executado').length;
            const naoExecutado = totalPlanejado - executado;
            const taxaExecucao = ((executado / totalPlanejado) * 100).toFixed(1);

            const stats = document.querySelectorAll('.stat-small-value');
            stats[0].textContent = totalPlanejado;
            stats[1].textContent = executado;
            stats[2].textContent = naoExecutado;
            stats[3].textContent = taxaExecucao + '%';
        }

        function renderTable(items) {
            const tbody = document.getElementById('table-body');
            
            if (items.length === 0) {
                tbody.innerHTML = '<tr><td colspan="11" class="loading">Nenhum item encontrado</td></tr>';
                return;
            }

            let html = '';
            for (const item of items) {
                const cssClass = item.status_execucao === 'Executado' ? 'executado' : 'nao-executado';
                
                const statusExec = item.status_execucao === 'Executado' 
                    ? `<span class="status status-executado">✓ Executado</span>`
                    : `<span class="status status-nao-executado">✗ Não Exec.</span>`;

                const statusPlan = `<span class="status status-calculado">Calculado</span>`;

                // Período realizado
                let periodoRealizado = '—';
                if (item.data_primeira_execucao) {
                    if (item.data_primeira_execucao === item.data_ultima_execucao) {
                        periodoRealizado = item.data_primeira_execucao;
                    } else {
                        periodoRealizado = `${item.data_primeira_execucao}<br/><small>até</small><br/>${item.data_ultima_execucao}`;
                    }
                }

                // Diferença em dias
                let difDias = '';
                if (item.dias_diferenca !== null) {
                    if (item.dias_diferenca > 0) {
                        difDias = `<span class="diferenca diferenca-negativa">+${item.dias_diferenca}d</span>`;
                    } else if (item.dias_diferenca < 0) {
                        difDias = `<span class="diferenca diferenca-positiva">${item.dias_diferenca}d</span>`;
                    } else {
                        difDias = '<span class="diferenca diferenca-zero">Mesmo</span>';
                    }
                } else if (item.status_execucao === 'Não Executado') {
                    difDias = '<span class="diferenca diferenca-nao-exec">—</span>';
                }

                // Máquinas
                let maquinas = '—';
                if (item.recursos_utilizados) {
                    maquinas = '<div class="recursos-cell">' + 
                        item.recursos_utilizados.split(',').map(r => `<span class="badge">L${r}</span>`).join('') + 
                        '</div>';
                }

                html += `
                    <tr class="${cssClass}">
                        <td><span class="sku-cell">${item.sku || '—'}</span></td>
                        <td><span class="produto-cell">${item.produto || '—'}</span></td>
                        <td class="number">${item.quantidade_planejada.toLocaleString('pt-BR', {maximumFractionDigits: 0})}</td>
                        <td class="number">${item.duracao_planejada_minutos}m</td>
                        <td class="data-cell">${item.data_planejada}</td>
                        <td>${statusPlan}</td>
                        <td><span class="exec-count">${item.quantidade_execucoes_realizado}x</span></td>
                        <td class="data-cell">${periodoRealizado}</td>
                        <td>${maquinas}</td>
                        <td>${statusExec}</td>
                        <td>${difDias}</td>
                    </tr>
                `;
            }

            tbody.innerHTML = html;
        }

        function buscar() {
            const sku = document.getElementById('search-sku').value.trim();
            if (sku) {
                // Buscar localmente nos items já carregados
                const filtrados = allItems.filter(item => 
                    item.sku.includes(sku.toUpperCase()) ||
                    item.produto.toUpperCase().includes(sku.toUpperCase()) ||
                    (item.recursos_utilizados && item.recursos_utilizados.includes(sku))
                );
                renderTable(filtrados);
                atualizarStats(filtrados);
            } else {
                renderTable(allItems);
                atualizarStats(allItems);
            }
        }

        function limpar() {
            document.getElementById('search-sku').value = '';
            renderTable(allItems);
            atualizarStats(allItems);
        }

        // Carregar ao iniciar
        document.addEventListener('DOMContentLoaded', function() {
            carregarItems();
        });

        // Buscar ao pressionar Enter
        document.getElementById('search-sku').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                buscar();
            }
        });
    </script>
</body>
</html>
