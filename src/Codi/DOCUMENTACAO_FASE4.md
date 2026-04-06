# FASE 4 - CALCULADORA DE EFICIÊNCIA

## 📋 Visão Geral

A **EficienciaCalculator** é o coração da FASE 4, responsável por:

1. ✅ **Cruzar dados** programados (ControlePCP) com reais (CODI)
2. ✅ **Calcular desvios** entre previsto e realizado
3. ✅ **Gerar KPIs** (OEE, Performance, Disponibilidade, etc)
4. ✅ **Classificar status** em OK, Aviso ou Crítico
5. ✅ **Persistir resultados** na tabela `cdi_eficiencia_medicao`
6. ✅ **Manter auditoria** em `cdi_eficiencia_historico`

## 🎯 Fluxo de Funcionamento

```
┌─────────────────────────────────────────────────────────────┐
│ USER: Inicia calcularEficienciaCompleta()                  │
│       com período data_inicio → data_fim                    │
└──────────────┬──────────────────────────────────────────────┘
               │
               ▼
┌──────────────────────────────────────────────────────────┐
│ 1. COLETAR PROGRAMAÇÕES                                  │
│    SELECT * FROM programacoes                            │
│    WHERE data_prevista_inicio >= date_inicio             │
│    AND data_prevista_fim <= date_fim                     │
│    AND status = 'finalizada'                             │
└──────────┬───────────────────────────────────────────────┘
           │
           ▼
┌──────────────────────────────────────────────────────────┐
│ 2. PARA CADA PROGRAMAÇÃO: buscarPerformanceReal()       │
│    SELECT SUM(quantidade), SUM(tempo_min), etc           │
│    FROM cdi_performance                                  │
│    WHERE recurso_id = ? AND data BETWEEN ? AND ?         │
└──────────┬───────────────────────────────────────────────┘
           │
           ▼
┌──────────────────────────────────────────────────────────┐
│ 3. CALCULAR DESVIOS                                      │
│    • Quantidade: realizado vs previsto                   │
│    • Tempo: tempo_real vs tempo_padrão                   │
│    • Prazo: data_realizada vs data_prevista              │
└──────────┬───────────────────────────────────────────────┘
           │
           ▼
┌──────────────────────────────────────────────────────────┐
│ 4. CALCULAR KPIs                                         │
│    • Eficiência QTD = realizado / previsto * 100         │
│    • Performance = tempo_padrão / tempo_real * 100       │
│    • Disponibilidade = (total - parado) / total * 100    │
│    • OEE = Efic × Perf × Disp / 10000                    │
│    • Produtividade = quantidade / horas                  │
└──────────┬───────────────────────────────────────────────┘
           │
           ▼
┌──────────────────────────────────────────────────────────┐
│ 5. DETERMINAR STATUS                                     │
│    OK    → Todos KPIs acima dos limites                  │
│    AVISO → Alguns KPIs abaixo dos limites de aviso       │
│    CRÍTICO → KPIs abaixo dos limites críticos            │
└──────────┬───────────────────────────────────────────────┘
           │
           ▼
┌──────────────────────────────────────────────────────────┐
│ 6. PERSISTIR EM BD                                       │
│    INSERT INTO cdi_eficiencia_medicao                    │
│    (On DUPLICATE KEY UPDATE)                             │
└──────────┬───────────────────────────────────────────────┘
           │
           ▼
┌──────────────────────────────────────────────────────────┐
│ RETORNAR RESULTADO                                       │
│  • sucesso: bool                                         │
│  • periodosProcessados: int                              │
│  • desviosCalculados: int                                │
│  • erros: array[error]                                   │
│  • detalhes: array[eficiencia_calculada]                 │
└──────────────────────────────────────────────────────────┘
```

## 🔧 API Principal

### `calcularEficienciaCompleta($dataInicio, $dataFim, $opcoes)`

Calcula eficiência para todas as programações de um período.

