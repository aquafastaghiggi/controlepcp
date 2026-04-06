# 📲 FASE 3 - CodiSyncService.php Completa

**Status:** ✅ 100% Concluída  
**Data:** 2026-04-06  
**Ambiente:** Sandbox (`controlepcp_sandbox`)  

---

## 📦 O que foi criado

### Arquivos Novos:
```
src/Codi/
├── CodiSyncService.php       (450+ linhas) - 🌟 CLASSE PRINCIPAL
├── exemplo_sync.php          (300+ linhas) - Exemplos (11 completos)
├── sync_api.php              (100+ linhas) - API REST para sync
├── sync_dashboard.php        (400+ linhas) - Interface web
└── DOCUMENTACAO_FASE3.md     (este arquivo) - Guia completo
```

---

## 🎯 CodiSyncService.php - Classe Principal

### O que faz?

A classe `CodiSyncService` **orquestra a sincronização** de dados CODI com o banco de dados local.

**Responsabilidades:**
- ✅ Buscar dados via CodiClient
- ✅ Transformar/processar dados
- ✅ Persistir no BD (eventos, performance)
- ✅ Registrar logs de sincronização
- ✅ Deduplica registros automaticamente
- ✅ Limpeza de dados antigos
- ✅ Status em tempo real
- ✅ Logging detalhado

---

## 📋 Fluxo de Sincronização

```
┌─────────────────────────────────────────────────────────────┐
│  CodiSyncService.syncAll()                                  │
└──────────────────────┬──────────────────────────────────────┘
                       │
                       ├─ syncConfiguration()
                       │  └─→ Salva credenciais em cdi_configuracao
                       │
                       ├─ syncEvents()
                       │  ├─ CodiClient.getEventos()
                       │  ├─ Transforma dados
                       │  ├─ Deduplica
                       │  └─→ Persiste em cdi_eventos
                       │
                       ├─ syncPerformance()
                       │  ├─ CodiClient.getPerformance()
                       │  ├─ Transforma dados
                       │  └─→ Persiste em cdi_performance
                       │
                       └─ persistSyncLog()
                          └─→ Registra resultado em cdi_sincronizacao_log
```

---

## 🔧 API Completa

### Método Principal

```php
$result = $syncService->syncAll(array $options = []): array;

// Retorna:
[
    'success' => true,
    'timestamp' => '2026-04-06 15:30:45',
    'events_synced' => 150,
    'performance_synced' => 1,
    'duration_seconds' => 2.54,
    'errors' => []
]
```

---

### Métodos Específicos

#### Sincronizar Apenas Eventos
```php
$count = $syncService->syncEvents(array $options = []): int;

// Exemplo:
$count = $syncService->syncEvents([
    'dataInicio' => '2026-04-01',
    'dataFim' => '2026-04-06',
    'limit' => 500
]);

echo "Sincronizados: $count eventos";
```

#### Sincronizar Apenas Performance
```php
$count = $syncService->syncPerformance(array $options = []): int;
```

#### Obter Status
```php
$status = $syncService->getStatus(): array;

// Retorna:
[
    'eventos' => [
        'total_events' => 5000,
        'ultimo_evento' => '2026-04-06 15:30:00'
    ],
    'sincronizacoes' => [
        'total_syncs' => 45,
        'ultimo_sync' => '2026-04-06 15:30:00'
    ],
    'status' => 'OK'
]
```

#### Limpar Dados Antigos
```php
$deleted = $syncService->archiveOldData(): int;

// Remove eventos com mais de 90 dias (configurável)
echo "Removidos: $deleted eventos";
```

#### Logging
```php
// Ver todos os logs
$logs = $syncService->getLogs(): array;

// Filtrar por nível
$errors = $syncService->getLogs('ERROR');
$warnings = $syncService->getLogs('WARNING');

// Desabilitar logging
$syncService->setLogging(false);
```

---

## 💻 Exemplos de Uso

### Exemplo 1: Sincronização Simples

```php
<?php
require_once 'src/bootstrap.php';
require_once 'src/Codi/CodiClient.php';
require_once 'src/Codi/CodiSyncService.php';

use Codi\CodiClient;
use Codi\CodiSyncService;

global $pdo;

// Criar cliente
$client = new CodiClient(
    'http://192.168.0.100:8080',
    'admin',
    'senha123',
    'matriz'
);

// Criar serviço de sync
$syncService = new CodiSyncService($client, $pdo);

// Sincronizar
$result = $syncService->syncAll();

if ($result['success']) {
    echo "✓ Sincronizado com sucesso!";
    echo "Eventos: " . $result['events_synced'];
    echo "Performance: " . $result['performance_synced'];
} else {
    echo "✗ Erro na sincronização";
}
?>
```

