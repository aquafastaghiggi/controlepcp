# 📋 PLANO: Integração de Dados Históricos - Sequenciamento

## Objetivo
Trazer dados históricos de programações ja executadas e visualizá-los graficamente com comparação **planejado vs realizado**.

---

## 🗂️ ETAPAS DO PROJETO

### **ETAPA 1: Preparação de Dados (BD)**
**Objetivo**: Audit ar dados históricos e criar queries de suporte

#### 1.1 - Diagnosticar Dados Históricos
- **Arquivo**: `api/sequenciamento.php` - nova action `diagnostico`
- **O que faz**: 
  - Conta quantas `sch_linhas` têm `sch_fim_producao` preenchido (executadas)
  - Conta quantas estão vazias (planejadas)
  - Mostra período min/max de execução
  - Mostra distribuição por tipo (setup vs produção)
- **Entrada**: `?action=diagnostico`
- **Saída**: JSON com estatísticas
- **Tempo est.**: 30 min

#### 1.2 - Query de Históricos Enriquecida
- **Arquivo**: `api/sequenciamento.php` - nova action `historicos`
- **O que faz**:
  - Busca `sch_linhas` COM `sch_fim_producao` (históricos executados)
  - Calcula desvios: (real - planejado)
  - Agrupa por programação + SKU
  - Retorna indicadores: pontualidade, eficiência real
- **Entrada**: `?action=historicos&periodo=7d` (últimos 7 dias)
- **Saída**: JSON estruturado com históricos
- **Tempo est.**: 1h

---

### **ETAPA 2: Backend - Endpoints Novos**
**Objetivo**: Criar 3 endpoints para diferentes contextos

#### 2.1 - Endpoint: Históricos Agregados
- **URL**: `/api/sequenciamento.php?action=historicos&period=7d&prg_id=opcional`
- **Retorna**:
  ```json
  {
    "sucesso": true,
    "periodo": "últimos 7 dias",
    "resumo": {
      "total_executados": 245,
      "pontualidade_percentual": 78.5,
      "desvio_tempo_medio_minutos": 12.3,
      "eficiencia_real_media": 82.1
    },
    "historicos": [
      {
        "prg_id": 1,
        "numero_op": "OP001",
        "linha": "LN02",
        "sku": "20150055",
        "tipo": "producao",
        "duracao_planejada_min": 120,
        "duracao_real_min": 135,
        "desvio_minutos": 15,
        "desvio_percentual": 12.5,
        "data_execucao": "2026-04-06",
        "hora_inicio_real": "09:30",
        "hora_fim_real": "11:45",
        "quantidade_planejada": 1000,
        "quantidade_produzida": 950,
        "pontual": false,
        "status_execucao": "concluído"
      }
    ]
  }
  ```
- **Tempo est.**: 1h

#### 2.2 - Endpoint: Comparativo Planejado vs Realizado
- **URL**: `/api/sequenciamento.php?action=comparativo&prg_id=1`
- **Retorna**: Dados para visualizar barras duplas (planejado + realizado)
- **Tempo est.**: 45 min

#### 2.3 - Endpoint: Resumo por Dia
- **URL**: `/api/sequenciamento.php?action=resumo_diario&data_inicio=2026-04-01&data_fim=2026-04-07`
- **Retorna**: Agregações diárias (total executado, pontualidade, etc)
- **Tempo est.**: 45 min

---

### **ETAPA 3: Frontend - Visualizações Históricas**
**Objetivo**: Mostrar gráfico com históricos + planejado

#### 3.1 - Toggle: Exibir Históricos
- Nova opção na toolbar: "Modo: Planejado | Históricos | Comparativo"
- Ao selecionar "Históricos", muda API chamada de `timeline` para `historicos`
- **Tempo est.**: 30 min

#### 3.2 - Renderização Dupla (Planejado vs Real)
- Ao renderizar modo "Comparativo":
  - Mostra **barra fina clara** = planejado
  - Mostra **barra dura escura** = realizado
  - Overlay mostrando diferença
- Cores especiais para:
  - 🟢 Dentro do prazo (desvio ≤ 5%)
  - 🟡 Atrasado (desvio 5-15%)
  - 🔴 Muito atrasado (desvio > 15%)
- **Tempo est.**: 1.5h

#### 3.3 - Painel de Estatísticas Globais
- Sidebar com resumo:
  - Pontualidade: XX%
  - Desvio tempo médio: XXmin
  - Itens no prazo: XX%
  - Eficiência real: XX%
