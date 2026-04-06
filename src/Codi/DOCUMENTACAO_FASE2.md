# 📲 FASE 2 - CodiClient.php Completa

**Status:** ✅ 100% Concluída  
**Data:** 2026-04-06  
**Ambiente:** Sandbox (`controlepcp_sandbox`)  

---

## 📦 O que foi criado

### Arquivos Novos:
```
src/Codi/
├── CodiClient.php          (350+ linhas) - 🌟 CLASSE PRINCIPAL
├── config.php              (50+ linhas) - Configuração
├── exemplo_uso.php         (400+ linhas) - Exemplos práticos
└── DOCUMENTAÇÃO_FASE2.md   (este arquivo) - Guia completo
```

---

## 🎯 CodiClient.php - Classe Principal

### O que faz?

A classe `CodiClient` é responsável por **conectar ao servidor CODI e fazer requisições HTTP**.

**Funcionalidades:**
- ✅ HTTP GET/POST requests
- ✅ Autenticação Basic Auth
- ✅ Retry automático (configurable)
- ✅ Error handling robusto
- ✅ Logging detalhado
- ✅ Timeout configurável
- ✅ SSL/TLS bypass (para ambientes de teste)
- ✅ JSON parsing automático
- ✅ Fluent interface (method chaining)

---

## 📋 API Reference

### Construtor

```php
$client = new CodiClient(
    string $baseUrl,      // 'http://192.168.0.100:8080'
    string $username,     // 'admin'
    string $password,     // 'senha123'
    string $companyCode   // 'matriz' (opcional)
);
```

### Métodos Genéricos

#### GET Request
```php
$data = $client->get(
    string $endpoint,     // '/path/to/endpoint' ou nome conhecido
    array $params = []    // Parâmetros de query
): ?array;
```

**Exemplo:**
```php
$dados = $client->get(
    '/action/ger/webservice/rest/relatorioEvento',
    ['dataInicio' => '2026-04-06', 'limit' => 10]
);
```

#### POST Request
```php
$data = $client->post(
    string $endpoint,
    array $data = [],
    array $params = []
): ?array;
```

**Exemplo:**
```php
$resultado = $client->post(
    '/action/ger/webservice/rest/operacao',
    ['acao' => 'criar', 'nome' => 'OP-001']
);
```

---

### Métodos de Alto Nível (Convenientes)

#### getEventos() - Buscar Eventos de Produção
```php
$eventos = $client->getEventos([
    'dataInicio' => '2026-04-06',
    'dataFim' => '2026-04-07',
    'limit' => 100
]): ?array;
```

**Retorna:**
```json
{
  "count": 50,
  "data": [
    {
      "id": "EVT001",
      "timestamp": "2026-04-06T10:30:00",
      "recursoId": "MAQUINA-01",
      "quantity": 100,
      "skuCodi": "SKU-001",
      "operacaoId": "OP-001"
    }
  ]
}
```

---

#### getEventosConsolidado() - Eventos Consolidados
```php
$consolidado = $client->getEventosConsolidado([
    'dataInicio' => '2026-04-06',
    'limit' => 50
]): ?array;
```

---

#### getPerformance() - Performance em Tempo Real
```php
$perf = $client->getPerformance(): ?array;
```

**Retorna:**
```json
{
  "oee": 85.5,
  "availability": 92.0,
  "performance": 93.5,
  "currentOrder": "OP-001",
  "currentResource": "MAQUINA-01",
  "timestamp": "2026-04-06T15:45:00"
}
```

---

#### getCalendario() - Calendário Fabril
```php
$calendario = $client->getCalendario(): ?array;
```

---

#### getRecursos() - Máquinas/Recursos
```php
$recursos = $client->getRecursos(['limit' => 100]): ?array;
```

**Retorna:**
```json
{
  "data": [
    {
      "id": "REC-001",
      "nome": "Máquina A",
      "tipo": "PRENSA",
      "status": "ATIVO"
    }
  ]
}
```

---

#### getOperacoes() - Operações
```php
$operacoes = $client->getOperacoes(['limit' => 50]): ?array;
```

---

#### getProdutos() - Produtos/SKUs
```php
$produtos = $client->getProdutos(['limit' => 100]): ?array;
```

---

### Métodos de Configuração (Fluent)

