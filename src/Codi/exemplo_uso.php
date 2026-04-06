<?php
/**
 * Exemplo de Uso - CODI Client
 * 
 * Este arquivo demonstra como usar o CodiClient.php para:
 * - Conectar ao servidor CODI
 * - Fazer requisições GET/POST
 * - Buscar dados de eventos e performance
 * - Lidar com erros e retries
 * 
 * FASE 2 - Integração CODI
 */

// Configurar namespace
namespace Codi;

// Incluir bootstrap
require_once __DIR__ . '/../bootstrap.php';

// Incluir CodiClient
require_once __DIR__ . '/CodiClient.php';

/**
 * Exemplo 1: Inicializar Cliente
 */
echo "=== EXEMPLO 1: Inicializar Cliente ===\n";

try {
    $client = new CodiClient(
        baseUrl: 'http://192.168.0.100:8080',
        username: 'admin',
        password: 'senha123',
        companyCode: 'matriz'
    );
    
    echo "✓ Cliente CODI inicializado\n";
    
    // Configurar opções (opcional)
    $client->setMaxRetries(3)
           ->setRetryDelayMs(1000)
           ->setTimeout(30)
           ->setLogging(true);
    
    echo "✓ Configurações aplicadas\n\n";
    
} catch (\Exception $e) {
    echo "✗ Erro ao inicializar: {$e->getMessage()}\n";
    exit(1);
}

/**
 * Exemplo 2: Testar Conexão
 */
echo "=== EXEMPLO 2: Testar Conexão ===\n";

if ($client->testConnection()) {
    echo "✓ Conectado com sucesso ao CODI!\n\n";
} else {
    echo "✗ Falha ao conectar ao CODI\n";
    echo "Verifique:\n";
    echo "- URL do servidor\n";
    echo "- Credenciais (username/password)\n";
    echo "- Conectividade de rede\n\n";
}

/**
 * Exemplo 3: Buscar Eventos (Dados de Produção)
 */
echo "=== EXEMPLO 3: Buscar Eventos de Produção ===\n";

$eventos = $client->getEventos([
    'dataInicio' => date('Y-m-d', strtotime('-1 day')),
    'dataFim' => date('Y-m-d'),
    'limit' => 10
]);

