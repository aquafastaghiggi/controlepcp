<?php
/**
 * Dashboard de Sincronização CODI
 * 
 * Interface web para executar e monitorar sincronizações
 */
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FASE 3 - Sincronização CODI</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 12px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .header h1 {
            font-size: 32px;
            margin-bottom: 10px;
        }
        .content {
            padding: 30px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
        }
        .full-width {
            grid-column: 1 / -1;
        }
        .section {
            background: #f9fafb;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            padding: 20px;
        }
        .section-title {
            font-size: 16px;
            font-weight: 600;
            color: #333;
            margin-bottom: 15px;
            border-bottom: 2px solid #667eea;
            padding-bottom: 10px;
        }
        .form-group {
            margin-bottom: 12px;
        }
        .form-group label {
            display: block;
            font-size: 12px;
            font-weight: 600;
            color: #666;
            margin-bottom: 4px;
        }
        .form-group input {
            width: 100%;
            padding: 8px 10px;
            border: 1px solid #e5e7eb;
            border-radius: 4px;
            font-size: 12px;
        }
        .button-group {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-top: 15px;
        }
        button {
            padding: 10px 15px;
            border: none;
            border-radius: 4px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            font-size: 12px;
        }
        .btn-primary {
            background: #667eea;
            color: white;
        }
        .btn-primary:hover {
            background: #5568d3;
        }
        .btn-secondary {
            background: #e5e7eb;
            color: #333;
        }
        .btn-secondary:hover {
            background: #d1d5db;
        }
        .btn-full {
            grid-column: 1 / -1;
        }
        .result-box {
            background: #f0fdf4;
            border: 2px solid #10b981;
            border-radius: 4px;
            padding: 12px;
            margin-top: 12px;
            font-size: 13px;
            max-height: 200px;
            overflow-y: auto;
        }
        .result-box.error {
            background: #fef2f2;
            border-color: #ef4444;
        }
        .result-box.loading {
            background: #fffbeb;
            border-color: #f59e0b;
        }
        .stat-card {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            padding: 15px;
            text-align: center;
        }
        .stat-value {
            font-size: 24px;
            font-weight: 700;
            color: #667eea;
        }
        .stat-label {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-bottom: 15px;
        }
        .log-item {
            font-size: 11px;
            padding: 8px;
            background: #f3f4f6;
            border-left: 3px solid #667eea;
            margin-bottom: 5px;
            font-family: monospace;
            border-radius: 2px;
        }
        .log-error { border-color: #ef4444; }
        .log-warning { border-color: #f59e0b; }
        .log-success { border-color: #10b981; }
        .loading {
            text-align: center;
            padding: 20px;
        }
        .spinner {
            border: 3px solid #f0f0f0;
            border-top: 3px solid #667eea;
            border-radius: 50%;
            width: 30px;
            height: 30px;
            animation: spin 1s linear infinite;
            margin: 0 auto 10px;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        .footer {
            background: #f9fafb;
            padding: 15px;
            text-align: center;
            color: #666;
            font-size: 12px;
            border-top: 1px solid #e5e7eb;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔄 FASE 3 - Sincronização CODI</h1>
            <p>Executar e monitorar sincronização de dados</p>
        </div>

        <div class="content">
            
            <!-- Configuração -->
            <div class="section">
                <div class="section-title">⚙ Configuração</div>
                
                <div class="form-group">
                    <label>URL CODI</label>
                    <input type="text" id="baseUrl" placeholder="http://192.168.0.100:8080" 
                        value="http://192.168.0.100:8080">
                </div>
                <div class="form-group">
                    <label>Usuário</label>
                    <input type="text" id="username" placeholder="admin" value="admin">
                </div>
                <div class="form-group">
                    <label>Senha</label>
                    <input type="password" id="password" placeholder="senha123">
                </div>
                <div class="form-group">
                    <label>Empresa</label>
                    <input type="text" id="companyCode" placeholder="matriz" value="matriz">
                </div>
            </div>

            <!-- Ações -->
            <div class="section">
                <div class="section-title">🎬 Ações</div>
                
                <div class="button-group">
                    <button class="btn-primary" onclick="syncAll()">🔄 Sync Tudo</button>
                    <button class="btn-primary" onclick="syncEvents()">📦 Events</button>
                    <button class="btn-primary" onclick="syncPerformance()">📊 Performance</button>
                    <button class="btn-secondary" onclick="getStatus()">📈 Status</button>
                </div>
            </div>

            <!-- Resultados -->
            <div class="section full-width">
                <div class="section-title">📋 Resultados</div>
                <div id="result" class="result-box">
                    Clique em uma ação para começar...
                </div>
            </div>

            <!-- Stats -->
            <div class="section full-width">
                <div class="section-title">📊 Estatísticas</div>
                <div class="stats-grid" id="stats">
                    <div class="stat-card">
                        <div class="stat-value" id="totalEvents">--</div>
                        <div class="stat-label">Total Eventos</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value" id="totalSyncs">--</div>
                        <div class="stat-label">Sincronizações</div>
                    </div>
                </div>
                <button class="btn-secondary btn-full" onclick="refreshStats()">🔄 Atualizar</button>
            </div>

            <!-- Logs -->
            <div class="section full-width">
                <div class="section-title">📝 Logs Recentes</div>
                <div id="logs" style="font-size: 11px; max-height: 200px; overflow-y: auto;">
                    <div style="color: #999;">Esperando ações...</div>
                </div>
            </div>

        </div>

        <div class="footer">
            <strong>FASE 3 - CodiSyncService</strong> | Criado em 2026-04-06 | Status: ✅ Completo
        </div>
    </div>

    <script>
        const CONFIG = {
            apiUrl: 'sync_api.php'
        };

        async function makeRequest(action, params = {}) {
            const url = new URL(CONFIG.apiUrl, window.location.origin + window.location.pathname.split('/src')[0]);
            url.pathname += '/src/Codi/sync_api.php';
            
            params.action = action;
            params.base_url = document.getElementById('baseUrl').value;
            params.username = document.getElementById('username').value;
            params.password = document.getElementById('password').value;
            params.company_code = document.getElementById('companyCode').value;
            
            // Construir query string
            const queryString = Object.entries(params)
                .map(([k, v]) => `${k}=${encodeURIComponent(v)}`)
                .join('&');
            
            showLoading();
            
            try {
                const response = await fetch(`sync_api.php?${queryString}`);
                const data = await response.json();
                
                displayResult(data);
                refreshStats();
                refreshLogs();
                
                return data;
            } catch (error) {
                displayError(error.message);
            }
        }

        function showLoading() {
            document.getElementById('result').className = 'result-box loading';
            document.getElementById('result').innerHTML = `
                <div class="loading">
                    <div class="spinner"></div>
                    <p>Processando...</p>
                </div>
            `;
        }

        function displayResult(data) {
            const result = document.getElementById('result');
            
            if (data.success) {
                result.className = 'result-box';
                let html = `<strong>✓ ${data.message}</strong><br>`;
                
                if (data.data) {
                    if (data.data.success !== undefined) {
                        html += `Status: ${data.data.success ? '✓ Sucesso' : '✗ Falha'}<br>`;
                        html += `Eventos: ${data.data.events_synced || 0}<br>`;
                        html += `Performance: ${data.data.performance_synced || 0}<br>`;
                        html += `Tempo: ${data.data.duration_seconds || 0}s<br>`;
                    } else if (data.data.eventos) {
                        html += `Total Eventos: ${data.data.eventos.total_events}<br>`;
                        html += `Último Evento: ${data.data.eventos.ultimo_evento || 'N/A'}<br>`;
                        html += `Total Syncs: ${data.data.sincronizacoes.total_syncs}<br>`;
                        html += `Último Sync: ${data.data.sincronizacoes.ultimo_sync || 'N/A'}<br>`;
                    }
                } else if (data.events_synced !== undefined) {
                    html += `Eventos Sincronizados: ${data.events_synced}<br>`;
                } else if (data.archived !== undefined) {
                    html += `Registros Removidos: ${data.archived}<br>`;
                }
                
                result.innerHTML = html;
            } else {
                displayError(data.error || data.message);
            }
        }

        function displayError(message) {
            const result = document.getElementById('result');
            result.className = 'result-box error';
            result.innerHTML = `<strong>✗ Erro</strong><br>${message}`;
        }

        async function refreshStats() {
            const data = await makeRequest('get_status');
        }

        async function refreshLogs() {
            const data = await makeRequest('get_logs');
            
            if (data.success && data.data) {
                const logsDiv = document.getElementById('logs');
                let html = '';
                
                data.data.forEach(log => {
                    const className = `log-${log.level.toLowerCase()}`;
                    html += `<div class="log-item ${className}">
                        [${log.timestamp}] <strong>${log.level}</strong> ${log.message}
                    </div>`;
                });
                
                logsDiv.innerHTML = html || '<div style="color: #999;">Sem logs</div>';
            }
        }

        function syncAll() {
            makeRequest('sync_all');
        }

        function syncEvents() {
            makeRequest('sync_events');
        }

        function syncPerformance() {
            makeRequest('sync_performance');
        }

        function getStatus() {
            makeRequest('get_status');
        }

        // Inicializar
        window.addEventListener('load', () => {
            refreshStats();
            refreshLogs();
        });

        // Auto-refresh a cada 30 segundos
        setInterval(() => {
            refreshStats();
        }, 30000);
    </script>
</body>
</html>
