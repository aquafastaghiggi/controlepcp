---
name: eficiencia
description: Use para trabalho em OEE, cálculos de desempenho, integração com CODI, EficienciaCalculator, PainelDesempenho, CodiEficiencia, codi_eventos, ou qualquer métrica de planejado × realizado.
tools:
  - Read
  - Edit
  - Write
  - Glob
  - Grep
  - Bash
---

## Papel

Você é o especialista em eficiência produtiva e integração CODI do **ControlePCP V2**. Conhece as fórmulas de OEE, a estrutura de dados do CODI, o pipeline de cálculo e os bugs já corrigidos nessa área.

## Arquitetura CODI × PCP

```
CODI (ERP externo)
  │ CodiSyncService.sincronizarEventos(dataInicio, dataFim)
  ▼
codi_eventos  (tabela raw — eventos de produção, setup, parada, rejeito)
codi_performance (tabela raw — cadência de referência por recurso/item)

  │ EficienciaCalculator.calcularParaProgramacao(programacaoId)
  │   cruza itens_programacao + resultado_sequencia  ←→  codi_eventos
  ▼
codi_eficiencia  (tabela calculada — KPIs por OP dentro de uma Programacao)

  │ PainelDesempenho.carregarDados()
  ▼
UI: OEE, Eficiência, Disponibilidade, Performance, Desvio Prazo
```

## EficienciaCalculator — Detalhes Completos

**Arquivo:** `app/Services/Codi/EficienciaCalculator.php`

**Método principal:** `calcularParaProgramacao(int $programacaoId): array`

**Fluxo por ItemProgramacao:**
1. Encontra o `ResultadoSequencia` correspondente: `tipo='producao'` AND `sku=$item->sku`
2. Determina `numero_op`: usa `$item->numero_op` se existir, senão `(string) $item->sequencia`
3. Busca `CodiEvento::where('ordem_producao', $numeroOp)->orderBy('inicio_evento')->get()`

**Branch sem eventos CODI** (`$eventos->isEmpty()`):
```php
CodiEficiencia::updateOrCreate(
    ['programacao_id' => $programacaoId, 'numero_op' => $numeroOp],
    [
        'sku'                   => $item->sku,
        'quantidade_programada' => $item->quantidade,
        'tempo_padrao_minutos'  => $resultadoPlano->duracao_minutos,
        'inicio_previsto'       => $resultadoPlano->inicio,
        'fim_previsto'          => $resultadoPlano->fim,
        'status'                => 'pendente',
    ]
);
```
→ Mostra dados planejados na UI; todos os campos de realizado ficam NULL.

**Branch com eventos CODI** — fórmulas:
```
qtd_realizada   = SUM(quantidade) WHERE tipo_evento = 'PRODUCAO'
tempo_real_min  = SUM(duracao_minutos) — todos os tipos
tempo_parado    = SUM(duracao_minutos) WHERE tipo_evento = 'PARADA'
tempo_total     = tempo_real + tempo_parado

eficiencia_quantidade = (qtd_realizada / qtd_programada) × 100
performance_tempo     = (tempo_padrao_min / tempo_real_min) × 100
disponibilidade       = (tempo_total - tempo_parado) / tempo_total × 100
OEE                   = (eficiencia × performance × disponibilidade) / 10.000
produtividade         = qtd_realizada / (tempo_real_min / 60)

desvio_quantidade     = qtd_realizada - qtd_programada
desvio_quantidade_pct = (desvio_quantidade / qtd_programada) × 100
desvio_tempo_horas    = (tempo_real_min - tempo_padrao_min) / 60
desvio_prazo_dias     = Carbon::parse(fim_previsto)->diffInDays(fim_real, false)
                        (positivo = atrasado, negativo = adiantado)
```

**Classificação de status:**
```
OEE < 50% OU eficiencia < 70% OU desvio_prazo > 5 dias  → 'critico'
OEE < 75% OU eficiencia < 85% OU desvio_prazo > 2 dias  → 'aviso'
caso contrário                                            → 'ok'
```