```php
$client->setMaxRetries(3)           // Tentar 3 vezes antes de falhar
        ->setRetryDelayMs(1000)     // Aguardar 1 segundo entre tentativas
        ->setTimeout(30)             // Timeout de 30 segundos
        ->setLogging(true);          // Habilitar logging
```

---

### Método de Teste

```php
$conectado = $client->testConnection(): bool;

if ($conectado) {
    echo "✓ Conectado!";
} else {
    echo "✗ Falha de conexão";
}
```

---

### Logging e Diagnóstico

```php
// Obter TODOS os logs
$logs = $client->getLogs(): array;

// Filtrar por nível
$erros = $client->getLogs('ERROR');
$avisos = $client->getLogs('WARNING');
$sucessos = $client->getLogs('SUCCESS');

// Cada log tem:
[
    'timestamp' => '2026-04-06 15:30:45',
    'level' => 'ERROR',
    'message' => 'Connection timeout'
]

// Limpar logs
$client->clearLogs();

// Ver configuração atual
$config = $client->getConfig(): array;
```

---

## 💻 Exemplos de Uso

### Uso Rápido

```php
// Inicializar
$client = new CodiClient(
    'http://192.168.0.100:8080',
    'admin',
    'senha123',
    'matriz'
);

// Buscar eventos de hoje
$eventos = $client->getEventos([
    'dataInicio' => date('Y-m-d'),
    'dataFim' => date('Y-m-d'),
    'limit' => 100
]);

if ($eventos) {
    echo "Encontrados: " . count($eventos['data']) . " eventos";
} else {
    echo "Erro ao buscar eventos";
}
```

---

### Com Tratamento de Erros

```php
try {
    $client = new CodiClient(
        $_ENV['CODI_URL'],
        $_ENV['CODI_USER'],
        $_ENV['CODI_PASS']
    );
    
    // Testar conexão
    if (!$client->testConnection()) {
        throw new Exception('Connection failed');
    }
    
    // Buscar dados
    $eventos = $client->getEventos([
        'dataInicio' => date('Y-m-d', strtotime('-7 days')),
        'limit' => 1000
    ]);
    
    if (!$eventos) {
        throw new Exception('Failed to fetch events');
    }
    
    // Processar dados
    foreach ($eventos['data'] as $evento) {
        processar_evento($evento);
    }
    
} catch (Exception $e) {
    error_log("CODI Error: " . $e->getMessage());
    // Lidar com erro
}
```

---

### Com Retry Manual

```php
$client = new CodiClient(...)
    ->setMaxRetries(5)
    ->setRetryDelayMs(2000);  // Aguardar 2 segundos entre tentativas

$dados = $client->getPerformance();

// Se falhar 5 vezes, retorna null
if ($dados === null) {
    // Logar erro e notificar
    $logs = $client->getLogs('ERROR');
    foreach ($logs as $log) {
        file_put_contents('codi_errors.log', 
            $log['timestamp'] . ' - ' . $log['message'] . "\n", 
            FILE_APPEND
        );
    }
}
```

---

### POST Request (Customizado)

```php
$resultado = $client->post(
    '/action/ger/webservice/rest/operacao',
    [
        'acao' => 'atualizar',
        'id' => 'OP-001',
        'status' => 'CONCLUIDA',
        'quantidade' => 500,
        'timestamp' => date('c')
    ],
    [
        'empresaCodigo' => 'matriz'
    ]
);

if ($resultado) {
    echo "✓ Atualização enviada";
} else {
    echo "✗ Falha ao enviar";
}
```

---

## 🔧 Configuração

### Via Arquivo

```php
// src/Codi/config.php
return [
    'codi' => [
        'baseUrl' => 'http://192.168.0.100:8080',
        'username' => 'admin',
        'password' => 'senha123',
        'companyCode' => 'matriz',
        'maxRetries' => 3,
        'retryDelayMs' => 1000,
        'timeoutSeconds' => 30,
        'enableLogging' => true,
    ]
];
```

---

### Via Variáveis de Ambiente

```bash
# .env
CODI_BASE_URL=http://192.168.0.100:8080
CODI_USERNAME=admin
CODI_PASSWORD=senha123
CODI_COMPANY_CODE=matriz
```

**Uso no código:**
```php
$client = new CodiClient(
    $_ENV['CODI_BASE_URL'],
    $_ENV['CODI_USERNAME'],
    $_ENV['CODI_PASSWORD'],
    $_ENV['CODI_COMPANY_CODE']
);
```