```php
$calculadora = new EficienciaCalculator($db);

$resultado = $calculadora->calcularEficienciaCompleta(
    '2026-04-01',      // Data início (YYYY-MM-DD)
    '2026-04-06',      // Data fim (YYYY-MM-DD)
    [
        'recurso_id' => 1,                 // Opcional: filtrar por recurso
        'eficiencia_critica' => 70,        // Limite crítico de eficiência
        'eficiencia_aviso' => 85,          // Limite aviso de eficiência
        'oee_critica' => 50,               // Limite crítico de OEE
        'oee_aviso' => 75,                 // Limite aviso de OEE
        'atraso_dias_critico' => 5,        // Dias de atraso = crítico
        'atraso_dias_aviso' => 2           // Dias de atraso = aviso
    ]
);

// Resposta:
// {
//   "sucesso": true,
//   "periodosProcessados": 45,
//   "desviosCalculados": 120,
//   "erros": [],
//   "detalhes": [
//     {
//       "programacao_id": 1,
//       "recurso_id": 5,
//       "previsto": {...},
//       "realizado": {...},
//       "desvios": {...},
//       "kpis": {...},
//       "status": {...}
//     },
//     ...
//   ]
// }
```

### Retorno - Estrutura Detalhada

```javascript
{
  // Status geral da execução
  "sucesso": true,
  "periodosProcessados": 45,
  "desviosCalculados": 120,
  
  // Erros encontrados durante processamento
  "erros": [
    {
      "programacaoId": 123,
      "erro": "Mensagem de erro específico"
    }
  ],
  
  // Detalhes de cada programação processada
  "detalhes": [
    {
      "programacao_id": 1,
      "recurso_id": 5,
      
      // Dados programados
      "previsto": {
        "quantidade": 1000,
        "tempo_padrao_horas": 10.5,
        "data_inicio": "2026-04-01",
        "data_fim": "2026-04-02"
      },
      
      // Dados reais do CODI
      "realizado": {
        "quantidade": 950,
        "tempo_real_horas": 11.2,
        "tempo_parado_min": 45,
        "data_inicio": "2026-04-01",
        "data_fim": "2026-04-02T08:30:00"
      },
      
      // Desvios calculados
      "desvios": {
        "quantidade": {
          "previsto": 1000,
          "realizado": 950,
          "desvio_unidades": -50,
          "desvio_percentual": -5.0
        },
        "tempo": {
          "tempo_padrao_horas": 10.5,
          "tempo_real_horas": 11.2,
          "desvio_horas": 0.7,
          "desvio_percentual": 6.67
        },
        "data": {
          "data_prevista": "2026-04-02",
          "data_realizada": "2026-04-02T08:30:00",
          "dias_atraso": 0,
          "status_prazo": "no_prazo"
        }
      },
      
      // KPIs calculados
      "kpis": {
        "eficiencia_quantidade": 95.0,       // 95% de eficiência
        "performance_tempo": 93.75,          // 93.75% de performance
        "disponibilidade": 93.28,            // 93.28% de disponibilidade
        "oee": 82.45,                        // Overall Equipment Effectiveness
        "produtividade_por_hora": 84.82      // 84.82 unidades/hora
      },
      
      // Status determinado baseado nos limites
      "status": {
        "geral": "ok",
        "niveis": {
          "eficiencia": "ok",
          "oee": "ok",
          "prazo": "ok"
        },
        "detalhes": []
      },
      
      "data_calculo": "2026-04-06T15:30:00"
    }
  ]
}
```

## 📊 Indicadores Calculados (KPIs)

| KPI | Fórmula | Range | Interpretação |
|-----|---------|-------|-----------------|
| **Eficiência de Quantidade** | (Realizado / Previsto) × 100 | 0-100% | % Atingimento de quantidade |
| **Performance de Tempo** | (Tempo Padrão / Tempo Real) × 100 | 0-100% | Velocidade vs padrão |
| **Disponibilidade** | (Total - Parado) / Total × 100 | 0-100% | % de tempo disponível |
| **OEE** | (Efic × Perf × Disp) / 10000 | 0-100% | Eficiência global do equipamento |
| **Produtividade** | Quantidade / Horas | ∞ | Peças/hora produzidas |

