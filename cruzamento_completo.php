<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cruzamento Completo - OP vs SKU vs Planejado vs Realizado</title>
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
            max-width: 1900px;
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
            line-height: 1.6;
        }

        .tabs {
            display: flex;
            gap: 10px;
            border-bottom: 2px solid #e2e8f0;
            margin-bottom: 20px;
        }

        .tab-button {
            padding: 12px 20px;
            background: none;
            border: none;
            border-bottom: 3px solid transparent;
            cursor: pointer;
            font-weight: 600;
            color: #718096;
            transition: all 0.2s;
            font-size: 14px;
        }

        .tab-button.active {
            color: #667eea;
            border-bottom-color: #667eea;
        }

        .tab-button:hover {
            color: #667eea;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        .table-wrapper {
            background: white;
            border-radius: 10px;
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
            padding: 14px 12px;
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

        td {
            padding: 12px;
            font-size: 13px;
        }

        .op-cell {
            font-weight: 700;
            color: #667eea;
            font-family: 'Courier New', monospace;
            font-size: 11px;
            letter-spacing: 0.5px;
        }

        .sku-cell {
            font-weight: 600;
            color: #2d3748;
        }

        .badge {
            display: inline-block;
            padding: 4px 8px;
            background: #edf2f7;
            border-radius: 4px;
            font-size: 11px;
            color: #2d3748;
            font-weight: 600;
        }

        .badge-planejado {
            background: #bee3f8;
            color: #0c4a6e;
        }

        .badge-realizado {
            background: #c6f6d5;
            color: #22543d;
        }

        .badge-concluido {
            background: #c6f6d5;
            color: #22543d;
        }

        .badge-iniciado {
            background: #fed7d7;
            color: #742a2a;
        }

        .number {
            text-align: right;
            font-family: 'Courier New', monospace;
            font-weight: 600;
            color: #1a202c;
        }

        .data-cell {
            font-size: 12px;
            color: #4a5568;
            font-weight: 500;
        }

        .loading {
            text-align: center;
            padding: 60px 20px;
            color: #718096;
            font-size: 16px;
        }

        .info-banner {
            background: linear-gradient(135deg, #dbeafe 0%, #e0f2fe 100%);
            border-left: 4px solid #0284c7;
            padding: 14px 16px;
            margin-bottom: 20px;
            border-radius: 6px;
            color: #0c4a6e;
            font-size: 13px;
            line-height: 1.5;
        }

        .info-banner strong {
            color: #075985;
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1>🎯 Cruzamento Completo - OP × SKU × Planejado × Realizado</h1>
            <div class="header-sub">
                Visualize toda a jornada de uma OP: do planejamento (sch_linhas + prg_itens com número de OP) até a execução (codi_calendario).
                <br>Correlacione SKU, quantidade planejada, datas e máquinas envolvidas.
            </div>

            <div class="info-banner">
                <strong>📊 Estrutura:</strong> OP (Ordem de Produção) × SKU × Quantidade Planejada × Data Planejada × Execuções × Datas Realizadas × Máquinas
            </div>

            <div class="tabs">
                <button class="tab-button active" onclick="showTab('lista')">📋 Lista Completa</button>
                <button class="tab-button" onclick="showTab('por_op')">🏭 Agrupado por OP</button>
            </div>
        </header>

        <!-- TAB 1: Lista Completa -->
        <div id="lista" class="tab-content active">
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th style="width: 100px;">OP</th>
                            <th style="width: 100px;">SKU</th>
                            <th style="width: 280px;">Produto</th>
                            <th style="width: 90px;">Qtd Plan.</th>
                            <th style="width: 80px;">Duração</th>
                            <th style="width: 110px;">Data Plan.</th>
                            <th style="width: 70px;">Tipo</th>
                            <th style="width: 110px;">Data Exec.</th>
                            <th style="width: 90px;">Máquina</th>
                            <th style="width: 80px;">Status</th>
                        </tr>
                    </thead>
                    <tbody id="table-lista">
                        <tr><td colspan="10" class="loading">⏳ Carregando dados...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- TAB 2: Agrupado por OP -->
        <div id="por_op" class="tab-content">
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th style="width: 100px;">OP</th>
                            <th style="width: 100px;">SKU</th>
                            <th style="width: 280px;">Produto</th>
                            <th style="width: 80px;">Qtd Plan.</th>
                            <th style="width: 70px;">Exec.</th>
                            <th style="width: 80px;">Máquinas</th>
                            <th style="width: 120px;">Período Plan.</th>
                            <th style="width: 130px;">Período Real.</th>
                        </tr>
                    </thead>
                    <tbody id="table-por_op">
                        <tr><td colspan="8" class="loading">⏳ Carregando dados...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        function showTab(tabName) {
            // Esconder todos os tabs
            document.querySelectorAll('.tab-content').forEach(el => {
                el.classList.remove('active');
            });
            document.querySelectorAll('.tab-button').forEach(el => {
                el.classList.remove('active');
            });

            // Mostrar tab selecionado
            document.getElementById(tabName).classList.add('active');
            event.target.classList.add('active');

            // Carregar dados se não foram carregados
            if (tabName === 'lista' && !window.dataLista) {
                carregarTabLista();
            } else if (tabName === 'por_op' && !window.dataOp) {
                carregarTabPorOp();
            }
        }

        function carregarTabLista() {
            fetch('api_cruzamento_completo.php?action=lista_completa')
                .then(r => r.json())
                .then(data => {
                    if (data.status === 'ok') {
                        window.dataLista = data.items;
                        renderTabLista(data.items);
                    }
                })
                .catch(err => {
                    document.getElementById('table-lista').innerHTML = 
                        `<tr><td colspan="10" class="loading">❌ Erro: ${err.message}</td></tr>`;
                });
        }

        function carregarTabPorOp() {
            fetch('api_cruzamento_completo.php?action=por_op')
                .then(r => r.json())
                .then(data => {
                    if (data.status === 'ok') {
                        window.dataOp = data.items;
                        renderTabPorOp(data.items);
                    }
                })
                .catch(err => {
                    document.getElementById('table-por_op').innerHTML = 
                        `<tr><td colspan="8" class="loading">❌ Erro: ${err.message}</td></tr>`;
                });
        }

        function renderTabLista(items) {
            const tbody = document.getElementById('table-lista');
            
            if (items.length === 0) {
                tbody.innerHTML = '<tr><td colspan="10" class="loading">Nenhum item encontrado</td></tr>';
                return;
            }

            let html = '';
            for (const item of items) {
                const tipoClass = item.tipo === 'Planejado' ? 'badge-planejado' : 'badge-realizado';
                const statusBadge = item.status_execucao ? `<span class="badge badge-${item.status_execucao.toLowerCase()}">${item.status_execucao}</span>` : 
                                  (item.status ? `<span class="badge badge-${item.status.toLowerCase()}">${item.status}</span>` : '—');
                
                html += `<tr>
                    <td><span class="op-cell">${item.op_planejada || '—'}</span></td>
                    <td><span class="sku-cell">${item.sku || '—'}</span></td>
                    <td>${item.descricao || '—'}</td>
                    <td class="number">${item.quantidade_planejada ? item.quantidade_planejada.toLocaleString('pt-BR', {maximumFractionDigits: 0}) : '—'}</td>
                    <td class="number">${item.duracao_minutos ? item.duracao_minutos + 'm' : '—'}</td>
                    <td class="data-cell">${item.data_inicio || '—'}</td>
                    <td><span class="badge ${tipoClass}">${item.tipo}</span></td>
                    <td class="data-cell">${item.data_execucao || '—'}</td>
                    <td>${item.recurso_executado || '—'}</td>
                    <td>${statusBadge}</td>
                </tr>`;
            }

            tbody.innerHTML = html;
        }

        function renderTabPorOp(items) {
            const tbody = document.getElementById('table-por_op');
            
            if (items.length === 0) {
                tbody.innerHTML = '<tr><td colspan="8" class="loading">Nenhum item encontrado</td></tr>';
                return;
            }

            let html = '';
            for (const item of items) {
                const periodoReal = item.primeira_execucao ? 
                    (item.primeira_execucao === item.ultima_execucao ? 
                        item.primeira_execucao : 
                        `${item.primeira_execucao}<br/>até ${item.ultima_execucao}`) : 
                    '—';

                html += `<tr>
                    <td><span class="op-cell">${item.op}</span></td>
                    <td><span class="sku-cell">${item.sku || '—'}</span></td>
                    <td>${item.descricao || '—'}</td>
                    <td class="number">${item.qtd_planejada ? item.qtd_planejada.toLocaleString('pt-BR', {maximumFractionDigits: 0}) : '—'}</td>
                    <td class="number">${item.qtd_execucoes || 0}</td>
                    <td>${item.maquinas ? item.maquinas.split(',').slice(0, 2).join(', ') : '—'}</td>
                    <td class="data-cell">${item.data_planejada || '—'}</td>
                    <td class="data-cell">${periodoReal}</td>
                </tr>`;
            }

            tbody.innerHTML = html;
        }

        // Carregar primeiro tab ao iniciar
        document.addEventListener('DOMContentLoaded', function() {
            carregarTabLista();
        });
    </script>
</body>
</html>