---

## 🐛 Troubleshooting

### "cURL Error: Could not resolve host"
**Problema:** Servidor CODI não está acessível

**Soluções:**
1. Verificar IP/hostname do servidor
2. Testar: `ping 192.168.0.100`
3. Verificar firewall/roteamento

---

### "HTTP 401: Unauthorized"
**Problema:** Credenciais incorretas

**Soluções:**
1. Verificar username/password
2. Testar manualmente em Postman
3. Verificar permissões de usuário no CODI

---

### "HTTP 404: Not Found"
**Problema:** Endpoint não existe

**Soluções:**
1. Verificar URL do endpoint
2. Consultar documentação CODI API
3. Verificar versão do CODI

---

### "Connection timeout"
**Problema:** Servidor demora para responder

**Soluções:**
1. Aumentar timeout: `$client->setTimeout(60)`
2. Verificar performance do servidor CODI
3. Verificar latência de rede

---

### "JSON decode error"
**Problema:** Resposta não é JSON válido

**Soluções:**
1. Verificar se endpoint retorna JSON
2. Verificar headers Accept/Content-Type
3. Ver logs para detalhes

---

## 🧪 Testando

### Executar Exemplos

```bash
cd c:\xampp\htdocs\controlepcp_sandbox
php -S localhost:8000

# Em outro terminal
curl http://localhost:8000/src/Codi/exemplo_uso.php
```

---

### Teste Unitário (Exemplo)

```php
<?php
// test_codi_client.php

require 'src/Codi/CodiClient.php';

$client = new CodiClient(
    'http://192.168.0.100:8080',
    'admin',
    'senha123'
);

// Teste 1: Conexão
assert($client->testConnection() === true);
echo "✓ Teste 1: Conexão OK\n";

// Teste 2: Buscar eventos
$eventos = $client->getEventos(['limit' => 1]);
assert($eventos !== null);
echo "✓ Teste 2: Eventos OK\n";

// Teste 3: Performance
$perf = $client->getPerformance();
assert($perf !== null);
echo "✓ Teste 3: Performance OK\n";

echo "\n✓ Todos os testes passaram!\n";
?>
```

---

## 📊 Performance & Limites

| Aspecto | Valor | Notas |
|---------|-------|-------|
| **Timeout** | 30s | Configurável |
| **Max Retries** | 3 | Configurable |
| **Retry Delay** | 1000ms | Configurável |
| **Response Size** | Ilimitado | Depende de memória |
| **Simultaneous Connections** | 1 | Sequencial por padrão |
| **Cache** | Nenhum | Implementar se necessário |

---

## 🔐 Segurança

### O que é feito:
- ✅ Autenticação Basic Auth
- ✅ Conexão HTTPS (com SSL bypass para dev)
- ✅ Headers de segurança
- ✅ Validação de JSON response

### O que você deve fazer:
- 🔒 **Nunca** commitar senha em código
- 🔒 Usar variáveis de ambiente
- 🔒 Usar configuração separada
- 🔒 Usar secrets manager em produção
- 🔒 Limitar acesso a credenciais

---

## 📝 Próximos Passos (FASE 3)

Depois de validar o CodiClient.php:

### FASE 3: CodiSyncService.php

Será criado: `src/Codi/CodiSyncService.php`

**Responsabilidades:**
- Usar CodiClient para buscar dados
- Processar e transformar dados
- Persistir no BD (cdi_eventos, cdi_performance)
- Registrar logs em cdi_sincronizacao_log
- Agendamento automático

**Estimado:** 2-3 dias

---

## 📚 Referências

### Arquivos FASE 2
- CodiClient.php - Classe principal (350+ linhas)
- config.php - Configuração
- exemplo_uso.php - Exemplos (400+ linhas)
- DOCUMENTACAO_FASE2.md - Esta documentação

### Documentação Anterior
- FASE 1: `db/RESUMO_FASE_1.md`
- Schema: `db/CODI_SCHEMA_DOCUMENTATION.md`
- Setup: `db/SETUP_WIZARD_GUIDE.md`

---

**Status:** ✅ FASE 2 Completa  
**Próximo:** FASE 3 - Serviço de Sincronização  
**Data:** 2026-04-06
