<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cruzamento Completo - Planejado vs Realizado</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            background: #f5f5f5;
            padding: 20px;
        }
        
        .container { max-width: 1600px; margin: 0 auto; }
        
        header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 8px;
            margin-bottom: 30px;
        }
        
        h1 { font-size: 28px; margin-bottom: 5px; }
        .subtitle { font-size: 13px; opacity: 0.9; }
        
        .tabs {
            display: flex;
            gap: 5px;
            margin-bottom: 20px;
            background: white;
            padding: 5px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .tab-btn {
            padding: 12px 20px;
            border: none;
            background: transparent;
            cursor: pointer;
            font-size: 13px;
            font-weight: 600;
            border-radius: 4px;
            transition: all 0.3s;
            color: #666;
        }
        
        .tab-btn:hover { background: #f5f5f5; }
        .tab-btn.active { background: #667eea; color: white; }
        
        .tab-content { display: none; }
        .tab-content.active { display: block; }
        
        .tab-panel {
            background: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .stat-card {
            background: white;
            padding: 15px;
            border-radius: 6px;
            border-left: 4px solid #667eea;
            text-align: center;
        }
        
        .stat-value { font-size: 28px; font-weight: bold; color: #667eea; }
        .stat-label { font-size: 11px; color: #999; text-transform: uppercase; margin-top: 5px; }
        
        .search-box {
            margin-bottom: 20px;
            display: flex;
            gap: 10px;
        }
        
        .search-box input {
            flex: 1;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 13px;
        }
        
        .search-box button {
            padding: 10px 20px;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 600;
        }
        
        .table-wrapper {
            border: 1px solid #ddd;
            border-radius: 4px;
            overflow: auto;
            max-height: 800px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
        }
        
        th {
            background: #667eea;
            color: white;
            padding: 10px;
            text-align: left;
            font-weight: 600;
            position: sticky;
            top: 0;
            z-index: 10;
        }
        
        td { padding: 8px 10px; border-bottom: 1px solid #eee; }
        tr:hover { background: #f9f9f9; }
        
        .badge {
            display: inline-block;
            padding: 3px 6px;
            border-radius: 3px;
            font-size: 11px;
            font-weight: 600;
        }
        
        .badge-planejado { background: #e3f2fd; color: #1976d2; }
        .badge-realizado { background: #e8f5e9; color: #388e3c; }
        
        .loading { text-align: center; padding: 30px; color: #999; }
        .spinner { 
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid #f3f3f3;
            border-top: 3px solid #667eea;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-right: 10px;
            vertical-align: middle;
        }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        
        .empty { text-align: center; padding: 40px; color: #999; }
        .error { background: #ffebee; color: #c62828; padding: 15px; border-radius: 4px; margin-bottom: 20px; }
    </style>
</head>
<body>

<div class="container">
    <header>
        <h1>📊 Cruzamento Completo</h1>
        <div class="subtitle">Planejado vs Realizado - OP × SKU × Quantidade × Máquinas</div>
    </header>
    
    <div class="tabs">
        <button class="tab-btn active" onclick="switchTab('lista')">📋 Lista Completa</button>
        <button class="tab-btn" onclick="switchTab('agrupado')">📊 Por OP</button>
        <button class="tab-btn" onclick="switchTab('comparativo')">⚖️ Comparativo</button>
    </div>
    
    <div id="tab-lista" class="tab-content active">
        <div id="error-lista" style="display:none;" class="error"></div>
        <div class="tab-panel">
            <div id="stats-lista" class="stats"></div>
            <div class="search-box">
                <input type="text" id="search-lista" placeholder="Buscar SKU, OP ou descrição...">
                <button onclick="searchLista()">🔍</button>
            </div>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th style="width:70px">Tipo</th>
                            <th style="width:80px">SKU</th>
                            <th style="width:200px">Produto</th>
                            <th style="width:80px">OP</th>
                            <th style="width:90px">Qtd</th>
                            <th style="width:100px">Data Plan</th>
                            <th style="width:100px">Data Exec</th>
                            <th style="width:120px">Máquina</th>
                        </tr>
                    </thead>
                    <tbody id="tbody-lista">
                        <tr><td colspan="8" class="loading"><span class="spinner"></span>Carregando...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <div id="tab-agrupado" class="tab-content">
        <div class="tab-panel">
            <div id="stats-agrupado" class="stats"></div>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th style="width:80px">OP</th>
                            <th style="width:100px">SKU</th>
                            <th style="width:200px">Produto</th>
                            <th style="width:80px">Qtd Plan</th>
                            <th style="width:80px">Itens</th>
                            <th style="width:100px">Execuções</th>
                        </tr>
                    </thead>
                    <tbody id="tbody-agrupado">
                        <tr><td colspan="6" class="loading"><span class="spinner"></span>Carregando...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <div id="tab-comparativo" class="tab-content">
        <div class="tab-panel">
            <div id="stats-comparativo" class="stats"></div>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th style="width:80px">SKU</th>
                            <th style="width:80px">OP</th>
                            <th style="width:150px">Descrição</th>
                            <th style="width:90px">Plan Qtd</th>
                            <th style="width:90px">Real Qtd</th>
                            <th style="width:100px">Data Plan</th>
                            <th style="width:100px">Data Real</th>
                            <th style="width:80px">Desvio</th>
                        </tr>
                    </thead>
                    <tbody id="tbody-comparativo">
                        <tr><td colspan="8" class="loading"><span class="spinner"></span>Carregando...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
let allData = [];
let filteredData = [];

function switchTab(tab) {
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
    
    event.target.classList.add('active');
    document.getElementById('tab-' + tab).classList.add('active');
}

async function loadData() {
    try {
        console.log('Fazendo fetch...');
        const response = await fetch('api_cruzamento_completo.php?action=lista_completa');
        console.log('Status:', response.status);
        
        if (!response.ok) throw new Error('HTTP ' + response.status);
        
        const result = await response.json();
        console.log('Resultado:', result);
        
        if (result.status === 'ok' && result.items) {
            allData = result.items;
            filteredData = allData;
            renderLista();
            renderAgrupado();
            renderComparativo();
        } else {
            showError('lista', 'Nenhum dado encontrado');
        }
    } catch (e) {
        console.error('Erro:', e);
        showError('lista', 'Erro: ' + e.message);
    }
}

function renderLista() {
    const tbody = document.getElementById('tbody-lista');
    let html = '';
    
    if (filteredData.length === 0) {
        html = '<tr><td colspan="8" class="empty">Nenhum resultado</td></tr>';
    } else {
        filteredData.forEach(row => {
            const badge = row.tipo === 'Planejado' ? 'badge-planejado' : 'badge-realizado';
            html += `
                <tr>
                    <td><span class="badge ${badge}">${row.tipo}</span></td>
                    <td>${row.sku || '-'}</td>
                    <td>${row.descricao || '-'}</td>
                    <td><strong>${row.op_planejada || '-'}</strong></td>
                    <td>${row.quantidade_planejada ? parseFloat(row.quantidade_planejada).toLocaleString('pt-BR') : '-'}</td>
                    <td>${row.data_inicio ? new Date(row.data_inicio).toLocaleDateString('pt-BR') : '-'}</td>
                    <td>${row.data_execucao ? new Date(row.data_execucao).toLocaleDateString('pt-BR') : '-'}</td>
                    <td>${row.recurso_executado || '-'}</td>
                </tr>
            `;
        });
    }
    
    tbody.innerHTML = html;
    
    const planejados = allData.filter(d => d.tipo === 'Planejado').length;
    const realizados = allData.filter(d => d.tipo === 'Realizado').length;
    
    document.getElementById('stats-lista').innerHTML = `
        <div class="stat-card"><div class="stat-value">${planejados}</div><div class="stat-label">Planejados</div></div>
        <div class="stat-card"><div class="stat-value">${realizados}</div><div class="stat-label">Realizados</div></div>
        <div class="stat-card"><div class="stat-value">${allData.length}</div><div class="stat-label">Total</div></div>
    `;
}

function renderAgrupado() {
    const tbody = document.getElementById('tbody-agrupado');
    const grouped = {};
    
    allData.filter(d => d.tipo === 'Planejado').forEach(row => {
        const op = row.op_planejada || 'SEM OP';
        if (!grouped[op]) {
            grouped[op] = {
                skus: new Set(),
                descricoes: new Set(),
                qtd: 0,
                count: 0
            };
        }
        grouped[op].skus.add(row.sku);
        grouped[op].descricoes.add(row.descricao);
        grouped[op].qtd += parseFloat(row.quantidade_planejada || 0);
        grouped[op].count++;
    });
    
    let html = '';
    if (Object.keys(grouped).length === 0) {
        html = '<tr><td colspan="6" class="empty">Nenhum dado</td></tr>';
    } else {
        Object.entries(grouped).forEach(([op, data]) => {
            const realizados = allData.filter(d => d.tipo === 'Realizado').length;
            html += `
                <tr>
                    <td><strong>${op}</strong></td>
                    <td>${Array.from(data.skus).join(', ') || '-'}</td>
                    <td>${Array.from(data.descricoes).values().next().value || '-'}</td>
                    <td>${data.qtd.toLocaleString('pt-BR')}</td>
                    <td>${data.count}</td>
                    <td>${realizados}</td>
                </tr>
            `;
        });
    }
    
    tbody.innerHTML = html;
    document.getElementById('stats-agrupado').innerHTML = `
        <div class="stat-card"><div class="stat-value">${Object.keys(grouped).length}</div><div class="stat-label">OPs</div></div>
    `;
}

function renderComparativo() {
    // Implementar comparativo
    document.getElementById('tbody-comparativo').innerHTML = '<tr><td colspan="8" class="empty">Em desenvolvimento...</td></tr>';
}

function searchLista() {
    const search = document.getElementById('search-lista').value.toLowerCase();
    filteredData = allData.filter(row => {
        const sku = (row.sku || '').toLowerCase();
        const op = (row.op_planejada || '').toLowerCase();
        const desc = (row.descricao || '').toLowerCase();
        return sku.includes(search) || op.includes(search) || desc.includes(search);
    });
    renderLista();
}

function showError(tab, msg) {
    const el = document.getElementById('error-' + tab);
    if (el) {
        el.textContent = '❌ ' + msg;
        el.style.display = 'block';
    }
}

window.addEventListener('load', loadData);
</script>

</body>
</html>
