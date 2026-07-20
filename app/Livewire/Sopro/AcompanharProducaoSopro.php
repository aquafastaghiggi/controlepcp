<?php

declare(strict_types=1);

namespace App\Livewire\Sopro;

use App\Models\Codi\CodiEficienciaSopro;
use App\Models\Codi\CodiEvento;
use App\Models\Codi\CodiSincronizacaoLog;
use App\Models\ItemProgramacaoSopro;
use App\Models\Maquina;
use App\Models\ProgramacaoSopro;
use App\Models\ResultadoSequenciaSopro;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class AcompanharProducaoSopro extends Component
{
    private const CODI_BASE = 'http://192.168.8.246:8080';
    private const CODI_USER = 'Aghiggi';
    private const CODI_PASS = '@Ag0351@';

    public array  $kpis           = [];
    public array  $maquinas       = [];
    public string $ultimoSync     = 'nunca';
    public string $syncTimestamp  = '';

    public string $motivoMaquina = '';
    public string $motivoPeriodo = 'hoje';
    public string $perfPeriodo   = 'hoje';

    public function mount(): void
    {
        $this->carregarDados();
    }

    public function refresh(): void
    {
        $this->carregarDados();
    }

    public function render(): \Illuminate\View\View
    {
        return view('livewire.sopro.acompanhar-producao-sopro');
    }

    public function carregarDados(): void
    {
        $this->carregarMaquinas();
        $this->calcularKpis();

        $sync = CodiSincronizacaoLog::where('status', 'sucesso')->orderByDesc('created_at')->first();
        if ($sync) {
            $this->ultimoSync    = Carbon::parse($sync->created_at)->locale('pt_BR')->diffForHumans();
            $this->syncTimestamp = Carbon::parse($sync->created_at)->toISOString();
        } else {
            $this->ultimoSync    = 'nunca';
            $this->syncTimestamp = '';
        }
    }

    // ─── KPIs globais ───────────────────────────────────────────────────────

    private function calcularKpis(): void
    {
        $inicioDia = Carbon::today()->setHour(6)->setMinute(30)->setSecond(0);
        if (now()->lt($inicioDia)) {
            $inicioDia = $inicioDia->subDay();
        }
        $inicioOntem = $inicioDia->copy()->subDay();
        $fimOntem    = $inicioDia->copy();

        $recursos = DB::table('maquinas')->whereNotNull('codigo_recurso')->where('ativo', true)->pluck('codigo_recurso');

        $produzidoHoje = (int) CodiEvento::where('tipo_evento', 'PRODUCAO')
            ->where('inicio_evento', '>=', $inicioDia)
            ->whereIn('codigo_recurso', $recursos)
            ->sum('quantidade');

        $produzidoOntem = (int) CodiEvento::where('tipo_evento', 'PRODUCAO')
            ->where('inicio_evento', '>=', $inicioOntem)
            ->where('inicio_evento', '<', $fimOntem)
            ->whereIn('codigo_recurso', $recursos)
            ->sum('quantidade');

        $hoje = today()->format('Y-m-d');
        $cacheKey = 'previsto_hoje_sopro_' . $hoje;

        // Fonte primária: kpis_diarios (imutável durante o dia)
        $previstoHoje = DB::table('kpis_diarios')
            ->where('data', $hoje)
            ->where('modulo', 'sopro')
            ->value('previsto_hoje');

        // Fallback: cache
        if ($previstoHoje === null) {
            $previstoHoje = \Illuminate\Support\Facades\Cache::get($cacheKey);
        }

        // Último recurso: soma das OPs programadas em andamento
        if ($previstoHoje === null) {
            $previstoHoje = 0;
            foreach ($this->maquinas as $m) {
                if ($m['op_atual']['programado'] ?? null) {
                    $previstoHoje += $m['op_atual']['programado'];
                }
            }
            // Persiste para travar o valor
            DB::table('kpis_diarios')->updateOrInsert(
                ['data' => $hoje, 'modulo' => 'sopro'],
                ['previsto_hoje' => $previstoHoje, 'calculado_em' => now(), 'updated_at' => now(), 'created_at' => now()]
            );
            $segundos = \Carbon\Carbon::tomorrow()->startOfDay()->diffInSeconds(now());
            \Illuminate\Support\Facades\Cache::put($cacheKey, $previstoHoje, $segundos);
        }

        $pctHoje = $previstoHoje > 0 ? round($produzidoHoje / $previstoHoje * 100, 1) : null;

        $maquinasAtivas = count(array_filter($this->maquinas, fn ($m) => $m['op_atual'] !== null));
        $emDia    = count(array_filter($this->maquinas, fn ($m) => $m['cor'] === 'green'));
        $atencao  = count(array_filter($this->maquinas, fn ($m) => $m['cor'] === 'yellow'));
        $atrasada = count(array_filter($this->maquinas, fn ($m) => $m['cor'] === 'red'));

        $oeeValues  = array_filter(array_column($this->maquinas, 'oee'), fn ($v) => $v !== null);
        $dispValues = array_filter(array_column($this->maquinas, 'disponibilidade'), fn ($v) => $v !== null);
        $perfValues = array_filter(array_column($this->maquinas, 'performance'), fn ($v) => $v !== null);

        $this->kpis = [
            'maquinas_ativas'    => $maquinasAtivas,
            'maquinas_em_alerta' => $atrasada + $atencao,
            'produzido_hoje'     => $produzidoHoje,
            'produzido_ontem'    => $produzidoOntem,
            'previsto_hoje'      => $previstoHoje,
            'pct_hoje'           => $pctHoje,
            'oee_medio'          => count($oeeValues)  > 0 ? round(array_sum($oeeValues)  / count($oeeValues), 1)  : null,
            'disp_media'         => count($dispValues) > 0 ? round(array_sum($dispValues) / count($dispValues), 1) : null,
            'perf_media'         => count($perfValues) > 0 ? round(array_sum($perfValues) / count($perfValues), 1) : null,
            'qual_media'         => 100.0,
            'situacao_geral'     => [
                'em_dia'    => $emDia,
                'atencao'   => $atencao,
                'atrasadas' => $atrasada,
            ],
        ];
    }

    // ─── Dados por máquina ──────────────────────────────────────────────────

    private function carregarMaquinas(): void
    {
        $maquinasComProgramacao = Maquina::where('ativo', true)->orderBy('codigo')->get();

        $resultado = [];

        $todosCodigosRecurso = $maquinasComProgramacao->pluck('codigo_recurso')->filter()->values()->toArray();
        $cutoff14d = now()->subDays(14)->startOfDay();

        $rawHistorico = CodiEvento::selectRaw('codigo_recurso, tipo_evento, DATE(inicio_evento) AS dia, SUM(quantidade) AS total_qty, SUM(duracao_minutos) AS total_min')
            ->where('inicio_evento', '>=', $cutoff14d)
            ->whereIn('codigo_recurso', $todosCodigosRecurso)
            ->where(function ($q) {
                $q->where('tipo_evento', 'PRODUCAO')
                  ->orWhere(function ($q2) {
                      $q2->where('tipo_evento', 'PARADA')
                         ->where(function ($q3) {
                             $q3->whereRaw("JSON_EXTRACT(dados_raw, '$.parada.nomeParada') IS NULL")
                                ->orWhereRaw("UPPER(JSON_UNQUOTE(JSON_EXTRACT(dados_raw, '$.parada.nomeParada'))) != 'PARADA PROGRAMADA'");
                         })
                         ->where(function ($q3) {
                             $q3->whereRaw("JSON_EXTRACT(dados_raw, '$.parada.tipoParada.nomeTipoParada') IS NULL")
                                ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(dados_raw, '$.parada.tipoParada.nomeTipoParada')) != 'Intervalo'");
                         });
                  });
            })
            ->groupBy('codigo_recurso', 'tipo_evento', DB::raw('DATE(inicio_evento)'))
            ->get();

        $historicoPorRecurso = [];
        foreach ($rawHistorico as $row) {
            $r = $row->codigo_recurso;
            $d = $row->dia;
            if (!isset($historicoPorRecurso[$r][$d])) {
                $historicoPorRecurso[$r][$d] = ['producao_qty' => 0, 'producao_min' => 0, 'parada_min' => 0];
            }
            if ($row->tipo_evento === 'PRODUCAO') {
                $historicoPorRecurso[$r][$d]['producao_qty'] += (float) $row->total_qty;
                $historicoPorRecurso[$r][$d]['producao_min'] += (float) $row->total_min;
            } else {
                $historicoPorRecurso[$r][$d]['parada_min'] += (float) $row->total_min;
            }
        }

        $inicioDiaHoje = Carbon::today()->setHour(6)->setMinute(30)->setSecond(0);
        if (now()->lt($inicioDiaHoje)) {
            $inicioDiaHoje = $inicioDiaHoje->subDay();
        }

        foreach ($maquinasComProgramacao as $maquina) {
            $codigoRecurso = $maquina->codigo_recurso;

            // ── Histórico 7d ──
            $diasHistorico = $historicoPorRecurso[$codigoRecurso] ?? [];
            $seteAtras     = now()->subDays(7)->toDateString();
            $semanaAtual    = array_filter($diasHistorico, fn ($d, $key) => $key > $seteAtras, ARRAY_FILTER_USE_BOTH);
            $semanaAnterior = array_filter($diasHistorico, fn ($d, $key) => $key <= $seteAtras, ARRAY_FILTER_USE_BOTH);

            $dadosGrafico = [];
            for ($i = 6; $i >= 0; $i--) {
                $dia   = now()->subDays($i)->toDateString();
                $entry = $semanaAtual[$dia] ?? ['producao_qty' => 0, 'producao_min' => 0, 'parada_min' => 0];
                $totalMin       = $entry['producao_min'] + $entry['parada_min'];
                $disponibilidade = $totalMin > 0 ? round($entry['producao_min'] / $totalMin * 100, 1) : null;
                $dadosGrafico[] = [
                    'data'            => $dia,
                    'producao_qty'    => (int) $entry['producao_qty'],
                    'paradas_min'     => (int) $entry['parada_min'],
                    'disponibilidade' => $disponibilidade,
                ];
            }

            $producaoTotal7d        = array_sum(array_column($dadosGrafico, 'producao_qty'));
            $horasParadas7d         = array_sum(array_column($dadosGrafico, 'paradas_min')) / 60;
            $dispValues             = array_filter(array_column($dadosGrafico, 'disponibilidade'), fn ($v) => $v !== null);
            $disponibilidadeMedia7d = count($dispValues) > 0 ? round(array_sum($dispValues) / count($dispValues), 1) : null;

            $qtyAtual    = array_sum(array_column(array_values($semanaAtual), 'producao_qty'));
            $minAtual    = array_sum(array_column(array_values($semanaAtual), 'producao_min'));
            $qtyAnterior = array_sum(array_column(array_values($semanaAnterior), 'producao_qty'));
            $minAnterior = array_sum(array_column(array_values($semanaAnterior), 'producao_min'));

            $ritmoAtual    = $minAtual > 0    ? round($qtyAtual / $minAtual * 60, 1)    : null;
            $ritmoAnterior = $minAnterior > 0 ? round($qtyAnterior / $minAnterior * 60, 1) : null;

            $tendencia = 'stable';
            if ($ritmoAtual !== null && $ritmoAnterior !== null && $ritmoAnterior > 0) {
                $variacao  = ($ritmoAtual - $ritmoAnterior) / $ritmoAnterior;
                $tendencia = match (true) {
                    $variacao >= 0.05  => 'up',
                    $variacao <= -0.05 => 'down',
                    default            => 'stable',
                };
            }

            // ── Programação confirmada mais recente ──
            $programacao = ProgramacaoSopro::where('maquina_id', $maquina->id)
                ->where('status', 'confirmada')
                ->with(['itens' => fn ($q) => $q->orderBy('sequencia')])
                ->orderByDesc('created_at')
                ->first();

            // ── Parada em aberto ──
            $paradaAberta = DB::table('codi_eventos')
                ->where('codigo_recurso', $codigoRecurso)
                ->where('tipo_evento', 'PARADA')
                ->whereNull('fim_evento')
                ->orderByDesc('inicio_evento')
                ->first();

            $paradaInfo = null;
            if ($paradaAberta) {
                $raw = is_array($paradaAberta->dados_raw) ? $paradaAberta->dados_raw : json_decode($paradaAberta->dados_raw ?? '{}', true);
                $paradaInfo = [
                    'minutos' => max(0, (int) Carbon::parse($paradaAberta->inicio_evento)->diffInMinutes(now())),
                    'nome'    => $raw['parada']['nomeParada'] ?? null,
                ];
            }

            $tempoParadoHojeMin = (int) DB::table('codi_eventos')
                ->where('codigo_recurso', $codigoRecurso)
                ->where('tipo_evento', 'PARADA')
                ->where('inicio_evento', '>=', $inicioDiaHoje)
                ->where(function ($q) {
                    $q->whereRaw("JSON_EXTRACT(dados_raw, '$.parada.nomeParada') IS NULL")
                      ->orWhereRaw("UPPER(JSON_UNQUOTE(JSON_EXTRACT(dados_raw, '$.parada.nomeParada'))) != 'PARADA PROGRAMADA'");
                })
                ->sum('duracao_minutos');

            if (!$programacao) {
                $resultado[] = $this->montarCardSemProgramacao($maquina, $codigoRecurso, $paradaInfo, $tempoParadoHojeMin, $producaoTotal7d, $disponibilidadeMedia7d, $horasParadas7d, $tendencia, $ritmoAtual, $ritmoAnterior, $dadosGrafico);
                continue;
            }

            $opNums = $programacao->itens->pluck('numero_op')->filter()->toArray();

            $realizadoPorOp = [];
            if (!empty($opNums)) {
                $realizadoPorOp = DB::table('codi_eventos')
                    ->whereIn('ordem_producao', $opNums)
                    ->where('tipo_evento', 'PRODUCAO')
                    ->selectRaw('ordem_producao, SUM(quantidade) as total')
                    ->groupBy('ordem_producao')
                    ->pluck('total', 'ordem_producao')
                    ->toArray();
            }

            $eficienciaPorOp = CodiEficienciaSopro::where('programacao_sopro_id', $programacao->id)
                ->get()
                ->keyBy('numero_op');

            // Identifica OP atual: a primeira não concluída, na sequência
            $opAtualItem = null;
            $totalOps    = $programacao->itens->count();
            $opsFinalizadas = 0;

            foreach ($programacao->itens as $item) {
                $realizado  = (float) ($realizadoPorOp[$item->numero_op] ?? 0);
                // itens_programacao_sopro.quantidade vem do Colemar em milheiros — ×1000
                // pra comparar com $realizado (produção real do CODI, em unidade).
                $programado = (float) $item->quantidade * 1000;
                if ($realizado >= $programado && $programado > 0) {
                    $opsFinalizadas++;
                } elseif ($realizado > 0 && $opAtualItem === null) {
                    $opAtualItem = $item;
                }
            }
            if ($opAtualItem === null) {
                foreach ($programacao->itens as $item) {
                    $realizado = (float) ($realizadoPorOp[$item->numero_op] ?? 0);
                    if ($realizado <= 0) {
                        $opAtualItem = $item;
                        break;
                    }
                }
            }

            // ── Fallback: se a OP rodando agora no CODI não é a op_atual prevista, marca como divergente ──
            $opCodiAtual = DB::table('codi_eventos')
                ->where('codigo_recurso', $codigoRecurso)
                ->where('tipo_evento', 'PRODUCAO')
                ->where('inicio_evento', '>=', now()->subHours(24))
                ->selectRaw('ordem_producao, codigo_item, MAX(inicio_evento) as ultimo_evento')
                ->groupBy('ordem_producao', 'codigo_item')
                ->orderByDesc('ultimo_evento')
                ->first();

            $divergente = false;
            if ($opCodiAtual && (!$opAtualItem || $opCodiAtual->ordem_producao !== $opAtualItem->numero_op)) {
                // A OP rodando no CODI não está na programação como "atual" — buscar dados via CODI
                $dadosOp = $this->buscarDadosOpCodi($opCodiAtual->ordem_producao);
                $opAtualItem = (object) [
                    'numero_op'         => $opCodiAtual->ordem_producao,
                    'sku'               => $opCodiAtual->codigo_item,
                    'descricao_produto' => $dadosOp['descricao'] ?? $opCodiAtual->codigo_item,
                    'quantidade'        => $dadosOp['quantidade'] ?? 0,
                    'id'                => null,
                ];
                $realizadoPorOp[$opCodiAtual->ordem_producao] = (float) DB::table('codi_eventos')
                    ->where('ordem_producao', $opCodiAtual->ordem_producao)
                    ->where('tipo_evento', 'PRODUCAO')
                    ->sum('quantidade');
                $divergente = true;
            }

            $opAtualArray = null;
            $cor = 'gray'; $status = 'Aguardando';

            if ($opAtualItem !== null) {
                $realizado  = (float) ($realizadoPorOp[$opAtualItem->numero_op] ?? 0);
                // itens_programacao_sopro.quantidade vem em milheiros — ×1000. Já a OP
                // "divergente" (stdClass sintético acima) busca quantidade direto da API
                // do CODI, que já está em unidade — não multiplicar nesse caso.
                $programado = (float) $opAtualItem->quantidade;
                if ($opAtualItem instanceof \App\Models\ItemProgramacaoSopro) {
                    $programado *= 1000;
                }
                $pct        = $programado > 0 ? round(($realizado / $programado) * 100, 1) : null;
                $faltam     = $programado > 0 ? max(0, $programado - $realizado) : null;

                $ultimosEventos = DB::table('codi_eventos')
                    ->where('ordem_producao', $opAtualItem->numero_op)
                    ->where('tipo_evento', 'PRODUCAO')
                    ->orderByDesc('inicio_evento')
                    ->limit(5)
                    ->get(['duracao_minutos', 'quantidade']);

                $ritmoMin = 0.0;
                if ($ultimosEventos->count() > 0) {
                    $totalDuracao = (float) $ultimosEventos->sum('duracao_minutos');
                    $totalQtd     = (float) $ultimosEventos->sum('quantidade');
                    if ($totalDuracao > 0) $ritmoMin = $totalQtd / $totalDuracao;
                }
                $ritmoCxH = (int) round($ritmoMin * 60);

                $etaFormatada = null;
                if ($pct !== null && $pct >= 100) {
                    $etaFormatada = 'Concluída';
                } elseif ($ritmoMin > 0 && $faltam !== null && $faltam > 0) {
                    $etaDatetime  = now()->addMinutes((int) ceil($faltam / $ritmoMin));
                    $etaFormatada = $etaDatetime->isToday() ? $etaDatetime->format('H:i') : $etaDatetime->format('H:i') . ' de ' . $etaDatetime->format('d/m');
                }

                $eventosHoje = DB::table('codi_eventos')
                    ->where('codigo_recurso', $codigoRecurso)
                    ->where('inicio_evento', '>=', $inicioDiaHoje)
                    ->whereIn('tipo_evento', ['PRODUCAO', 'PARADA'])
                    ->get();

                $minProducao = 0.0; $minParada = 0.0;
                foreach ($eventosHoje as $ev) {
                    $fim = $ev->fim_evento ?? now();
                    $minutos = max(0, Carbon::parse($ev->inicio_evento)->diffInMinutes($fim));
                    if ($ev->tipo_evento === 'PRODUCAO') $minProducao += $minutos; else $minParada += $minutos;
                }
                $minTotal = $minProducao + $minParada;
                $disponibilidade = $minTotal > 0 ? round($minProducao / $minTotal * 100, 1) : null;

                $eficiencia = $eficienciaPorOp[$opAtualItem->numero_op] ?? null;

                // Fallback de performance: ritmo real vs taxa nominal do produto.
                // Evita valores absurdos quando não há programação (pct indefinido ou > 100%).
                // $opAtualItem->taxa_por_hora não existe (nem no model ItemProgramacaoSopro,
                // nem no stdClass do fallback "divergente") — a taxa mora em frascos.taxa_por_hora,
                // buscada aqui via sku.
                if ($eficiencia?->performance_tempo !== null) {
                    $performance = $eficiencia->performance_tempo;
                } elseif ($minProducao > 0 && !empty($opAtualItem->sku)) {
                    $taxaPorHora = (float) DB::table('frascos')->where('sku', $opAtualItem->sku)->value('taxa_por_hora');
                    if ($taxaPorHora > 0) {
                        // Ritmo real (cx/h de produção pura) vs taxa nominal
                        $produzidoOp = DB::table('codi_eventos')
                            ->where('ordem_producao', $opAtualItem->numero_op)
                            ->where('tipo_evento', 'PRODUCAO')
                            ->sum('quantidade');
                        $ritmoReal = $produzidoOp / ($minProducao / 60);
                        $performance = min(100, round($ritmoReal / $taxaPorHora * 100, 1));
                    } else {
                        $performance = $pct; // sem taxa cadastrada — último fallback
                    }
                } else {
                    $performance = $pct !== null ? min(100, $pct) : null;
                }

                $oee = $eficiencia?->oee ?? (($disponibilidade !== null && $performance !== null) ? round($disponibilidade / 100 * $performance / 100 * 100, 1) : null);

                if ($divergente) {
                    $cor = 'red'; $status = 'Divergente';
                } elseif ($pct !== null && $pct > 100) {
                    $cor = 'red'; $status = 'Excesso';
                } elseif ($pct !== null && $pct >= 100) {
                    $cor = 'green'; $status = 'Concluída';
                } elseif ($paradaInfo !== null) {
                    $cor = 'red'; $status = 'Parada';
                } elseif ($realizado > 0) {
                    $cor = 'green'; $status = 'Em dia';
                } else {
                    $cor = 'gray'; $status = 'Aguardando';
                }

                $opAtualArray = [
                    'numero_op'     => $opAtualItem->numero_op,
                    'sku'           => $opAtualItem->sku,
                    'descricao'     => $opAtualItem->descricao_produto,
                    'programado'    => $programado ?: null,
                    'realizado'     => $realizado,
                    'pct'           => $pct,
                    'faltam'        => $faltam,
                    'ritmo_cxh'     => $ritmoCxH,
                    'eta_formatada' => $etaFormatada,
                    'divergente'    => $divergente,
                ];
            }

            $resultado[] = [
                'id'                    => $maquina->id,
                'codigo'                => $maquina->codigo,
                'nome'                  => $maquina->nome,
                'codigo_recurso'        => $codigoRecurso,
                'op_atual'              => $opAtualArray,
                'parada_aberta'         => $paradaInfo,
                'tempo_parado_hoje_min' => $tempoParadoHojeMin,
                'cor'                   => $cor,
                'status'                => $status,
                'oee'                   => $oee ?? null,
                'disponibilidade'       => $disponibilidade ?? null,
                'performance'           => $performance ?? null,
                'ultimo_evento'         => $opCodiAtual->ultimo_evento ?? null,
                'historico_7d'          => [
                    'producao_total'        => $producaoTotal7d,
                    'disponibilidade_media' => $disponibilidadeMedia7d,
                    'horas_paradas'         => round($horasParadas7d, 1),
                    'tendencia'             => ['direcao' => $tendencia, 'ritmo_atual' => $ritmoAtual, 'ritmo_anterior' => $ritmoAnterior],
                    'dados_grafico'         => $dadosGrafico,
                ],
            ];
        }

        $this->maquinas = $resultado;
    }

    private function montarCardSemProgramacao($maquina, $codigoRecurso, $paradaInfo, $tempoParadoHojeMin, $producaoTotal7d, $disponibilidadeMedia7d, $horasParadas7d, $tendencia, $ritmoAtual, $ritmoAnterior, $dadosGrafico): array
    {
        return [
            'id'                    => $maquina->id,
            'codigo'                => $maquina->codigo,
            'nome'                  => $maquina->nome,
            'codigo_recurso'        => $codigoRecurso,
            'op_atual'              => null,
            'parada_aberta'         => $paradaInfo,
            'tempo_parado_hoje_min' => $tempoParadoHojeMin,
            'cor'                   => $paradaInfo ? 'red' : 'gray',
            'status'                => $paradaInfo ? 'Parada' : 'Sem programação',
            'oee'                   => null,
            'disponibilidade'       => null,
            'performance'           => null,
            'ultimo_evento'         => null,
            'historico_7d'          => [
                'producao_total'        => $producaoTotal7d,
                'disponibilidade_media' => $disponibilidadeMedia7d,
                'horas_paradas'         => round($horasParadas7d, 1),
                'tendencia'             => ['direcao' => $tendencia, 'ritmo_atual' => $ritmoAtual, 'ritmo_anterior' => $ritmoAnterior],
                'dados_grafico'         => $dadosGrafico,
            ],
        ];
    }

    private function buscarDadosOpCodi(string $numeroOp): ?array
    {
        $row = DB::table('codi_eventos')
            ->where('ordem_producao', $numeroOp)
            ->whereNotNull('dados_raw')
            ->first(['dados_raw']);

        if (!$row) return null;

        $raw = is_array($row->dados_raw) ? $row->dados_raw : json_decode($row->dados_raw, true);
        $codigoOrdemProducao = $raw['ordens'][0]['ordemProducao']['codigoOrdemProducao'] ?? null;

        if (!$codigoOrdemProducao) return null;

        $ch = curl_init(self::CODI_BASE . '/action/ger/webservice/rest/ordemProducao/' . $codigoOrdemProducao);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_USERPWD, self::CODI_USER . ':' . self::CODI_PASS);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($code !== 200 || !$resp) return null;

        $dados = json_decode($resp, true);

        return [
            'quantidade' => $dados['quantidade'] ?? null,
            'descricao'  => $dados['item']['nomeItem'] ?? null,
            'status'     => $dados['status'] ?? null,
        ];
    }

    public function getMotivosParada(): array
    {
        $codigosRecurso = DB::table('maquinas')->whereNotNull('codigo_recurso')->where('ativo', true)->pluck('codigo_recurso');
        $diasMap = ['hoje' => 0, '3d' => 3, '7d' => 7, '15d' => 15];
        $dias = $diasMap[$this->motivoPeriodo] ?? 0;

        $inicioDia = Carbon::today()->setHour(6)->setMinute(30)->setSecond(0);
        if (now()->lt($inicioDia)) $inicioDia = $inicioDia->subDay();

        $query = DB::table('codi_eventos')
            ->where('tipo_evento', 'PARADA')
            ->whereIn('codigo_recurso', $codigosRecurso)
            ->whereRaw('TIMESTAMPDIFF(MINUTE, inicio_evento, IFNULL(fim_evento, NOW())) < 600')
            ->whereRaw('JSON_EXTRACT(dados_raw, "$.parada.nomeParada") IS NOT NULL')
            ->whereRaw('JSON_UNQUOTE(JSON_EXTRACT(dados_raw, "$.parada.tipoParada.nomeTipoParada")) != "Intervalo"')
            ->whereRaw('JSON_UNQUOTE(JSON_EXTRACT(dados_raw, "$.parada.nomeParada")) != "PARADA PROGRAMADA"');

        if ($dias === 0) {
            $query->where('inicio_evento', '>=', $inicioDia);
        } else {
            $query->where('inicio_evento', '>=', $inicioDia->copy()->subDays($dias));
        }

        return $query
            ->selectRaw('JSON_UNQUOTE(JSON_EXTRACT(dados_raw, "$.parada.nomeParada")) as motivo, COUNT(*) as ocorrencias, SUM(TIMESTAMPDIFF(MINUTE, inicio_evento, IFNULL(fim_evento, NOW()))) as total_min')
            ->groupBy('motivo')
            ->orderByDesc('total_min')
            ->limit(3)
            ->get()
            ->toArray();
    }

    public function setMotivoMaquina(string $maquina): void { $this->motivoMaquina = $maquina; }
    public function setMotivoPeriodo(string $periodo): void { $this->motivoPeriodo = $periodo; }
    public function setPerfPeriodo(string $periodo): void { $this->perfPeriodo = $periodo; }
}