## ⚙️ Limites Padrão

```php
$limites_padrao = [
    'eficiencia_critica' => 70,      // Abaixo = vermelho
    'eficiencia_aviso' => 85,         // 70-85 = amarelo
    'oee_critica' => 50,
    'oee_aviso' => 75,
    'atraso_dias_critico' => 5,       // Crítico com 5+ dias
    'atraso_dias_aviso' => 2          // Aviso com 2-4 dias
];
```

## 🗂️ Tabelas de Suporte

### `cdi_eficiencia_medicao` (Principal)
Armazena cada medição de eficiência calculada.

```sql
-- Principais colunas:
- programacao_id: FK para programação original
- recurso_id: FK para máquina/recurso
- previsto_quantidade, previsto_tempo_horas
- realizado_quantidade, realizado_tempo_horas
- desvio_quantidade, desvio_quantidade_perc
- desvio_tempo, desvio_tempo_perc, desvio_dias
- taxa_eficiencia, taxa_performance, taxa_disponibilidade
- oee, produtividade
- status_geral: 'ok' | 'aviso' | 'critico'
- data_medicao: timestamp do cálculo
```

### `cdi_eficiencia_historico` (Auditoria)
Registra mudanças de status ao longo do tempo.

```sql
-- Registra:
- Quando status mudou de OK → Crítico
- Quem fez a mudança
- Razão/detalhes da mudança
- Timestamp da mudança
```

## 📝 Exemplos de Uso

### Exemplo 1: Cálculo Simples
```php
$calculadora = new EficienciaCalculator($db);
$resultado = $calculadora->calcularEficienciaCompleta('2026-04-01', '2026-04-06');

if ($resultado['sucesso']) {
    echo "Processadas {$resultado['periodosProcessados']} programações\n";
}
```

### Exemplo 2: Com Filtro de Recurso
```php
$resultado = $calculadora->calcularEficienciaCompleta(
    '2026-04-01',
    '2026-04-06',
    ['recurso_id' => 5]  // Apenas máquina 5
);
```

### Exemplo 3: Com Limites Customizados
```php
$resultado = $calculadora->calcularEficienciaCompleta(
    '2026-04-01',
    '2026-04-06',
    [
        'eficiencia_critica' => 90,   // Mais rigoroso
        'eficiencia_aviso' => 95,
        'oee_critica' => 80
    ]
);
```

### Exemplo 4: Monitorar Logs
```php
$calculadora->setLogging(true);
$resultado = $calculadora->calcularEficienciaCompleta(...);

$logs = $calculadora->getLogs();
foreach ($logs as $log) {
    echo "[{$log['timestamp']}] {$log['nivel']}: {$log['mensagem']}\n";
}
```

## 📍 Localização dos Arquivos

```
src/Codi/
├── EficienciaCalculator.php        ← Classe principal
├── exemplo_eficiencia.php          ← 8 exemplos práticos
├── eficiencia_dashboard.php        ← Interface web
├── DOCUMENTACAO_FASE4.md           ← Este arquivo
└── config.php                       ← Configuração compartilhada
```

## 🚀 Próximos Passos (FASE 5)

- Criar endpoints REST para consumir eficiência calculada
- Expor dados via API: `/api/codi_eficiencia.php`
- Suporte a filtros e paginação
- Dashboard visual dos KPIs

## 🔗 Dependências

- ✅ FASE 1: Banco de dados (tabelas de eficiência)
- ✅ FASE 2: CodiClient (não usado diretamente)
- ✅ FASE 3: CodiSyncService (sincroniza performance antes do cálculo)
- ✅ ControlePCP: Tabelas de programações e recursos