- Atualiza conforme filtro
- **Tempo est.**: 1h

#### 3.4 - Hover Expandido em Históricos
- Tooltip maior mostrando:
  - Hora planejada vs hora real
  - Desvio em minutos
  - Quantidade planejada vs produzida
  - Taxa de eficiência
- **Tempo est.**: 45 min

---

### **ETAPA 4: Validação & Testes**
**Objetivo**: Garantir que dados históricos fazem sentido

#### 4.1 - Teste Manual
- Visualizar últimos 7 dias de execução
- Confirmar se dados estão corretos
- Validar se desvios fazem sentido
- **Tempo est.**: 45 min

#### 4.2 - Teste de Casos Extremos
- Itens executados muito antes (antecipado)
- Itens muito atrasados
- Itens incompletos (quantidade produzida < planejada)
- **Tempo est.**: 30 min

#### 4.3 - Documento de Validação
- Criar relatório com screenshots
- Listar discrepâncias encontradas
- Sugerir ajustes
- **Tempo est.**: 1h

---

### **ETAPA 5: Recursos Avançados (Opcional)**
**Objetivo**: Melhorias pós-validação

#### 5.1 - Exportar Históricos (CSV)
- Botão "Exportar" que baixa CSV com dados históricos
- **Tempo est.**: 45 min

#### 5.2 - Gráficos KPI (Charts.js)
- Gráfico de barras: Defeitos/Atrasos por dia
- Gráfico de pizza: % Pontual vs Atrasado
- **Tempo est.**: 2h

#### 5.3 - Filtro Temporal Avançado
- Data picker: "De XX até YY"
- Preset: "Últimos 7 dias", "Este mês", "Últimos 30"
- **Tempo est.**: 1h

---

## 📊 Timeline Total Estimado

| Etapa | Subtarefas | Tempo | Status |
|-------|-----------|-------|--------|
| **1** | Diagnóstico + Query | 1.5h | ⏳ Não iniciado |
| **2** | 3 Endpoints | 2.5h | ⏳ Não iniciado |
| **3** | Frontend completo | 4.5h | ⏳ Não iniciado |
| **4** | Validação | 2h | ⏳ Não iniciado |
| **5** | Extras (opcional) | 4h | ⏳ Não iniciado |
| **TOTAL** | | **~12-14h** | |

---

## 🎯 Proposta de Execução

### **BLOCO A - Backend (ETAPA 1-2)** → ~4h
Implement 1-2 endpoints para retornar dados históricos

### **BLOCO B - Frontend (ETAPA 3)** → ~4.5h
Adicionar visualizações no gráfico

### **BLOCO C - Validação (ETAPA 4)** → ~2h
Testar dados e confirmar com você

### **Sugestão**: 
1. ✅ Comece pelo BLOCO A (endpoints) - rápido e independente
2. ✅ Depois BLOCO B (visual) - vocês ver dados reais
3. ✅ Depois BLOCO C (validação) - confirmar que faz sentido
4. ⚪ BLOCO D (extras) - se houver tempo/necessidade

---

## 🔧 Dados Disponíveis para Usar

**Tabela: `sch_linhas`** (245 registros conforme visto)
```sql
Planejado:
- sch_data_inicio (DATE)
- sch_hora_inicio / sch_hora_fim (TIME)
- sch_duracao_minutos (INT)
- sch_quantidade (DECIMAL) - planejado
- sch_tipo ('setup' | 'producao')

Realizado:
- sch_inicio_producao (DATETIME) 
- sch_fim_producao (DATETIME)
- sch_produzido_estimado (DECIMAL) - realizado
- sch_status (VARCHAR) - status execução
```

---

## ✅ Checklist de Sucesso

- [ ] Diagnóstico mostra quantos históricos temos
- [ ] API retorna dados históricos corretamente
- [ ] Frontend renderiza modo "Históricos"
- [ ] Mostra comparação planejado vs real
- [ ] Filtros funcionam em modo histórico
- [ ] Você valida que dados fazem sentido
- [ ] Desvios são calculados corretamente
- [ ] Estatísticas globais estão precisas
- [ ] Pronto para integração com CODI

---

## ❓ Perguntas antes de começar

1. **Quer começar pelo BLOCO A logo?** (endpoints)
2. **Qual período quer analisar primeiro?** (últimos 7 dias? Este mês?)
3. **Alguma métrica específica é importante?** (pontualidade? eficiência? quantidade?)
4. **Quer fazer exportação em CSV desde já?** (ou deixa como extra)