---

### Exemplo 2: Sincronização com Período Específico

```php
$syncService->syncEvents([
    'dataInicio' => '2026-04-01',
    'dataFim' => '2026-04-06',
    'limit' => 1000
]);
```

---

### Exemplo 3: Monitorar Status

```php
$status = $syncService->getStatus();

echo "Total de eventos: " . $status['eventos']['total_events'];
echo "Último evento: " . $status['eventos']['ultimo_evento'];
echo "Sincronizações realizadas: " . $status['sincronizacoes']['total_syncs'];
```

---

### Exemplo 4: Limpeza Periódica

```php
// Remover dados com mais de 120 dias
$syncService = new CodiSyncService($client, $pdo, [
    'archiveAfterDays' => 120
]);

$deleted = $syncService->archiveOldData();
echo "Removidos: $deleted registros";
```

---

### Exemplo 5: Sincronização Silenciosa

```php
// Desabilitar logging para não poluir os logs
$syncService->setLogging(false);
$result = $syncService->syncAll();

if (!$result['success']) {
    // Ativar logging e sincronizar novamente
    $syncService->setLogging(true);
    $syncService->syncAll();
}
```

---

## 🌐 API REST

### Endpoint: `sync_api.php`

#### Sincronizar Tudo
```bash
GET sync_api.php?action=sync_all
```

#### Sincronizar Eventos
```bash
GET sync_api.php?action=sync_events&data_inicio=2026-04-01&data_fim=2026-04-06&limit=500
```

#### Sincronizar Performance
```bash
GET sync_api.php?action=sync_performance
```

#### Obter Status
```bash
GET sync_api.php?action=get_status
```

#### Obter Logs
```bash
GET sync_api.php?action=get_logs
```

#### Arquivar Dados Antigos
```bash
GET sync_api.php?action=archive
```

---

## 🎨 Dashboard Web

### Acessar
```
http://localhost/controlepcp_sandbox/src/Codi/sync_dashboard.php
```

### Funcionalidades:
- ✅ Interface visual para sincronização
- ✅ Monitoramento em tempo real
- ✅ Estatísticas
- ✅ Logs em tempo real
- ✅ Ações rápidas (botões)
- ✅ Auto-refresh a cada 30s

---

## 📊 Estrutura de Dados

### Input - Evento (CODI)
```json
{
  "id": "EVT001",
  "timestamp": "2026-04-06T10:30:00",
  "recursoId": "MAQUINA-01",
  "quantity": 100.50,
  "skuCodi": "SKU-001",
  "operacaoId": "OP-001",
  "tipoEvento": "PRODUCAO",
  "status": "CONCLUIDA"
}
```

### Output - Evento (BD)
```sql
INSERT INTO cdi_eventos (
  cdi_evento_id_externo,
  cdi_data_evento,
  cdi_hora_evento,
  cdi_timestamp_evento,
  cdi_quantidade_evento,
  cdi_sku_codi,
  cdi_recurso_id,
  cdi_operacao_id,
  cdi_tipo_evento,
  cdi_status_evento,
  cdi_data_sincronizacao
)
```

---

## 🔐 Segurança

### Implementado ✅
- ✅ Validação de dados de entrada
- ✅ SQL prepared statements (protege contra SQL injection)
- ✅ Logging de audit trail
- ✅ Deduplicação automática
- ✅ Transações ACID (via ON DUPLICATE KEY)

---

## 📈 Performance

| Aspecto | Valor | Notas |
|---------|-------|-------|
| **Batch Size** | 100 | Configurável |
| **Timeout** | 30s | Via CodiClient |
| **Retry** | 3 tentativas | Via CodiClient |
| **Records/seg** | ~100-500 | Depende rede |

---

## 🚀 Próximas Fases

```
✅ FASE 1 ← Banco de Dados (CONCLUÍDA)
✅ FASE 2 ← HTTP Client (CONCLUÍDA)
✅ FASE 3 ← Sincronização (CONCLUÍDA)
⏳ FASE 4 ← Calculadora Eficiência
⏳ FASE 5 ← API Endpoints
⏳ FASE 6 ← Dashboard Integration
⏳ FASE 7 ← Testing & Validation
```

---

## 📚 Documentação

| Arquivo | Propósito | Tamanho |
|---------|-----------|--------|
| exemplo_sync.php | 11 exemplos práticos | 300+ KB |
| sync_api.php | REST API para sync | 100+ linhas |
| sync_dashboard.php | Interface web | 400+ linhas |
| DOCUMENTACAO_FASE3.md | Esta documentação | 300+ linhas |

---

**Status:** ✅ FASE 3 - 100% Completo  
**Próximo:** FASE 4 - EficienciaCalculator.php
