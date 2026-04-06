<?php
/**
 * Teste Rápido - CodiClient.php
 * 
 * Use este arquivo para validar se a classe CodiClient foi criada corretamente
 * 
 * Como usar:
 * 1. Coloque a URL, usuário e senha do seu CODI
 * 2. Acesse via navegador: http://localhost/controlepcp_sandbox/src/Codi/test.php
 * 3. Verifique os resultados
 */

require_once __DIR__ . '/CodiClient.php';

use Codi\CodiClient;

$results = [];

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FASE 2 - Teste CodiClient</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .container {
            max-width: 1000px;
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
        .header p {
            opacity: 0.9;
        }
        .content {
            padding: 30px;
        }
        .section {
            margin-bottom: 30px;
        }
        .section-title {
            font-size: 18px;
            font-weight: 600;
            color: #333;
            margin-bottom: 15px;
            border-bottom: 2px solid #667eea;
            padding-bottom: 10px;
        }
        .status-box {
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 12px;
            border-left: 4px solid;
        }
        .status-success {
            background: #f0fdf4;
            border-color: #10b981;
            color: #166534;
        }
        .status-error {
            background: #fef2f2;
            border-color: #ef4444;
            color: #991b1b;
        }
        .status-warning {
            background: #fffbeb;
            border-color: #f59e0b;
            color: #92400e;
        }
        .form-group {
            margin-bottom: 16px;
        }
        .form-group label {
            display: block;
            margin-bottom: 6px;
            font-weight: 500;
            color: #333;
        }
        .form-group input {
            width: 100%;
            padding: 10px 12px;
            border: 2px solid #e5e7eb;
            border-radius: 6px;
            font-size: 14px;
        }
        .form-group input:focus {
            outline: none;
            border-color: #667eea;
        }
        .button-group {
            display: flex;
            gap: 12px;
        }
        button {
            flex: 1;
            padding: 12px 20px;
            border: none;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            font-size: 14px;
        }
        .btn-primary {
            background: #667eea;
            color: white;
        }
        .btn-primary:hover {
            background: #5568d3;
            transform: translateY(-2px);
        }
        .btn-secondary {
            background: #e5e7eb;
            color: #333;
        }
        .btn-secondary:hover {
            background: #d1d5db;
        }
        .code-block {
            background: #f3f4f6;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            padding: 12px;
            font-family: 'Courier New', monospace;
            font-size: 12px;
            overflow-x: auto;
            line-height: 1.5;
        }
        .log-item {
            padding: 8px 12px;
            background: #f9fafb;
            border-left: 3px solid #667eea;
            margin-bottom: 6px;
            font-family: monospace;
            font-size: 12px;
        }
        .log-timestamp {
            color: #999;
        }
        .log-level-ERROR { border-color: #ef4444; }
        .log-level-WARNING { border-color: #f59e0b; }
        .log-level-SUCCESS { border-color: #10b981; }
        .footer {
            background: #f9fafb;
            padding: 20px;
            text-align: center;
            color: #666;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🚀 FASE 2 - Teste CodiClient</h1>
            <p>Validar conexão e funcionamento do cliente CODI</p>
        </div>

        <div class="content">
            
            <!-- SEÇÃO: Informações do Sistema -->
            <div class="section">
                <div class="section-title">✓ Informações do Sistema</div>
                
                <?php
                $phpVersion = phpversion();
                $curlLoaded = extension_loaded('curl') ? '✓' : '✗';
                $pdoLoaded = extension_loaded('pdo_mysql') ? '✓' : '✗';
                
                echo "<div class='status-box status-success'>";
                echo "<strong>PHP Version:</strong> $phpVersion<br>";
                echo "<strong>cURL:</strong> $curlLoaded (Necessário)<br>";
                echo "<strong>PDO MySQL:</strong> $pdoLoaded (Necessário)<br>";
                echo "</div>";
                
                if (!extension_loaded('curl')) {
                    echo "<div class='status-box status-error'>";
                    echo "⚠ Extensão cURL não está instalada!";
                    echo "</div>";
                }
                ?>
            </div>

            <!-- SEÇÃO: Configuração -->
            <div class="section">
                <div class="section-title">⚙ Configuração</div>
                
                <form method="POST">
                    <div class="form-group">
                        <label>URL do Servidor CODI</label>
                        <input type="text" name="base_url" placeholder="http://192.168.0.100:8080"
                            value="<?php echo $_POST['base_url'] ?? ''; ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Usuário</label>
                        <input type="text" name="username" placeholder="admin"
                            value="<?php echo $_POST['username'] ?? ''; ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Senha</label>
                        <input type="password" name="password" placeholder="senha123"
                            value="<?php echo $_POST['password'] ?? ''; ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Código da Empresa (opcional)</label>
                        <input type="text" name="company_code" placeholder="matriz"
                            value="<?php echo $_POST['company_code'] ?? 'matriz'; ?>">
                    </div>
                    
                    <div class="button-group">
                        <button type="submit" name="action" value="test" class="btn-primary">
                            🧪 Testar Conexão
                        </button>
                        <button type="submit" name="action" value="full_test" class="btn-primary">
                            🔍 Teste Completo
                        </button>
                    </div>
                </form>
            </div>

            <!-- SEÇÃO: Resultados -->
            <?php
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
                $baseUrl = $_POST['base_url'] ?? '';
                $username = $_POST['username'] ?? '';
                $password = $_POST['password'] ?? '';
                $companyCode = $_POST['company_code'] ?? 'matriz';
                
                if (empty($baseUrl) || empty($username) || empty($password)) {
                    echo "<div class='section'>";
                    echo "<div class='status-box status-error'>";
                    echo "❌ Preencha todos os campos obrigatórios!";
                    echo "</div>";
                    echo "</div>";
                } else {
                    try {
                        $client = new CodiClient($baseUrl, $username, $password, $companyCode);
                        $client->setLogging(true);
                        
                        echo "<div class='section'>";
                        echo "<div class='section-title'>📊 Resultados dos Testes</div>";
                        
                        // Teste 1: Conexão
                        echo "<div class='status-box status-warning'>";
                        echo "⏳ Testando conexão...";
                        echo "</div>";
                        
                        if ($client->testConnection()) {
                            echo "<div class='status-box status-success'>";
                            echo "✅ <strong>Teste 1: Conexão</strong> - Sucesso!";
                            echo "</div>";
                        } else {
                            echo "<div class='status-box status-error'>";
                            echo "❌ <strong>Teste 1: Conexão</strong> - Falha";
                            echo "</div>";
                        }
                        
                        // Testes adicionais se solicitado
                        if ($_POST['action'] === 'full_test') {
                            
                            // Teste 2: Eventos
                            echo "<div class='status-box status-warning'>";
                            echo "⏳ Buscando eventos...";
                            echo "</div>";
                            
                            $eventos = $client->getEventos(['limit' => 1]);
                            if ($eventos !== null) {
                                $count = isset($eventos['count']) ? $eventos['count'] : (isset($eventos['data']) ? count($eventos['data']) : 0);
                                echo "<div class='status-box status-success'>";
                                echo "✅ <strong>Teste 2: Eventos</strong> - $count eventos encontrados";
                                echo "</div>";
                            } else {
                                echo "<div class='status-box status-warning'>";
                                echo "⚠ <strong>Teste 2: Eventos</strong> - Sem dados";
                                echo "</div>";
                            }
                            
                            // Teste 3: Performance
                            echo "<div class='status-box status-warning'>";
                            echo "⏳ Buscando performance...";
                            echo "</div>";
                            
                            $perf = $client->getPerformance();
                            if ($perf !== null) {
                                echo "<div class='status-box status-success'>";
                                echo "✅ <strong>Teste 3: Performance</strong> - Dados obtidos";
                                echo "</div>";
                            } else {
                                echo "<div class='status-box status-warning'>";
                                echo "⚠ <strong>Teste 3: Performance</strong> - Sem dados";
                                echo "</div>";
                            }
                        }
                        
                        // Mostrar configuração
                        echo "<div class='status-box status-success'>";
                        echo "<strong>Configuração Ativa:</strong><br>";
                        $config = $client->getConfig();
                        echo "Base URL: {$config['baseUrl']}<br>";
                        echo "Username: {$config['username']}<br>";
                        echo "Company: {$config['companyCode']}<br>";
                        echo "Max Retries: {$config['maxRetries']}<br>";
                        echo "Timeout: {$config['timeout']}s";
                        echo "</div>";
                        
                        // Mostrar logs
                        $logs = $client->getLogs();
                        if (!empty($logs)) {
                            echo "<div class='status-box status-success'>";
                            echo "<strong>Logs da Execução (" . count($logs) . " entradas):</strong><br>";
                            foreach ($logs as $log) {
                                $class = "log-level-{$log['level']}";
                                echo "<div class='log-item $class'>";
                                echo "<span class='log-timestamp'>[{$log['timestamp']}]</span> ";
                                echo "<strong>{$log['level']}</strong>: {$log['message']}";
                                echo "</div>";
                            }
                            echo "</div>";
                        }
                        
                        echo "</div>";
                        
                    } catch (Exception $e) {
                        echo "<div class='section'>";
                        echo "<div class='status-box status-error'>";
                        echo "💥 <strong>Erro:</strong> " . htmlspecialchars($e->getMessage());
                        echo "</div>";
                        echo "</div>";
                    }
                }
            }
            ?>

            <!-- SEÇÃO: Próximos Passos -->
            <div class="section">
                <div class="section-title">📋 Próximos Passos</div>
                
                <div class="status-box status-success">
                    <strong>Após validar o CodiClient:</strong><br><br>
                    1. ✅ CodiClient.php criado e testado<br>
                    2. ⏳ FASE 3: CodiSyncService.php (próxima)<br>
                    3. ⏳ FASE 4: EficienciaCalculator.php<br>
                    4. ⏳ FASE 5: API Endpoints<br>
                    5. ⏳ FASE 6: Dashboard Integration<br>
                    6. ⏳ FASE 7: Testing & Validation
                </div>
            </div>

            <!-- SEÇÃO: Exemplos de Código -->
            <div class="section">
                <div class="section-title">💻 Exemplos de Uso</div>
                
                <pre class="code-block"><code>&lt;?php
require_once 'src/Codi/CodiClient.php';
use Codi\CodiClient;

// Inicializar
$client = new CodiClient(
    'http://192.168.0.100:8080',
    'admin',
    'senha123',
    'matriz'
);

// Testar
if ($client->testConnection()) {
    // Buscar eventos
    $eventos = $client->getEventos(['limit' => 100]);
    
    // Buscar performance
    $perf = $client->getPerformance();
    
    // Ver logs
    $logs = $client->getLogs();
}
?&gt;</code></pre>
            </div>

        </div>

        <div class="footer">
            <strong>FASE 2 - CodiClient.php</strong> | 
            Criado em 2026-04-06 | 
            Status: ✅ Completo | 
            Próximo: FASE 3
        </div>
    </div>
</body>
</html>