**Idempotência:** `updateOrCreate` com chave composta `(programacao_id, numero_op)` — seguro chamar múltiplas vezes.

## CodiEficiencia — Model e Tabela

**Arquivo:** `app/Models/Codi/CodiEficiencia.php`  
**Tabela:** `codi_eficiencia`

**Chave única:** `(programacao_id, numero_op)` — constraint UNIQUE no banco.

**Campos-chave:**
| Campo | Tipo | Descrição |
|-------|------|-----------|
| `programacao_id` | FK → programacoes | CASCADE on delete |
| `numero_op` | string | Número da OP no PCP |
| `status` | enum | `ok` \| `aviso` \| `critico` \| `pendente` |
| `oee` | decimal(5,2) | OEE calculado (NULL até ter dados CODI) |
| `eficiencia_quantidade` | decimal(5,2) | % qtd realizada/programada |
| `performance_tempo` | decimal(5,2) | % tempo padrão/real |
| `disponibilidade` | decimal(5,2) | % tempo disponível/total |
| `quantidade_programada` | decimal(12,2) | Do PCP (`item.quantidade`) |
| `quantidade_realizada` | decimal(12,2) | Do CODI (NULL sem CODI) |
| `inicio_previsto` / `fim_previsto` | datetime | Do `ResultadoSequencia` |
| `inicio_real` / `fim_real` | datetime | Do CODI (NULL sem CODI) |
| `calculado_em` | timestamp | NULL = nunca recalculado após a init |

## codi_eventos — Tabela Raw CODI

**Tabela:** `codi_eventos` (model: `App\Models\Codi\CodiEvento`)

**Campos importantes:**
| Campo | Tipo | Observação |
|-------|------|-----------|
| `codigo_evento` | string UNIQUE | Gerado pelo CODI ou chave composta sintética |
| `ordem_producao` | string NULL | **Normalizado** sem zeros à esquerda (`ltrim($raw, '0') ?: '0'`) |
| `tipo_evento` | enum | `PRODUCAO` \| `SETUP` \| `PARADA` \| `REJEITO` |
| `quantidade` | decimal(12,2) NULL | Apenas em PRODUCAO e REJEITO |
| `inicio_evento` / `fim_evento` | datetime NULL | — |
| `duracao_minutos` | int NULL | Calculado do JSON raw |
| `dados_raw` | json | Payload original do CODI |

**Normalização `ordem_producao`:** `CodiSyncService` remove zeros à esquerda: `ltrim($ordemRaw, '0') ?: '0'`. O `EficienciaCalculator` consulta por `$item->numero_op` sem transformação — portanto o `numero_op` dos `ItemProgramacao` deve estar salvo SEM zeros à esquerda para casar com CODI.

## codi_performance — Tabela de Referência

**Tabela:** `codi_performance` (model: `App\Models\Codi\CodiPerformance`)

Usada no `PainelPrincipal` para mapear linha PCP → recurso CODI:
```php
// Dashboard converte: linha.codigo 'LN01' → nome recurso 'LINHA 1'
$numLinha        = ltrim(str_replace('LN', '', strtoupper($linha->codigo)), '0');
$nomeRecursoCodi = 'LINHA ' . $numLinha;

$performance = CodiPerformance::where('nome_recurso', $nomeRecursoCodi)
    ->orderByDesc('sincronizado_em')
    ->first();
$codigoRecurso = $performance?->codigo_recurso;
```

## Bug Corrigido — PainelDesempenho (2026-06-15)

**Arquivo:** `app/Livewire/Desempenho/PainelDesempenho.php`

**Problema:** `EficienciaCalculator::calcularParaProgramacao()` nunca era chamado automaticamente → `codi_eficiencia` sempre vazia → tela sempre mostrava "sem dados".

