# PROMPT: Estado Atual do Relatório Gantt.php - Briefing Técnico

---

## 📋 CONTEXTO

Você está analisando o arquivo `gantt.php` do projeto **ControlePCP (Sistema de Planejamento e Controle da Produção - PCP)**. Este é um relatório interativo baseado em **DHTMLX Gantt** que visualiza o sequenciamento de produção com dados integrados da API CODI.

---

## 🔧 ARQUITETURA ATUAL

### Stack Técnico
- **Backend:** PHP 8.2 com PDO (MySQL)
- **Frontend:** DHTMLX Gantt 9.x (library JavaScript)
- **Dados Previsto:** Tabela `prg_agenda` (schedule da programação)
- **Dados Realizado:** Tabela `realizado_2026_excel` (sincronizado diariamente via CODI API)
- **Ambiente:** VirtualHost Apache em porta 8081 (sandbox)

### Estrutura de Dados
```
PREVISTO (Planejado):
├── prg_agenda.sch_inicio_producao
├── prg_agenda.sch_fim_producao
├── prg_agenda.sch_quantidade
├── prg_agenda.sch_descricao (produto)
├── prg_agenda.sch_tipo (SETUP ou SKU)
└── prg_itens.prg_itens_op (número da OP)

REALIZADO (Executado via CODI):
├── realizado_2026_excel.data_evento
├── realizado_2026_excel.ordem_op
├── realizado_2026_excel.quantidade
└── [AGRUPADO POR OP para comparação com Previsto]
```

---

## 📊 FUNCIONALIDADES IMPLEMENTADAS

### 1. Visualização Gantt (Backend - PHP)
- ✅ **Busca de Programações:** Dropdown com lista de programações disponíveis
- ✅ **Mapeamento de OPs:** Resolve SKU → OP via tabela `prg_itens`
- ✅ **Integração Previsto/Realizado:** 
  - Busca dados agrupados de `realizado_2026_excel` por período
  - Calcula percentual de cumprimento: `(realizado / previsto) * 100`
- ✅ **Color Coding por Eficiência:**
  - Verde (≥100%): Produção atendida ou acima
  - Laranja (80-99%): Produção parcial
  - Vermelho (<80%): Produção abaixo
- ✅ **Tratamento de SETUP:** Linhas de setup diferenciadas, sem dados de realizado

### 2. Renderização Gantt (Frontend - JavaScript)
- ✅ **Grid Hierárquico (2 colunas):**
  - Coluna 1: "Produto/Recurso" → OP na primeira linha + Nome do Produto abaixo
  - Coluna 2: "Previsto | Realizado" → Badges coloridas com quantidades
- ✅ **Timeline (Horizontal):**
  - Cabeçalho hierárquico: Semana / Dia
  - Barras de tarefas coloridas por eficiência
  - Overlay INFO sobre cada barra: `Realizado | Previsto (%)` 
- ✅ **Scrolls Persistentes:**
  - Scroll vertical: Navegar entre itens
  - Scroll horizontal: Navegar no tempo
  - Ambos sempre visíveis (forçado via CSS e config)
- ✅ **Layout:** row_height=44px (espaço para 2 linhas de texto no grid)

### 3. Sincronização CODI Automática
- ✅ **Auto-Sync na Abertura:** Verifica localStorage se já sincronizou hoje
  - Se não, executa `fetch('api/sync_codi.php', {action: 'sync_yesterday'})`
  - Silencioso (log apenas no console)
  - Marca localStorage com a data para evitar re-sync no mesmo dia
- ✅ **Botão Manual "🔄 Sincronizar CODI":**
  - Usa `force: true` para ignorar limite diário
  - Feedback visual: "⏳ Sincronizando..." → Alert com resultado
  - Re-ativa botão após conclusão

---

## 💻 BACKEND - PHP (api/sync_codi.php)

### Última Correção (09/04/2026)
**Problema:** JSON parsing error "Unexpected end of JSON input" 
- **Causa:** Comando `python` global não executava corretamente
- **Solução Implementada:**
  ```php
  $venvPython = __DIR__ . '/../.venv/Scripts/python.exe';
  if (!file_exists($venvPython)) {
      $venvPython = 'python'; // Fallback para global
  }
  $command = escapeshellcmd("\"$venvPython\" \"$pythonScript\"");
  exec($command . " 2>&1", $output, $returnCode);
  ```
