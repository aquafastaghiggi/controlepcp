<?php
/**
 * Visualizador de Migrações CODI
 * Mostra diagrama das tabelas e relações
 */
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🗂️ Visualizador de Migrações CODI</title>
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
            max-width: 1400px;
            margin: 0 auto;
        }
        
        .header {
            text-align: center;
            color: white;
            margin-bottom: 40px;
        }
        
        .header h1 {
            font-size: 2.5em;
            margin-bottom: 10px;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }
        
        .header p {
            font-size: 1.1em;
            opacity: 0.9;
        }
        
        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }
        
        .card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(0,0,0,0.3);
        }
        
        .card-title {
            font-size: 1.3em;
            font-weight: bold;
            color: #667eea;
            margin-bottom: 10px;
            padding-bottom: 10px;
            border-bottom: 2px solid #667eea;
        }
        
        .card-subtitle {
            font-size: 0.9em;
            color: #666;
            margin-bottom: 15px;
            font-style: italic;
        }
        
        .card-content {
            font-size: 0.95em;
            line-height: 1.6;
        }
        
        .table-item {
            background: #f5f7fa;
            padding: 8px 12px;
            margin: 5px 0;
            border-radius: 5px;
            border-left: 3px solid #667eea;
        }
        
        .table-name {
            font-weight: bold;
            color: #333;
        }
        
        .table-info {
            font-size: 0.85em;
            color: #666;
            margin-top: 3px;
        }
        
        .status-badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.85em;
            font-weight: bold;
            margin-top: 10px;
        }
        
        .status-success {
            background: #d4edda;
            color: #155724;
        }
        
        .status-warning {
            background: #fff3cd;
            color: #856404;
        }
        
        .legend {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            margin-bottom: 40px;
        }
        
        .legend-title {
            font-size: 1.3em;
            font-weight: bold;
            color: #667eea;
            margin-bottom: 15px;
        }
        
        .legend-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }
        
        .legend-item {
            padding: 10px;
            background: #f5f7fa;
            border-radius: 5px;
        }
        
        .legend-icon {
            font-size: 1.5em;
            margin-right: 8px;
        }
        
        .action-buttons {
            display: flex;
            gap: 10px;
            justify-content: center;
            margin-top: 30px;
        }
        
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 1em;
            font-weight: bold;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-primary {
            background: #667eea;
            color: white;
        }
        
        .btn-primary:hover {
            background: #5568d3;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        
        .btn-secondary {
            background: #f5f7fa;
            color: #667eea;
            border: 2px solid #667eea;
        }
        
        .btn-secondary:hover {
            background: #667eea;
            color: white;
        }
        
        .diagram {
            background: white;
            border-radius: 10px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            overflow-x: auto;
            margin-bottom: 40px;
        }
        
        .diagram-title {
            font-size: 1.3em;
            font-weight: bold;
            color: #667eea;
            margin-bottom: 20px;
        }
        
        .flow {
            display: flex;
            align-items: center;
            gap: 20px;
            flex-wrap: wrap;
        }
        
        .flow-item {
            background: #f5f7fa;
            padding: 15px 20px;
            border-radius: 5px;
            border: 2px solid #667eea;
            font-weight: bold;
            min-width: 150px;
            text-align: center;
        }
        
        .flow-arrow {
            font-size: 1.5em;
            color: #667eea;
        }
        
        .section-title {
            color: white;
            font-size: 1.8em;
            margin-top: 40px;
            margin-bottom: 20px;
            font-weight: bold;
        }
        
        code {
            background: #f5f7fa;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: 'Courier New', monospace;
            font-size: 0.9em;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- HEADER -->
        <div class="header">
            <h1>🗂️ Visualizador de Migrações CODI</h1>
            <p>Status das tabelas e estrutura do banco de dados MySQL</p>
        </div>
        
        <!-- FLUXO DE INTEGRAÇÃO -->
        <div class="diagram">
            <div class="diagram-title">📊 Fluxo de Integração</div>
            <div class="flow">
                <div class="flow-item">CODI Hardware</div>
                <div class="flow-arrow">→</div>
                <div class="flow-item">API REST CODI</div>
                <div class="flow-arrow">→</div>
                <div class="flow-item">CodiClient.php</div>
                <div class="flow-arrow">→</div>
                <div class="flow-item">Banco Local</div>
                <div class="flow-arrow">→</div>
                <div class="flow-item">Dashboard</div>
            </div>
        </div>
        
        <!-- LEGENDA DE CORES -->
        <div class="legend">
            <div class="legend-title">📋 Legenda de Tabelas</div>
            <div class="legend-grid">
                <div class="legend-item">
                    <span class="legend-icon">⚙️</span>
                    <strong>Configuração</strong> - Credenciais e setup
                </div>
                <div class="legend-item">
                    <span class="legend-icon">📥</span>
                    <strong>Sincronização</strong> - Dados do CODI
                </div>
                <div class="legend-item">
                    <span class="legend-icon">📊</span>
                    <strong>Eficiência</strong> - Cruzamento previsto/realizado
                </div>
                <div class="legend-item">
                    <span class="legend-icon">📈</span>
                    <strong>Cache</strong> - Dados pré-calculados
                </div>
            </div>
        </div>
        
        <!-- TABELAS -->
        <h2 class="section-title">📦 Tabelas Criadas</h2>
        
        <div class="grid">
            <!-- CONFIGURAÇÃO -->
            <div class="card">
                <div class="card-title">⚙️ Configuração</div>
                <div class="card-content">
                    <div class="table-item">
                        <div class="table-name">cdi_configuracao</div>
                        <div class="table-info">URL, usuário, senha, company code</div>
                    </div>
                    <span class="status-badge status-success">✓ 1 tabela</span>
                </div>
            </div>
            
            <!-- SINCRONIZAÇÃO -->
            <div class="card">
                <div class="card-title">📥 Sincronização</div>
                <div class="card-content">
                    <div class="table-item">
                        <div class="table-name">cdi_eventos</div>
                        <div class="table-info">Log de eventos de produção</div>
                    </div>
                    <div class="table-item">
                        <div class="table-name">cdi_performance</div>
                        <div class="table-info">Performance em tempo real</div>
                    </div>
                    <div class="table-item">
                        <div class="table-name">cdi_sincronizacao_log</div>
                        <div class="table-info">Auditoria de sindronizações</div>
                    </div>
                    <span class="status-badge status-success">✓ 3 tabelas</span>
                </div>
            </div>
            
            <!-- MAPEAMENTO -->
            <div class="card">
                <div class="card-title">🔗 Mapeamento</div>
                <div class="card-content">
                    <div class="table-item">
                        <div class="table-name">cdi_sku_mapping</div>
                        <div class="table-info">SKU CODI ↔ ControlePCP</div>
                    </div>
                    <span class="status-badge status-success">✓ 1 tabela</span>
                </div>
            </div>
            
            <!-- EFICIÊNCIA -->
            <div class="card">
                <div class="card-title">📊 Eficiência (Core)</div>
                <div class="card-content">
                    <div class="table-item">
                        <div class="table-name">cdi_eficiencia_medicao</div>
                        <div class="table-info">Previsto vs Realizado ⭐</div>
                    </div>
                    <div class="table-item">
                        <div class="table-name">cdi_eficiencia_historico</div>
                        <div class="table-info">Auditoria de status</div>
                    </div>
                    <span class="status-badge status-success">✓ 2 tabelas</span>
                </div>
            </div>
            
            <!-- CACHE & VIEWS -->
            <div class="card">
                <div class="card-title">⚡ Cache & Views</div>
                <div class="card-content">
                    <div class="table-item">
                        <div class="table-name">cdi_resumo_diario</div>
                        <div class="table-info">Pré-cálculos para dashboard</div>
                    </div>
                    <div class="table-item">
                        <div class="table-name">cdi_view_eficiencia_atual</div>
                        <div class="table-info">View dos últimos 30 dias</div>
                    </div>
                    <span class="status-badge status-success">✓ 1 tabela + 1 view</span>
                </div>
            </div>
            
            <!-- SUMÁRIO -->
            <div class="card">
                <div class="card-title">📈 Sumário</div>
                <div class="card-content">
                    <div class="table-item">
                        <div class="table-name">Total de Tabelas</div>
                        <div class="table-info">8 tabelas criadas</div>
                    </div>
                    <div class="table-item">
                        <div class="table-name">Views</div>
                        <div class="table-info">1 view auxiliar</div>
                    </div>
                    <span class="status-badge status-success">✓ Estrutura completa</span>
                </div>
            </div>
        </div>
        
        <!-- ESTATÍSTICAS -->
        <h2 class="section-title">📊 Estatísticas da Estrutura</h2>
        
        <div class="grid">
            <div class="card">
                <div class="card-title">Colunas por Tabela</div>
                <div class="card-content">
                    <div class="table-item">
                        <strong>cdi_configuracao:</strong> 9 colunas
                    </div>
                    <div class="table-item">
                        <strong>cdi_eventos:</strong> 14 colunas
                    </div>
                    <div class="table-item">
                        <strong>cdi_performance:</strong> 10 colunas
                    </div>
                    <div class="table-item">
                        <strong>cdi_eficiencia_medicao:</strong> 22 colunas (!!!)
                    </div>
                    <div class="table-item" style="border-left-color: #ffc107;">
                        <strong>Total:</strong> ~95 colunas disponíveis
                    </div>
                </div>
            </div>
            
            <div class="card">
                <div class="card-title">Índices Criados</div>
                <div class="card-content">
                    <div class="table-item">
                        ✓ Índices PRIMARY KEY em todas
                    </div>
                    <div class="table-item">
                        ✓ Índices em datas/timestamps
                    </div>
                    <div class="table-item">
                        ✓ Índices em foreign keys
                    </div>
                    <div class="table-item">
                        ✓ Índices FULLTEXT em nomes
                    </div>
                    <div class="table-item">
                        ✓ Índices compostos otimizados
                    </div>
                </div>
            </div>
            
            <div class="card">
                <div class="card-title">Tipos de Dados</div>
                <div class="card-content">
                    <div class="table-item">
                        INT - IDs e contadores
                    </div>
                    <div class="table-item">
                        VARCHAR - Strings
                    </div>
                    <div class="table-item">
                        DATETIME - Timestamps
                    </div>
                    <div class="table-item">
                        DECIMAL - Valores numéricos
                    </div>
                    <div class="table-item">
                        ENUM - Status/Classificação
                    </div>
                </div>
            </div>
        </div>
        
        <!-- PRÓXIMAS ETAPAS -->
        <h2 class="section-title">🚀 Próximas Etapas</h2>
        
        <div class="grid">
            <div class="card">
                <div class="card-title">1️⃣ Executar Migrações</div>
                <div class="card-content">
                    <p>Clique no botão abaixo para executar todas as migrations SQL:</p>
                    <div class="action-buttons">
                        <a href="run_codi_migrations.php" class="btn btn-primary" target="_blank">
                            🔨 Executar Migrations
                        </a>
                    </div>
                </div>
            </div>
            
            <div class="card">
                <div class="card-title">2️⃣ Verificar Tabelas</div>
                <div class="card-content">
                    <p>Use MySQL para verificar se as tabelas foram criadas:</p>
                    <code>SHOW TABLES LIKE 'cdi_%';</code>
                    <p style="margin-top: 15px; color: #666;">Deve listar 8 tabelas</p>
                </div>
            </div>
            
            <div class="card">
                <div class="card-title">3️⃣ Próxima Fase</div>
                <div class="card-content">
                    <p>Criar classe <code>CodiClient.php</code> para conectar ao CODI:</p>
                    <code>src/Codi/CodiClient.php</code>
                    <p style="margin-top: 15px; color: #666;">Implementar HTTP REST calls</p>
                </div>
            </div>
        </div>
        
        <!-- DOCUMENTAÇÃO -->
        <div style="background: white; border-radius: 10px; padding: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.2); margin-top: 40px; margin-bottom: 40px;">
            <h3 style="color: #667eea; margin-bottom: 15px;">📚 Documentação Completa</h3>
            <div style="line-height: 1.8;">
                <p>📖 <a href="CODI_SCHEMA_DOCUMENTATION.md" style="color: #667eea; text-decoration: none; font-weight: bold;">Documentação das Tabelas →</a></p>
                <p>📄 <a href="README_MIGRATIONS.md" style="color: #667eea; text-decoration: none; font-weight: bold;">README de Migrações →</a></p>
                <p>📋 <a href="codi_migrations.sql" style="color: #667eea; text-decoration: none; font-weight: bold;">SQL Bruto →</a></p>
            </div>
        </div>
        
        <!-- FOOTER -->
        <div style="text-align: center; color: white; padding: 30px; margin-top: 60px; border-top: 2px solid rgba(255,255,255,0.2);">
            <p style="font-size: 1.1em;">✅ Estrutura de banco pronta para integração CODI</p>
            <p style="opacity: 0.8; margin-top: 10px;">ControlePCP Sandbox • Integração CODI • 2026</p>
        </div>
    </div>
</body>
</html>