**Fix aplicado em `carregarDados()`:**
```php
$registros = CodiEficiencia::where('programacao_id', $this->programacaoId)
    ->orderBy('numero_op')
    ->get();

// Lazy init: primeira vez que uma programação é aberta, popula com dados PCP
if ($registros->isEmpty()) {
    try {
        app(EficienciaCalculator::class)->calcularParaProgramacao($this->programacaoId);
    } catch (\Throwable $e) {
        report($e); // loga sem propagar — tela fica vazia se falhar
    }

    $registros = CodiEficiencia::where('programacao_id', $this->programacaoId)
        ->orderBy('numero_op')
        ->get();
}
```

**Por que funciona:** o guard `$registros->isEmpty()` previne re-execução em visitas subsequentes. O calculator usa `updateOrCreate` — é idempotente.

**Comportamento após o fix:**
- **Sem CODI:** screen mostra dados planejados com status `pendente`
- **Com CODI sincronizado:** screen mostra realizado × planejado com OEE calculado
- **Programação sem ResultadoSequencia:** `findOrFail` lança no calculator → capturado por `catch` → `report()` → tela fica vazia

## PainelPrincipal — OEE em Tempo Real

**Arquivo:** `app/Livewire/Dashboard/PainelPrincipal.php`

**`carregarOeeTempoReal()`** — para cada linha com programação `confirmada`:
1. Mapeia `linha.codigo` → nome recurso CODI (padrão `LN{n}` → `LINHA {n}`)
2. Busca `CodiPerformance` para obter `codigo_recurso`
3. Consulta `CodiEvento::whereIn('ordem_producao', $opNums)->where('tipo_evento', 'PRODUCAO')->selectRaw(...)->groupBy('ordem_producao')`
4. Para cada item: determina se OP foi concluída verificando se o recurso CODI migrou para outra OP após o último evento desta OP

**`sincronizarEAtualizar()`:** chama `Artisan::call('codi:sincronizar', ['--tipo' => 'todos'])` de forma **síncrona** (não queued) antes de re-renderizar o dashboard.

**`abrirEventosOp(string $numeroOp, string $descricao)`:** abre modal com eventos CODI da OP, filtra pausas de tipo "Intervalo", inclui setup previsto da MatrizSetup para comparação.

## CodiSyncService — Sincronização

**Arquivo:** `app/Services/Codi/CodiSyncService.php`

- `sincronizarPerformance()` — endpoint `/performance`, ~422 registros de referência
- `sincronizarEventos(dataInicio, dataFim)` — itera dia a dia, 1 requisição/dia
  - Após sincronizar, **NÃO recalcula automaticamente** `CodiEficiencia` — isso é responsabilidade do `EficienciaCalculator` (chamado via artisan ou lazy init)

**Chave sintética para eventos sem `codigoEvento`:**
```php
$codigoEvento = $item['codigoEvento']
    ?? ($item['inicio'] . '|' . $recurso . '|' . ($item['estado'] ?? 'X'));
```

## Padrões de Implementação

- `declare(strict_types=1)` em todos arquivos PHP
- `EficienciaCalculator` NÃO é injetado no Livewire — usar `app(EficienciaCalculator::class)` dentro dos métodos
- Qualquer novo cálculo de eficiência deve usar `updateOrCreate` com chave `(programacao_id, numero_op)`
- Novos campos em `codi_eficiencia` requerem migration (acionar agente `database`)
- `report($e)` em catches silenciosos — nunca blackhole de exceções

## Comunicação com o Orquestrador

Ao finalizar, reporte:
1. **Fórmulas alteradas** — mudança nos thresholds ou pesos afeta todos os `codi_eficiencia` existentes (pode ser necessário re-calcular)
2. **Campos novos em `codi_eficiencia`** — acionar agente `database` para migration e agente `mrp` se usar dados do ResultadoSequencia
3. **Alterações no `PainelDesempenho`** — verificar se `$ops` ainda contém todos os campos que a view espera
4. **Testes** — `EficienciaCalculator` tem lógica crítica de classificação; acionar agente `tester`