if ($eventos) {
    echo "✓ {$eventos['count'] ?? count($eventos)} eventos encontrados\n";
    
    if (isset($eventos['data']) && count($eventos['data']) > 0) {
        echo "\nPrimeiro evento:\n";
        echo json_encode($eventos['data'][0], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
    }
} else {
    echo "✗ Erro ao buscar eventos\n\n";
}

/**
 * Exemplo 4: Buscar Performance Atual
 */
echo "=== EXEMPLO 4: Buscar Performance Atual ===\n";

$performance = $client->getPerformance();

if ($performance) {
    echo "✓ Performance obtida\n";
    echo json_encode($performance, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
} else {
    echo "✗ Erro ao buscar performance\n\n";
}

/**
 * Exemplo 5: Buscar Recursos (Máquinas)
 */
echo "=== EXEMPLO 5: Buscar Recursos (Máquinas) ===\n";

$recursos = $client->getRecursos(['limit' => 5]);

if ($recursos) {
    echo "✓ Recursos obtidos\n";
    if (isset($recursos['data'])) {
        echo "Total: " . count($recursos['data']) . "\n";
        foreach ($recursos['data'] as $recurso) {
            echo "- {$recurso['nome'] ?? $recurso['id']}\n";
        }
    }
    echo "\n";
} else {
    echo "✗ Erro ao buscar recursos\n\n";
}

/**
 * Exemplo 6: Buscar Operações
 */
echo "=== EXEMPLO 6: Buscar Operações ===\n";

$operacoes = $client->getOperacoes(['limit' => 5]);

if ($operacoes) {
    echo "✓ Operações obtidas\n";
    if (isset($operacoes['data'])) {
        echo "Total: " . count($operacoes['data']) . "\n";
    }
    echo "\n";
} else {
    echo "✗ Erro ao buscar operações\n\n";
}

/**
 * Exemplo 7: Buscar Produtos
 */
echo "=== EXEMPLO 7: Buscar Produtos ===\n";

$produtos = $client->getProdutos(['limit' => 5]);

if ($produtos) {
    echo "✓ Produtos obtidos\n";
    if (isset($produtos['data'])) {
        echo "Total: " . count($produtos['data']) . "\n";
    }
    echo "\n";
} else {
    echo "✗ Erro ao buscar produtos\n\n";
}

/**
 * Exemplo 8: Fazer Requisição GET Customizada
 */
echo "=== EXEMPLO 8: Requisição GET Customizada ===\n";

$dados = $client->get(
    '/action/ger/webservice/rest/relatorioEvento',
    [
        'empresaCodigo' => 'matriz',
        'dataInicio' => date('Y-m-d'),
        'limit' => 1
    ]
);

if ($dados) {
    echo "✓ Requisição bem-sucedida\n";
    echo "Respos primeiros 200 chars:\n";
    echo substr(json_encode($dados), 0, 200) . "...\n\n";
} else {
    echo "✗ Erro na requisição\n\n";
}

/**
 * Exemplo 9: Fazer Requisição POST (se o CODI suportar)
 */
echo "=== EXEMPLO 9: Requisição POST (Exemplo) ===\n";

$postData = $client->post(
    '/action/ger/webservice/rest/operacao',
    [
        'empresaCodigo' => 'matriz',
        'acao' => 'criar',
    ]
);

if ($postData) {
    echo "✓ POST bem-sucedido\n";
} else {
    echo "⚠ POST retornou vazio (normal, depende do endpoint)\n";
}
echo "\n";

/**
 * Exemplo 10: Ver Logs e Configuração
 */
echo "=== EXEMPLO 10: Logs e Configuração ===\n";

// Configurações atuais
$config = $client->getConfig();
echo "Configuração Atual:\n";
echo "- Base URL: {$config['baseUrl']}\n";
echo "- Username: {$config['username']}\n";
echo "- Company Code: {$config['companyCode']}\n";
echo "- Max Retries: {$config['maxRetries']}\n";
echo "- Timeout: {$config['timeout']}s\n";
echo "- Logging Enabled: " . ($config['loggingEnabled'] ? 'Sim' : 'Não') . "\n\n";

// Logs da última execução
$logs = $client->getLogs();
echo "Total de Logs: " . count($logs) . "\n";

// Filtrar por nível
$errors = $client->getLogs('ERROR');
if (!empty($errors)) {
    echo "\n⚠ Erros detectados:\n";
    foreach ($errors as $error) {
        echo "- [{$error['timestamp']}] {$error['message']}\n";
    }
}

$warnings = $client->getLogs('WARNING');
if (!empty($warnings)) {
    echo "\n⚠ Avisos:\n";
    foreach ($warnings as $warning) {
        echo "- [{$warning['timestamp']}] {$warning['message']}\n";
    }
}

$successes = $client->getLogs('SUCCESS');
if (!empty($successes)) {
    echo "\n✓ Sucessos:\n";
    foreach ($successes as $success) {
        echo "- [{$success['timestamp']}] {$success['message']}\n";
    }
}

echo "\n";

/**
 * Exemplo 11: Tratamento de Erros
 */
echo "=== EXEMPLO 11: Tratamento de Erros ===\n";

try {
    // Tentar com socket inválido
    $clientInvalid = new CodiClient(
        baseUrl: 'http://localhost:9999',
        username: 'invalid',
        password: 'invalid'
    );
    
    $result = $clientInvalid->getEventos();
    
    if ($result === null) {
        echo "✗ Requisição falhou após todos os retries\n";
        echo "Verifique os logs:\n";
        $logs = $clientInvalid->getLogs('ERROR');
        foreach ($logs as $log) {
            echo "- {$log['message']}\n";
        }
    }
    
} catch (\Exception $e) {
    echo "✗ Exceção: {$e->getMessage()}\n";
}

echo "\n=== FIM DOS EXEMPLOS ===\n";
?>
