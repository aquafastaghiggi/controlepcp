# 📊 DOCUMENTAÇÃO - TABELAS CODI MYSQL

## Sumário

1. [Tabelas de Configuração](#1-configuração)
2. [Tabelas de Sincronização](#2-sincronização)
3. [Tabelas de Eficiência](#3-eficiência)
4. [Views e Helpers](#4-views)
5. [Exemplo de Query](#5-exemplos)

---

## 1. CONFIGURAÇÃO

### `cdi_configuracao`
Armazena credenciais e URLs de conexão com o CODI.

| Campo | Tipo | Descrição |
|-------|------|-----------|
| `cdi_id` | INT | ID único |
| `cdi_servidor_url` | VARCHAR(255) | URL do servidor CODI |
| `cdi_usuario` | VARCHAR(100) | Usuário do CODI |
| `cdi_senha` | VARCHAR(255) | Senha (criptografada) |
| `cdi_codename_empresa` | VARCHAR(100) | Code name da empresa no CODI |
| `cdi_ativo` | TINYINT | 1=ativo, 0=desativado |
| `cdi_ultima_sincronizacao` | DATETIME | Timestamp da última sync |
| `cdi_timeout_ms` | INT | Timeout em ms (default: 30000) |
| `cdi_retry_count` | TINYINT | Quantas vezes retry (default: 3) |

**Usar quando:** App precisa conectar ao CODI
**Exemplo:**
```sql
SELECT * FROM cdi_configuracao WHERE cdi_ativo = 1;
```

---

## 2. SINCRONIZAÇÃO

### `cdi_eventos`
Log de todos os eventos de produção sincronizados do CODI.

| Campo | Tipo | Descrição |
|-------|------|-----------|
| `cdi_evento_id` | INT | ID local |
| `cdi_codigo_evento_codi` | VARCHAR(100) | ID único do evento no CODI (UNIQUE) |
| `cdi_codigo_ordem_producao` | VARCHAR(50) | OP/Ordem de produção |
| `cdi_codigo_item` | VARCHAR(100) | SKU no CODI |
| `cdi_nome_item` | VARCHAR(255) | Nome do produto |
| `cdi_quantidade_produzida` | DECIMAL(15,4) | Quantidade produzida |
| `cdi_data_evento` | DATETIME | Quando ocorreu (não quando sincronizou) |
| `cdi_recurso_nome` | VARCHAR(150) | Máquina/recurso que produziu |
| `cdi_tipo_evento` | ENUM | PRODUCAO / SETUP / REJEITO / PARADA / OUTRO |
| `cdi_unidade_medida` | VARCHAR(20) | Unidade (pc, kg, l, etc) |
| `cdi_sync_id` | VARCHAR(100) | ID do batch de sync (rastreamento) |

**Índices:** data_evento, ordem, item, tipo, sync_id

**Usar quando:** Precisa histórico de produção real
**Exemplo:**
```sql
-- Produção dos últimos 7 dias
SELECT 
  cdi_codigo_ordem_producao,
  cdi_codigo_item,
  SUM(cdi_quantidade_produzida) as total_produzido,
  COUNT(*) as eventos
FROM cdi_eventos
WHERE cdi_data_evento >= DATE_SUB(NOW(), INTERVAL 7 DAY)
GROUP BY cdi_codigo_ordem_producao, cdi_codigo_item;
```

---

### `cdi_performance`
Snapshots de performance capturados do CODI em tempo real.

| Campo | Tipo | Descrição |
|-------|------|-----------|
| `cdi_perf_id` | INT | ID |
| `cdi_codigo_recurso` | VARCHAR(100) | ID do recurso/máquina |
| `cdi_nome_recurso` | VARCHAR(150) | Nome (ex: "Recurso01") |
| `cdi_timestamp_coleta` | DATETIME | Quando foi coletado |
| `cdi_disponibilidade` | DECIMAL(5,2) | % (0-100) |
| `cdi_performance` | DECIMAL(5,2) | % (0-100) |
| `cdi_oee` | DECIMAL(5,2) | OEE % (0-100) |
| `cdi_estado_atual` | ENUM | PRODUCAO / PARADO / SETUP / MANUTENCAO |
| `cdi_ordem_producao_current` | VARCHAR(50) | OP atualmente em produção |

**Usar quando:** Precisa status atual dos recursos
**Exemplo:**
```sql
-- Última performance de cada recurso
SELECT 
  cdi_nome_recurso,
  cdi_estado_atual,
  cdi_oee,
  cdi_timestamp_coleta
FROM cdi_performance p1
WHERE cdi_timestamp_coleta = (
  SELECT MAX(cdi_timestamp_coleta) FROM cdi_performance p2
  WHERE p2.cdi_codigo_recurso = p1.cdi_codigo_recurso
);
```

---

### `cdi_sincronizacao_log`
Auditoria completa de cada sincronização realizada.

| Campo | Tipo | Descrição |
|-------|------|-----------|
| `cdi_sync_log_id` | INT | ID |
| `cdi_sync_id` | VARCHAR(100) | ID único da sync (UNIQUE) |
| `cdi_timestamp_inicio` | DATETIME | Início da sync |
| `cdi_timestamp_fim` | DATETIME | Fim da sync |
| `cdi_endpoint_consultado` | VARCHAR(255) | Qual endpoint (ex: /relatorioEvento) |
| `cdi_status` | ENUM | SUCESSO / ERRO / PENDENTE / PARCIAL |
| `cdi_registros_sincronizados` | INT | Quantidade de registros |
| `cdi_registros_duplicados` | INT | Registros pulados por duplicação |
| `cdi_mensagem_erro` | TEXT | Se houve erro, descrição |
| `cdi_duracao_ms` | INT | Tempo em ms |

**Usar quando:** Precisa auditar sincronizações
**Exemplo:**
```sql
-- Últimas 10 sincronizações bem-sucedidas
SELECT * FROM cdi_sincronizacao_log
WHERE cdi_status = 'SUCESSO'
ORDER BY cdi_timestamp_inicio DESC
LIMIT 10;
```

---

## 3. EFICIÊNCIA

### `cdi_sku_mapping`
Mapeia SKUs entre CODI e ControlePCP (necessário se os códigos forem diferentes).

| Campo | Tipo | Descrição |
|-------|------|-----------|
| `cdi_sku_map_id` | INT | ID |
| `cdi_sku_codi` | VARCHAR(100) | SKU no CODI |
| `cdi_sku_controlepcp` | VARCHAR(100) | SKU no seu sistema |
| `cdi_nome_produto` | VARCHAR(255) | Nome comum |
| `cdi_unidade_medida_origem` | VARCHAR(20) | Unidade CODI (ex: "pc") |
| `cdi_unidade_medida_destino` | VARCHAR(20) | Unidade ControlePCP (ex: "un") |
| `cdi_fator_conversao` | DECIMAL(10,4) | Multiplicador (ex: 1.0, 10.0 se 10 un = 1 pc) |
| `cdi_ativo` | TINYINT | 1=ativo |

**Usar quando:** SKU do CODI é diferente do seu
**Exemplo:**
```sql
-- Converter quantidade do CODI para seu formato
SELECT 
  cdi_sku_codi,
  cdi_sku_controlepcp,
  cdi_fator_conversao
FROM cdi_sku_mapping
WHERE cdi_ativo = 1;
```

---

### `cdi_eficiencia_medicao` ⭐ **TABELA PRINCIPAL**

Core da integração: Cruzamento Previsto (ControlePCP) vs Realizado (CODI).

| Campo | Tipo | Descrição |
|-------|------|-----------|
| `cdi_efic_id` | INT | ID |
| `cdi_data_medicao` | DATE | Data de referência |
| `cdi_codigo_ordem_producao` | VARCHAR(50) | OP |
| `cdi_codigo_item` | VARCHAR(100) | SKU original |
| `cdi_sku_controlepcp` | VARCHAR(100) | SKU mapeado |
| **PROGRAMADO** | - | - |
| `cdi_quantidade_programada` | DECIMAL(15,4) | Qtd esperada |
| `cdi_tempo_producao_programado_min` | INT | Tempo esperado (min) |
| `cdi_velocidade_programada` | DECIMAL(10,4) | qtd/min esperada |
| **REALIZADO** | - | - |
| `cdi_quantidade_realizada` | DECIMAL(15,4) | Qtd real (CODI) |
| `cdi_tempo_producao_real_min` | INT | Tempo real (min) |
| `cdi_velocidade_real` | DECIMAL(10,4) | qtd/min real |
| **CÁLCULOS** | - | - |
| `cdi_desvio_quantidade` | DECIMAL(15,4) | Realizado - Programado |
| `cdi_desvio_percentual` | DECIMAL(8,2) | % (realizado/programado*100) |
| `cdi_eficiencia` | DECIMAL(5,2) | KPI final % |
| `cdi_status` | ENUM | ON_TIME / ATRASADO / ADIANTADO / NAO_PRODUZIDO |
| `cdi_margem_dias` | INT | Dias de diferença |
| `cdi_classificacao` | ENUM | EXCELENTE / BOM / ACEITAVEL / RUIM / CRITICO |

**Usar quando:** Precisa análise de eficiência

**Exemplo:**
```sql
-- OPs do mês com status
SELECT 
  cdi_data_medicao,
  cdi_codigo_ordem_producao,
  cdi_quantidade_programada,
  cdi_quantidade_realizada,
  cdi_eficiencia,
  cdi_status,
  cdi_classificacao
FROM cdi_eficiencia_medicao
WHERE cdi_data_medicao BETWEEN '2026-04-01' AND '2026-04-30'
ORDER BY cdi_data_medicao, cdi_status;

-- OPs em atraso
SELECT * FROM cdi_eficiencia_medicao
WHERE cdi_status = 'ATRASADO'
ORDER BY cdi_margem_dias DESC;
```

---

### `cdi_eficiencia_historico`
Auditoria de mudanças de status de eficiência.

**Usar quando:** Precisa rastrear quando um status mudou.

---

### `cdi_resumo_diario`
Cache pré-calculado dos resumos diários (melhora performance do dashboard).

| Campo | Importante |
|-------|-----------|
| `cdi_data_resumo` | Data (UNIQUE) |
| `cdi_total_ops` | Total de OPs no dia |
| `cdi_ops_no_prazo` | Contagem ON_TIME |
| `cdi_eficiencia_media` | Eficiência média % |
| `cdi_taxa_conclusao` | % de OPs concluídas |

**Usar quando:** Precisa gráficos/dashboard rápidos

---

## 4. VIEWS

### `cdi_view_eficiencia_atual`
View que retorna eficiência dos últimos 30 dias.

```sql
SELECT * FROM cdi_view_eficiencia_atual;
```

Retorna:
- Dados de eficiência filtrados
- Dias passados calculados
- Ordenado por data DESC

---

## 5. EXEMPLOS

### Query 1: Produção diária vs Programado

```sql
SELECT 
  DATE(cdi_data_medicao) as data,
  COUNT(*) as total_ops,
  SUM(cdi_quantidade_programada) as qtd_prog,
  SUM(cdi_quantidade_realizada) as qtd_real,
  ROUND((SUM(cdi_quantidade_realizada) / SUM(cdi_quantidade_programada) * 100), 2) as % realizado,
  ROUND(AVG(cdi_eficiencia), 2) as eficiencia_media
FROM cdi_eficiencia_medicao
GROUP BY DATE(cdi_data_medicao)
ORDER BY data DESC;
```

### Query 2: Problemas críticos

```sql
SELECT 
  cdi_codigo_ordem_producao,
  cdi_codigo_item,
  cdi_data_medicao,
  cdi_quantidade_programada,
  cdi_quantidade_realizada,
  cdi_desvio_percentual,
  cdi_classificacao,
  DATEDIFF(CURDATE(), cdi_data_medicao) as dias_atrasado
FROM cdi_eficiencia_medicao
WHERE cdi_classificacao IN ('CRITICO', 'RUIM')
  AND cdi_data_medicao >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
ORDER BY cdi_margem_dias ASC;
```

### Query 3: Performance de recursos

```sql
SELECT 
  cdi_nome_recurso,
  MAX(cdi_oee) as oee_max,
  AVG(cdi_oee) as oee_media,
  MIN(cdi_oee) as oee_min,
  MAX(cdi_timestamp_coleta) as ultima_coleta
FROM cdi_performance
WHERE cdi_timestamp_coleta >= DATE_SUB(NOW(), INTERVAL 7 DAY)
GROUP BY cdi_nome_recurso
ORDER BY oee_media DESC;
```

---

## 📋 CHECKLIST - Antes de usar

- [ ] Executar `run_codi_migrations.php` para criar tabelas
- [ ] Preencher `cdi_configuracao` com credenciais do CODI
- [ ] Preencher `cdi_sku_mapping` se SKUs forem diferentes
- [ ] Validar primeira sincronização em `cdi_sincronizacao_log`
- [ ] Conferir dados em `cdi_eventos` (devem ter registros)
- [ ] Testar cálculo em `cdi_eficiencia_medicao`

---

**Próxima etapa:** Criar CodiClient.php para conectar ao CODI