- **Status:** ✅ Testado - Retorna HTTP 200 com JSON válido
- **Resposta Esperada:**
  ```json
  {
    "success": false,
    "message": "Já foi sincronizado hoje (1666 registros inseridos)...",
    "alreadySynced": true,
    "recordsToday": 1666
  }
  ```

### Script Python Executado (sync_codi_yesterday.py)
- Puxa dados da API CODI: `http://192.168.8.246:8080/action/ger/webservice/rest/relatorioEventoConsolidado`
- Extrai: ordem_op, quantidade por data_evento (últimas 150 dias)
- Insere em `realizado_2026_excel` com `ON DUPLICATE KEY UPDATE`
- Credenciais: marcos.brun / Eb035611! (HTTP Basic Auth)

---

## 📍 URLs & Acesso

### Sandbox (Desenvolvimento)
- **Gantt:** `http://localhost:8081/gantt.php`
- **API Sync:** `http://localhost:8081/api/sync_codi.php`
- **Banco:** controlepcp_sandbox
- **User DB:** controlepcp_sbx / 7e10f4a8150344cc!

### Produção
- **Gantt:** `http://localhost/controlepcp/gantt.php`
- **API Sync:** `http://localhost/controlepcp/api/sync_codi.php`
- **Banco:** controlepcp
- **User DB:** pcp_app / k7m2y9u4

---

## 🐛 ESTADO ATUAL & CONHECIDOS

### Working ✅
- Visualização Gantt com Previsto/Realizado
- Color coding por eficiência (verde/laranja/vermelho)
- Scrolls horizontal e vertical funcionais
- Auto-sync na abertura funcional
- Botão manual sync funcional
- Python venv executa corretamente
- JSON parsing sem erros

### Dados
- **Tabela realizado_2026_excel:** 1.666 registros sincronizados
- **Data de Sincronização:** Diariamente automaticamente, ou manual com botão
- **Período:** Últimas 150 dias (configurable em sync_codi_yesterday.py)

### Últimas Commits (GitHub)
1. `963eb5d` - Fix: Corrigir execução Python em sync_codi.php usando venv
2. `5d142bb` - Sincronização: gantt.php com Previsto/Realizado
3. `f7efe8e` - Ajustes no Gantt - Previsto e Realizado com nomes dos produtos

---

## 📝 ARQUIVOS PRINCIPAIS

| Arquivo | Responsabilidade |
|---------|------------------|
| `gantt.php` | Renderização principal do Gantt (PHP + JS) |
| `api/sync_codi.php` | Endpoint para sincronizar dados CODI |
| `sync_codi_yesterday.py` | Script Python que puxa dados da API CODI |
| `realizado_2026_excel` (DB) | Tabela com dados históricos sincronizados |
| `prg_agenda` (DB) | Schedule planejado das programações |
| `prg_itens` (DB) | Mapeamento SKU → OP |

---

## 🚀 PRÓXIMAS ETAPAS / BACKLOG

Se precisar de melhorias, considere:
1. ⏳ **Performance:** Pagination se houver >5000 itens
2. ⏳ **Filtros:** Adicionar filtro por Data, Linha, Status
3. ⏳ **Exportação:** CSV/PDF da visualização
4. ⏳ **Detalhes:** Modal com informações completas ao clicar na barra
5. ⏳ **Comparativo:** Gráfico de eficiência ao longo do tempo

---

## 🔗 CONTATO COM DADOS

### Acesso ao Banco
```bash
# Sandbox
mysql -h 127.0.0.1 -u controlepcp_sbx -p7e10f4a8150344cc! controlepcp_sandbox

# Produção
mysql -h 127.0.0.1 -u pcp_app -pk7m2y9u4 controlepcp
```

### Verificação Rápida
```sql
-- Ver dados de realizado (últimos 7 dias)
SELECT data_evento, ordem_op, SUM(quantidade) as total
FROM realizado_2026_excel
WHERE data_evento >= DATE_SUB(NOW(), INTERVAL 7 DAY)
GROUP BY data_evento, ordem_op
ORDER BY data_evento DESC;
```

---

## ✨ Resumo Executivo

O **gantt.php** é um relatório interativo e funcional que:
- ✅ Visualiza o sequenciamento de produção (Previsto vs Realizado)
- ✅ Integra dados da API CODI automaticamente
- ✅ Mostra eficiência de cada OP com color coding
- ✅ Sincroniza dados diariamente
- ✅ É responsivo com scrolls sempre visíveis
- ⚠️ Recentemente corrigido: Python venv execution em sync_codi.php

**Status Geral:** Production-Ready com auto-sync funcional.
