<?php
/**
 * FASE 5 - EXEMPLOS DE USO: API REST Eficiência
 */

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FASE 5 - Testes API Eficiência</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Courier New', monospace;
            background: #1e1e2e;
            color: #ccc;
            line-height: 1.6;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .header {
            background: #2d2d44;
            padding: 20px;
            border-radius: 4px;
            margin-bottom: 20px;
            border-left: 4px solid #667eea;
        }

        .header h1 {
            color: #667eea;
            margin-bottom: 5px;
        }

        .header p {
            color: #999;
            font-size: 14px;
        }

        .examples-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }

        .example {
            background: #2d2d44;
            padding: 15px;
            border-radius: 4px;
            border-left: 3px solid #667eea;
        }

        .example h3 {
            color: #667eea;
            margin-bottom: 10px;
            font-size: 14px;
        }

        .example-url {
            background: #1e1e2e;
            padding: 10px;
            border-radius: 3px;
            font-size: 12px;
            word-break: break-all;
            margin-bottom: 10px;
            color: #4ec9b0;
            border: 1px solid #444;
        }

        .example-button {
            background: #667eea;
            color: white;
            border: none;
            padding: 8px 12px;
            border-radius: 3px;
            cursor: pointer;
            font-size: 12px;
            font-family: 'Courier New', monospace;
            transition: background 0.3s;
        }

        .example-button:hover {
            background: #5568d3;
        }

        .response-container {
            background: #2d2d44;
            padding: 20px;
            border-radius: 4px;
            margin-top: 20px;
            border-left: 3px solid #28a745;
            display: none;
        }

        .response-container.active {
            display: block;
        }

        .response-container h4 {
            color: #28a745;
            margin-bottom: 10px;
            font-size: 12px;
        }

        .response-body {
            background: #1e1e2e;
            padding: 10px;
            border-radius: 3px;
            font-size: 11px;
            overflow-x: auto;
            max-height: 400px;
            overflow-y: auto;
            border: 1px solid #444;
            white-space: pre-wrap;
            word-break: break-all;
        }

        .error {
            color: #ff6b6b;
        }

        .success {
            color: #28a745;
        }

        @media (max-width: 768px) {
            .examples-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔌 FASE 5 - API REST Eficiência</h1>
            <p>Interface para testar endpoints de eficiência | Base: http://localhost/controlepcp_sandbox/api/codi_eficiencia.php</p>
        </div>

        <div class="examples-grid">
            <!-- 1. Listar -->
            <div class="example">
                <h3>1️⃣ Listar (últimos 7 dias)</h3>
                <div class="example-url">?action=listar&periodo=7&pagina=1&por_pagina=50</div>
                <button class="example-button" onclick="testar('listar', 'GET /api/codi_eficiencia.php?action=listar&periodo=7')">Testar</button>
            </div>

            <!-- 2. Detalhe -->
            <div class="example">
                <h3>2️⃣ Detalhe (ID específico)</h3>
                <div class="example-url">?action=detalhe&id=1</div>
                <button class="example-button" onclick="testar('detalhe', 'GET /api/codi_eficiencia.php?action=detalhe&id=1')">Testar</button>
            </div>

            <!-- 3. Filtrar por Status -->
            <div class="example">
                <h3>3️⃣ Filtrar por Status (críticos)</h3>
                <div class="example-url">?action=filtrar&status=critico&limite=20</div>
                <button class="example-button" onclick="testar('filtrar', 'GET /api/codi_eficiencia.php?action=filtrar&status=critico')">Testar</button>
            </div>

            <!-- 4. Resumo Agregado -->
            <div class="example">
                <h3>4️⃣ Resumo (agregação de dados)</h3>
                <div class="example-url">?action=resumo&data_inicio=2026-04-01&data_fim=2026-04-06</div>
                <button class="example-button" onclick="testar('resumo', 'GET /api/codi_eficiencia.php?action=resumo')">Testar</button>
            </div>

            <!-- 5. Por Recurso -->
            <div class="example">
                <h3>5️⃣ Por Recurso (máquina específica)</h3>
                <div class="example-url">?action=por_recurso&recurso_id=1&dias=30</div>
                <button class="example-button" onclick="testar('por_recurso', 'GET /api/codi_eficiencia.php?action=por_recurso&recurso_id=1')">Testar</button>
            </div>

            <!-- 6. Tendência -->
            <div class="example">
                <h3>6️⃣ Tendência (análise histórica)</h3>
                <div class="example-url">?action=tendencia&dias=30</div>
                <button class="example-button" onclick="testar('tendencia', 'GET /api/codi_eficiencia.php?action=tendencia&dias=30')">Testar</button>
            </div>

            <!-- 7. Críticos -->
            <div class="example">
                <h3>7️⃣ Críticos (alertas)</h3>
                <div class="example-url">?action=criticos&dias=7&limite=50</div>
                <button class="example-button" onclick="testar('criticos', 'GET /api/codi_eficiencia.php?action=criticos')">Testar</button>
            </div>

            <!-- 8. Exportar -->
            <div class="example">
                <h3>8️⃣ Exportar (JSON/CSV)</h3>
                <div class="example-url">?action=exportar&formato=json&data_inicio=2026-04-01</div>
                <button class="example-button" onclick="testar('exportar', 'GET /api/codi_eficiencia.php?action=exportar&formato=json')">Testar</button>
            </div>
        </div>

        <div class="response-container" id="response">
            <h4>📊 Resposta:</h4>
            <div class="response-body" id="response-body"></div>
        </div>
    </div>

    <script>
        const baseUrl = 'http://localhost/controlepcp_sandbox/api/codi_eficiencia.php';

        function testar(action, descricao) {
            const responseContainer = document.getElementById('response');
            const responseBody = document.getElementById('response-body');

            responseContainer.classList.add('active');
            responseBody.innerHTML = '<span class="success">⏳ Carregando...</span>';

            let url = baseUrl + '?action=' + action;

            // Adicionar parâmetros extras quando necessário
            if (action === 'resumo') {
                url += '&data_inicio=2026-04-01&data_fim=2026-04-06';
            } else if (action === 'filtrar') {
                url += '&status=critico&limite=20';
            } else if (action === 'listar') {
                url += '&periodo=7&pagina=1&por_pagina=50';
            } else if (action === 'por_recurso') {
                url += '&recurso_id=1&dias=30';
            } else if (action === 'tendencia') {
                url += '&dias=30';
            } else if (action === 'criticos') {
                url += '&dias=7&limite=50';
            }

            fetch(url)
                .then(response => response.json())
                .then(data => {
                    responseBody.innerHTML = '<span class="success">' + descricao + '</span>\n\n' +
                        JSON.stringify(data, null, 2);
                })
                .catch(error => {
                    responseBody.innerHTML = '<span class="error">❌ Erro: ' + error.message + '</span>';
                });
        }

        // Auto testar ao carregar (apenas exemplar)
        window.addEventListener('load', () => {
            console.log('FASE 5 API - Pronto para testes');
            console.log('Base URL:', baseUrl);
            console.log('Exemplos de endpoints disponíveis:');
            console.log('- GET ?action=listar');
            console.log('- GET ?action=detalhe&id=1');
            console.log('- GET ?action=filtrar&status=critico');
            console.log('- GET ?action=resumo');
            console.log('- GET ?action=por_recurso&recurso_id=1');
            console.log('- GET ?action=tendencia&dias=30');
            console.log('- GET ?action=criticos');
            console.log('- GET ?action=exportar&formato=json');
        });
    </script>
</body>
</html>
