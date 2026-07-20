<?php

declare(strict_types=1);

namespace App\Livewire\Dashboard;

use App\Models\Codi\CodiEficiencia;
use App\Models\Codi\CodiEvento;
use App\Models\Codi\CodiPerformance;
use App\Models\Codi\CodiSincronizacaoLog;
use App\Models\ItemProgramacao;
use App\Models\MatrizSetup;
use App\Models\Produto;
use App\Models\Programacao;
use App\Models\ResultadoSequencia;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class AcompanharProducao extends Component
{
    public array  $kpis          = [];
    public array  $linhas        = [];
    public string $ultimoSync    = 'nunca';
    public string $syncTimestamp = '';  // ISO timestamp for JS countdown

    public string $motivoLinha   = '';
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
        return view('livewire.dashboard.acompanhar-producao');
    }

    // -------------------------------------------------------------------------
    // Public entry point
    // -------------------------------------------------------------------------

    public function carregarDados(): void
    {
        $this->calcularKpis();
        $this->carregarLinhas();

        // Derived KPIs that depend on $this->linhas being populated
        $this->kpis['linhas_em_alerta'] = count(array_filter(
            $this->linhas,
            fn ($l) => in_array($l['cor'], ['red', 'yellow'])
        ));

        // Situação geral baseada no estado de scheduling de cada linha (cor calculada em carregarLinhas).
        // Antes: lia codi_eficiencia.status (status_alerta CODI) da op_atual — 36 de 46 OPs são
        // 'pendente' (eficiência não calculada), tornando a maioria das linhas invisível no card.
        $statusCounts = ['em_dia' => 0, 'atencao' => 0, 'atrasadas' => 0, 'sem_dados' => 0];
        foreach ($this->linhas as $linha) {
            $cor = $linha['cor'] ?? 'gray';
            if ($cor === 'green') {
                $statusCounts['em_dia']++;
            } elseif ($cor === 'yellow') {
                $statusCounts['atencao']++;
            } elseif ($cor === 'red') {
                $statusCounts['atrasadas']++;
            } else {
                $statusCounts['sem_dados']++;
            }
        }
        $this->kpis['situacao_geral'] = $statusCounts;

        // Item 4 Rodada 3: lines that have inicio_real data — on time vs. late start
        $linhasComInicio = array_filter(
            $this->linhas,
            fn ($l) => isset($l['op_atual']['atraso_inicio_min']) && $l['op_atual']['atraso_inicio_min'] !== null
        );
        $this->kpis['total_com_inicio_real'] = count($linhasComInicio);
        $this->kpis['iniciaram_no_prazo']    = count(array_filter($linhasComInicio, fn ($l) => $l['op_atual']['atraso_inicio_min'] <= 0));
        $this->kpis['iniciaram_atrasado']    = count(array_filter($linhasComInicio, fn ($l) => $l['op_atual']['atraso_inicio_min'] > 0));

        $sync = CodiSincronizacaoLog::where('status', 'sucesso')
            ->orderByDesc('created_at')
            ->first();

        if ($sync) {
            $this->ultimoSync    = Carbon::parse($sync->created_at)->locale('pt_BR')->isoFormat('HH:mm');
            $this->syncTimestamp = Carbon::parse($sync->created_at)->toISOString();
        } else {
            $this->ultimoSync    = 'nunca';
            $this->syncTimestamp = '';
        }
    }

    // -------------------------------------------------------------------------
    // KPIs globais
    // -------------------------------------------------------------------------

    private function calcularKpis(): void
    {
        // Linhas com pelo menos uma programação confirmada
        $linhasAtivas = \App\Models\Linha::whereHas('programacoes', function ($q): void {
            $q->where('status', 'confirmada');
        })->count();

        // OPs com eventos hoje mas ainda não concluídas
        $opsComEventosHoje = CodiEvento::whereDate('inicio_evento', today())
            ->distinct()
            ->pluck('ordem_producao')
            ->filter()
            ->values();

        // Pre-load: MAX(fim_evento) and MIN(codigo_recurso) per OP — avoids N+1 inside loop
        $opNums = $opsComEventosHoje->toArray();

        $metasPorOp = CodiEvento::whereIn('ordem_producao', $opNums)
            ->selectRaw('ordem_producao, MAX(fim_evento) as ultimo_fim, MIN(codigo_recurso) as codigo_recurso')
            ->groupBy('ordem_producao')
            ->get()
            ->keyBy('ordem_producao');

        // Bulk migration check: load all events for these recursos that could signal migration
        $metasList    = $metasPorOp->values()->filter(fn ($m) => $m->codigo_recurso && $m->ultimo_fim);
        $todosRecursos = $metasList->pluck('codigo_recurso')->unique()->values()->toArray();
        $minFimGlobal  = $metasList->min('ultimo_fim');

        $eventosMigracao = collect();
        if (!empty($todosRecursos) && $minFimGlobal) {
            $eventosMigracao = CodiEvento::whereIn('codigo_recurso', $todosRecursos)
                ->where('tipo_evento', 'PRODUCAO')
                ->where('inicio_evento', '>=', $minFimGlobal)
                ->select(['codigo_recurso', 'ordem_producao', 'inicio_evento'])
                ->get();
        }

        // Determine in PHP which OPs had their recurso migrate to a different OP
        $recursosFimPairs = $metasList->map(fn ($m) => [
            'codigo_recurso' => $m->codigo_recurso,
            'ordem_producao' => $m->ordem_producao,
            'ultimo_fim'     => $m->ultimo_fim,
        ])->values();

        $migradosSet = collect();
        foreach ($recursosFimPairs as $pair) {
            $migrou = $eventosMigracao->contains(function ($e) use ($pair) {
                return $e->codigo_recurso === $pair['codigo_recurso']
                    && $e->ordem_producao !== $pair['ordem_producao']
                    && $e->inicio_evento >= $pair['ultimo_fim'];
            });
            if ($migrou) {
                $migradosSet->push($pair['ordem_producao']);
            }
        }

        $opsEmAndamento = 0;
        foreach ($opsComEventosHoje as $opNum) {
            // Uma OP está "em andamento" se não houve migração de recurso para outra OP depois dela
            $meta = $metasPorOp[$opNum] ?? null;
            if (!$meta?->ultimo_fim) {
                $opsEmAndamento++;
                continue;
            }
            if (!$migradosSet->contains($opNum)) {
                $opsEmAndamento++;
            }
        }

        // OEE médio do dia (desde 06:00) — OP mais recente por linha
        $inicioDia = \Carbon\Carbon::today()->setHour(6)->setMinute(0)->setSecond(0);

        // Linhas cujo último evento é uma Parada Programada em andamento — excluídas
        // do OEE/disponibilidade/performance médios, já que não estão produzindo
        $linhasEmParadaProgramada = \Illuminate\Support\Facades\DB::table('codi_eventos as ce')
            ->join('linhas as l', 'l.codigo_recurso', '=', 'ce.codigo_recurso')
            ->whereIn('ce.id', function ($sub) {
                $sub->selectRaw('MAX(id)')
                    ->from('codi_eventos')
                    ->groupBy('codigo_recurso');
            })
            ->where('ce.tipo_evento', 'PARADA')
            ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(ce.dados_raw, '$.parada.nomeParada')) LIKE '%PARADA PROGRAMADA%'")
            ->where('l.ativo', true)
            ->pluck('l.id')
            ->toArray();

        // OEE/disponibilidade/performance médios via calcularOeeLinha() — mesma
        // fonte usada pelos mini cards (quantidadeBoasEstimadaRecurso do CODI,
        // em codi_eventos.dados_raw), em vez de codi_eficiencia.performance_tempo
        // (que depende de produtos.taxa_por_hora cadastrado no ControlePCP e pode
        // estar dessincronizado do que o CODI considera taxa nominal).
        $linhasParaOee = \Illuminate\Support\Facades\DB::table('linhas as l')
            ->join('programacoes as p', 'p.linha_id', '=', 'l.id')
            ->where('p.status', 'confirmada')
            ->where('l.ativo', true)
            ->whereNotIn('l.id', $linhasEmParadaProgramada)
            ->select('l.id', 'l.codigo_recurso')
            ->distinct()
            ->get();

        $oeeValores  = [];
        $dispValores = [];
        $perfValores = [];

        foreach ($linhasParaOee as $linhaOee) {
            if (!$linhaOee->codigo_recurso) continue;

            $opNums = \Illuminate\Support\Facades\DB::table('itens_programacao as ip')
                ->join('programacoes as p', 'p.id', '=', 'ip.programacao_id')
                ->where('p.linha_id', $linhaOee->id)
                ->where('p.status', 'confirmada')
                ->pluck('ip.numero_op')
                ->toArray();

            $oee = $this->calcularOeeLinha($linhaOee->codigo_recurso, $opNums);

            if ($oee['oee'] !== null) {
                $oeeValores[]  = $oee['oee'];
                $dispValores[] = $oee['disponibilidade'];
                $perfValores[] = $oee['performance'];
            }
        }

        $oeeMedio  = count($oeeValores)  > 0 ? array_sum($oeeValores)  / count($oeeValores)  : null;
        $dispMedia = count($dispValores) > 0 ? array_sum($dispValores) / count($dispValores) : null;
        $perfMedia = count($perfValores) > 0 ? array_sum($perfValores) / count($perfValores) : null;

        $oeeMedio  = $oeeMedio  !== null ? round((float) $oeeMedio,  1) : null;
        $dispMedia = $dispMedia !== null ? round((float) $dispMedia, 1) : null;
        $perfMedia = $perfMedia !== null ? round((float) $perfMedia, 1) : null;

        // ─── OPs em programações confirmadas ─────────────────────────────────────
        // Fonte única para filtrar realizado — garante que previsto e realizado
        // usam o mesmo universo de OPs (descarta eventos CODI fora do PCP).
        $opsPCP = ItemProgramacao::query()
            ->join('programacoes', 'programacoes.id', '=', 'itens_programacao.programacao_id')
            ->where('programacoes.status', 'confirmada')
            ->whereNotNull('itens_programacao.numero_op')
            ->pluck('itens_programacao.numero_op');

        // ─── Previsto HOJE ────────────────────────────────────────────────────────
        // OPs que terminam até 22:00 → saldo completo.
        // OPs multi-dia → proporcional às horas que cabem na janela 06:00–22:00
        // (taxa_por_hora × eficiencia × horas_hoje), limitado ao saldo restante.
        $inicioDia  = \Carbon\Carbon::today()->setHour(6)->setMinute(0)->setSecond(0);
        $fimDiaUtil = \Carbon\Carbon::tomorrow()->setHour(3)->setMinute(0)->setSecond(0);

        // ─── Previsto HOJE (somaOps ao vivo, igual TvStaticController) ──────────
        // Recalculado a cada request — reflete reprogramações/arquivamentos do
        // dia imediatamente, sem depender de kpis_diarios/Cache desatualizados.
        $calendarioService = app(\App\Services\CalendarioService::class);

        // Linhas em Parada Programada (excluir do previsto)
        $linhasEmParadaProgramada = DB::table('codi_eventos as ce')
            ->join('linhas as l', 'l.codigo_recurso', '=', 'ce.codigo_recurso')
            ->whereIn('ce.id', function ($sub) {
                $sub->selectRaw('MAX(id)')->from('codi_eventos')->groupBy('codigo_recurso');
            })
            ->where('ce.tipo_evento', 'PARADA')
            ->whereRaw("UPPER(JSON_UNQUOTE(JSON_EXTRACT(ce.dados_raw, '$.parada.nomeParada'))) LIKE '%PARADA PROGRAMADA%'")
            ->where('l.ativo', true)
            ->pluck('l.id')
            ->toArray();

        // somaOps proporcional (igual TV)
        $previstoHoje = 0;
        $programacoes = DB::table('programacoes as p')
            ->join('linhas as l', 'l.id', '=', 'p.linha_id')
            ->join('calendarios as cal', 'cal.linha_id', '=', 'p.linha_id')
            ->where('p.status', 'confirmada')
            ->where('l.ativo', true)
            ->whereNotIn('p.linha_id', $linhasEmParadaProgramada)
            ->select('p.id', 'p.linha_id', 'p.dias_selecionados', 'p.eficiencia', 'cal.id as calendario_id')
            ->get();

        foreach ($programacoes as $prog) {
            $diasSel = json_decode($prog->dias_selecionados, true);
            $calId = $prog->calendario_id;
            $eficiencia = max(0.0, (float) $prog->eficiencia) / 100;

            $ops = DB::table('itens_programacao as ip')
                ->join('codi_eficiencia as ce', function ($j) use ($prog) {
                    $j->on('ce.numero_op', '=', 'ip.numero_op')
                      ->where('ce.programacao_id', '=', $prog->id);
                })
                ->leftJoin('produtos as prod', 'prod.sku', '=', 'ip.sku')
                ->where('ip.programacao_id', $prog->id)
                ->where('ce.fim_previsto', '>', $inicioDia)
                ->where('ce.inicio_previsto', '<', $fimDiaUtil)
                ->select('ip.quantidade', 'ce.inicio_previsto', 'ce.fim_previsto', 'prod.taxa_por_hora')
                ->get();

            foreach ($ops as $op) {
                $ini = new \DateTimeImmutable($op->inicio_previsto);
                $fim = new \DateTimeImmutable($op->fim_previsto);
                $iniCalc = $ini < new \DateTimeImmutable($inicioDia->toDateTimeString()) ? new \DateTimeImmutable($inicioDia->toDateTimeString()) : $ini;
                $fimCalc = $fim > new \DateTimeImmutable($fimDiaUtil->toDateTimeString()) ? new \DateTimeImmutable($fimDiaUtil->toDateTimeString()) : $fim;
                if ($fimCalc <= $iniCalc) continue;
                $minTotal = $calendarioService->minutosUteisEntre($ini, $fim, $calId, $diasSel);
                $minOverlap = $calendarioService->minutosUteisEntre($iniCalc, $fimCalc, $calId, $diasSel);
                if ($minTotal <= 0) continue;

                $taxaPorHora = (float) ($op->taxa_por_hora ?? 0);
                if ($taxaPorHora > 0) {
                    $prevCxOp = min((int) $op->quantidade, (int) round($taxaPorHora * $eficiencia * $minOverlap / 60));
                } else {
                    $prevCxOp = (int) round($op->quantidade * ($minOverlap / $minTotal));
                }
                $previstoHoje += $prevCxOp;
            }
        }
        $previstoHoje = (int) round($previstoHoje);

        // ─── Realizado: toda produção física do dia por recurso da linha ────────
        $recursosLinhas = DB::table('linhas')
            ->whereNotNull('codigo_recurso')
            ->where('ativo', true)
            ->pluck('codigo_recurso');

        $produzidoHoje = (int) CodiEvento::where('tipo_evento', 'PRODUCAO')
            ->where('inicio_evento', '>=', $inicioDia)
            ->whereIn('codigo_recurso', $recursosLinhas)
            ->sum('quantidade');

        // ─── Ontem: janela produtiva 06:00 ontem → 03:00 hoje ───────────────────
        $inicioOntem = \Carbon\Carbon::yesterday()->setHour(6)->setMinute(0)->setSecond(0);
        $fimOntem    = \Carbon\Carbon::today()->setHour(3)->setMinute(0)->setSecond(0);

        $produzidoOntem = (int) CodiEvento::where('tipo_evento', 'PRODUCAO')
            ->where('inicio_evento', '>=', $inicioOntem)
            ->where('inicio_evento', '<', $fimOntem)
            ->whereIn('codigo_recurso', $recursosLinhas)
            ->sum('quantidade');

        // ─── Previsto ontem — somaOps proporcional, reconstruindo qual programação
        // estava ativa em cada linha durante a janela de ontem (06:00→03:00),
        // igual ao método de previsto_hoje. Cobre reprogramações no meio do dia:
        // soma a confirmada atual (se já existia ontem) + qualquer arquivada
        // durante o período, sem duplicar OP entre as duas.
        $linhasAtivasOntem = DB::table('linhas')->where('ativo', true)->whereNotNull('codigo_recurso')->get(['id', 'codigo_recurso']);

        $previstoOntem = 0;

        foreach ($linhasAtivasOntem as $linha) {
            $progsOntem = DB::table('programacoes as p')
                ->join('calendarios as cal', 'cal.linha_id', '=', 'p.linha_id')
                ->where('p.linha_id', $linha->id)
                ->where(function ($q) use ($inicioOntem, $fimOntem) {
                    $q->where(function ($q2) use ($inicioOntem) {
                        // Confirmada atual que já existia ontem
                        $q2->where('p.status', 'confirmada')
                           ->where('p.updated_at', '<', $inicioOntem->copy()->addDay());
                    })->orWhere(function ($q2) use ($inicioOntem, $fimOntem) {
                        // Arquivada durante o dia de ontem
                        $q2->where('p.status', 'arquivada')
                           ->where('p.arquivada_em', '>=', $inicioOntem)
                           ->where('p.arquivada_em', '<=', $fimOntem);
                    });
                })
                ->select('p.id', 'p.dias_selecionados', 'p.status', 'p.arquivada_em', 'p.updated_at', 'p.eficiencia', 'cal.id as calendario_id')
                ->orderBy('p.id')
                ->get();

            if ($progsOntem->isEmpty()) {
                continue;
            }

            $opsContadas = []; // evitar dupla contagem de mesma OP em programações diferentes

            foreach ($progsOntem as $prog) {
                $diasSel = json_decode($prog->dias_selecionados, true);
                $calId = $prog->calendario_id;
                $eficiencia = max(0.0, (float) $prog->eficiencia) / 100;

                $ops = DB::table('itens_programacao as ip')
                    ->join('codi_eficiencia as ce', function ($j) use ($prog) {
                        $j->on('ce.numero_op', '=', 'ip.numero_op')
                          ->where('ce.programacao_id', '=', $prog->id);
                    })
                    ->leftJoin('produtos as prod', 'prod.sku', '=', 'ip.sku')
                    ->where('ip.programacao_id', $prog->id)
                    ->where('ce.fim_previsto', '>', $inicioOntem)
                    ->where('ce.inicio_previsto', '<', $fimOntem)
                    ->whereNotIn('ip.numero_op', $opsContadas)
                    ->select('ip.numero_op', 'ip.quantidade', 'ce.inicio_previsto', 'ce.fim_previsto', 'prod.taxa_por_hora')
                    ->get();

                foreach ($ops as $op) {
                    $opsContadas[] = $op->numero_op;
                    $ini = new \DateTimeImmutable($op->inicio_previsto);
                    $fim = new \DateTimeImmutable($op->fim_previsto);
                    $iniCalc = $ini < new \DateTimeImmutable($inicioOntem->toDateTimeString()) ? new \DateTimeImmutable($inicioOntem->toDateTimeString()) : $ini;
                    $fimCalc = $fim > new \DateTimeImmutable($fimOntem->toDateTimeString()) ? new \DateTimeImmutable($fimOntem->toDateTimeString()) : $fim;
                    if ($fimCalc <= $iniCalc) {
                        continue;
                    }
                    $minTotal = $calendarioService->minutosUteisEntre($ini, $fim, $calId, $diasSel);
                    $minOverlap = $calendarioService->minutosUteisEntre($iniCalc, $fimCalc, $calId, $diasSel);
                    if ($minTotal <= 0) {
                        continue;
                    }

                    $taxaPorHora = (float) ($op->taxa_por_hora ?? 0);
                    if ($taxaPorHora > 0) {
                        $prevCxOp = min((int) $op->quantidade, (int) round($taxaPorHora * $eficiencia * $minOverlap / 60));
                    } else {
                        $prevCxOp = (int) round($op->quantidade * ($minOverlap / $minTotal));
                    }
                    $previstoOntem += $prevCxOp;
                }
            }
        }
        $previstoOntem = (int) round($previstoOntem);

        // ─── % de realização ─────────────────────────────────────────────────────
        $pctHoje  = $previstoHoje  > 0 ? round($produzidoHoje  / $previstoHoje  * 100, 1) : null;
        $pctOntem = $previstoOntem > 0 ? round($produzidoOntem / $previstoOntem * 100, 1) : null;

        $this->kpis = [
            'linhas_ativas'         => $linhasAtivas,
            'ops_em_andamento'      => $opsEmAndamento,
            'linhas_em_alerta'      => 0, // populated after carregarLinhas()
            'oee_medio'             => $oeeMedio  !== null ? round((float) $oeeMedio,  1) : null,
            'disp_media'            => $dispMedia !== null ? round((float) $dispMedia, 1) : null,
            'perf_media'            => $perfMedia !== null ? round((float) $perfMedia, 1) : null,
            'qual_media'            => 100.0,
            'previsto_hoje'         => $previstoHoje,
            'produzido_hoje'        => $produzidoHoje,
            'pct_hoje'              => $pctHoje,
            'previsto_ontem'        => $previstoOntem,
            'produzido_ontem'       => $produzidoOntem,
            'pct_ontem'             => $pctOntem,
            'iniciaram_no_prazo'    => 0, // populated after carregarLinhas()
            'iniciaram_atrasado'    => 0, // populated after carregarLinhas()
            'total_com_inicio_real' => 0, // populated after carregarLinhas()
        ];
    }

    // -------------------------------------------------------------------------
    // OEE em tempo real por linha
    // -------------------------------------------------------------------------

    /**
     * Calcula Disponibilidade, Performance e OEE do dia a partir dos eventos
     * de codi_eventos.dados_raw, filtrando apenas as OPs passadas como parâmetro.
     *
     * @param string   $codigoRecurso  codigo_recurso da linha no CODI
     * @param string[] $opNums         OPs da programação confirmada desta linha
     * @return array{disponibilidade: float|null, performance: float|null, qualidade: float, oee: float|null}
     */
    private function calcularOeeLinha(string $codigoRecurso, array $opNums): array
    {
        $null = ['disponibilidade' => null, 'performance' => null, 'qualidade' => null, 'oee' => null];

        if (empty($opNums)) {
            return $null;
        }

        $eventos = CodiEvento::where('codigo_recurso', $codigoRecurso)
            ->where('inicio_evento', '>=', \Carbon\Carbon::today()->setHour(6)->setMinute(0)->setSecond(0))
            ->whereIn('ordem_producao', $opNums)
            ->whereIn('tipo_evento', ['PRODUCAO', 'PARADA'])
            ->get();

        if ($eventos->isEmpty()) {
            return $null;
        }

        $minProducao = 0.0;
        $minParada   = 0.0;
        $cxBoas      = 0.0;
        $cxEstimadas = 0.0;

        foreach ($eventos as $evento) {
            // fim_evento pode ser null para evento em aberto — usa agora como referência
            $fim     = $evento->fim_evento ?? now();
            $minutos = max(0.0, (float) $evento->inicio_evento->diffInMinutes($fim));
            $raw     = is_array($evento->dados_raw) ? $evento->dados_raw : [];

            if ($evento->tipo_evento === 'PRODUCAO') {
                $minProducao += $minutos;
                foreach ($raw['ordens'] ?? [] as $ordem) {
                    $cxBoas      += (float) ($ordem['quantidadeBoasRecurso']         ?? 0);
                    $cxEstimadas += (float) ($ordem['quantidadeBoasEstimadaRecurso'] ?? 0);
                }
            } else {
                $nomeParada = $raw['parada']['nomeParada'] ?? '';
                // Não conta Parada Programada como parada operacional no OEE
                if (stripos($nomeParada, 'PARADA PROGRAMADA') === false) {
                    $minParada += $minutos;
                }
            }
        }

        $minTotal = $minProducao + $minParada;

        $disponibilidade = $minTotal > 0
            ? round($minProducao / $minTotal * 100, 1)
            : null;

        $performance = $cxEstimadas > 0
            ? round($cxBoas / $cxEstimadas * 100, 1)
            : null;

        $qualidade = 100.0;

        $oee = ($disponibilidade !== null && $performance !== null)
            ? round($disponibilidade / 100 * $performance / 100 * $qualidade / 100 * 100, 1)
            : null;

        return compact('disponibilidade', 'performance', 'qualidade', 'oee');
    }

    // -------------------------------------------------------------------------
    // Dados por linha
    // -------------------------------------------------------------------------

    private function carregarLinhas(): void
    {
        $linhasComProgramacao = \App\Models\Linha::whereHas('programacoes', function ($q): void {
            $q->where('status', 'confirmada');
        })->get();

        $resultado = [];

        // ── Rodada 2: batch pre-loads por código de recurso ──────────────────────
        // Resolve all codigo_recurso values upfront to avoid N+1 in new queries

        $nomesRecursoTodos = $linhasComProgramacao->map(
            fn ($l) => 'LINHA ' . ltrim(str_replace('LN', '', strtoupper($l->codigo)), '0')
        )->values()->toArray();

        // Full CodiPerformance objects keyed by nome_recurso — eliminates per-line N+1
        $performancePorNome = CodiPerformance::whereIn('nome_recurso', $nomesRecursoTodos)
            ->orderByDesc('sincronizado_em')
            ->get()
            ->unique('nome_recurso')
            ->keyBy('nome_recurso');

        $codigoRecursoPorNome = $performancePorNome->pluck('codigo_recurso', 'nome_recurso')->toArray();
        $todosCodigosRecurso  = array_values(array_filter($codigoRecursoPorNome));

        $paradaAbertaPorRecurso    = collect();
        $tempoParadoHojePorRecurso = [];
        $ultimoEventoPorRecurso    = [];

        if (!empty($todosCodigosRecurso)) {
            // Latest open PARADA per recurso (fim_evento IS NULL)
            $paradaAbertaPorRecurso = CodiEvento::whereIn('codigo_recurso', $todosCodigosRecurso)
                ->where('tipo_evento', 'PARADA')
                ->whereNull('fim_evento')
                ->orderByDesc('inicio_evento')
                ->get()
                ->unique('codigo_recurso')
                ->keyBy('codigo_recurso');

            // Total parada time today per recurso
            // Exclui Intervalo e Parada Programada — mesma lógica do PainelPrincipal::abrirEventosOp()
            $tempoParadoHojePorRecurso = CodiEvento::whereIn('codigo_recurso', $todosCodigosRecurso)
                ->where('tipo_evento', 'PARADA')
                ->whereDate('inicio_evento', today())
                ->where(function ($q): void {
                    $q->whereRaw("JSON_EXTRACT(dados_raw, '$.parada.nomeParada') IS NULL")
                      ->orWhereRaw("UPPER(JSON_UNQUOTE(JSON_EXTRACT(dados_raw, '$.parada.nomeParada'))) != 'PARADA PROGRAMADA'");
                })
                ->where(function ($q): void {
                    $q->whereRaw("JSON_EXTRACT(dados_raw, '$.parada.tipoParada.nomeTipoParada') IS NULL")
                      ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(dados_raw, '$.parada.tipoParada.nomeTipoParada')) != 'Intervalo'");
                })
                ->selectRaw('codigo_recurso, SUM(duracao_minutos) as total_parado')
                ->groupBy('codigo_recurso')
                ->pluck('total_parado', 'codigo_recurso')
                ->toArray();

            // Last event timestamp per recurso — used to detect "sem sinal CODI"
            $ultimoEventoPorRecurso = CodiEvento::whereIn('codigo_recurso', $todosCodigosRecurso)
                ->selectRaw('codigo_recurso, MAX(inicio_evento) as ultimo_inicio')
                ->groupBy('codigo_recurso')
                ->pluck('ultimo_inicio', 'codigo_recurso')
                ->toArray();
        }

        // Full MatrizSetup load — O(1) SKU-pair lookup inside loop; table is small
        $setupMatrix = MatrizSetup::all()
            ->keyBy(fn ($s) => $s->sku_origem . '|' . $s->sku_destino);

        // Last 5 PRODUCAO events per OP — pre-loaded for all active OPs to avoid N+1
        $linhaIds       = $linhasComProgramacao->pluck('id')->toArray();
        $programacaoIds = Programacao::whereIn('linha_id', $linhaIds)
            ->where('status', 'confirmada')
            ->pluck('id')
            ->toArray();

        $ultimosEventosPorOp = collect();
        if (!empty($programacaoIds)) {
            $allConfirmedOpNums = ItemProgramacao::whereIn('programacao_id', $programacaoIds)
                ->pluck('numero_op')
                ->filter()
                ->unique()
                ->values()
                ->toArray();

            if (!empty($allConfirmedOpNums)) {
                // 30-day window is sufficient for rhythm calculation and prevents unbounded history load
                $ultimosEventosPorOp = CodiEvento::whereIn('ordem_producao', $allConfirmedOpNums)
                    ->where('tipo_evento', 'PRODUCAO')
                    ->where('inicio_evento', '>=', now()->subDays(30))
                    ->orderByDesc('inicio_evento')
                    ->get()
                    ->groupBy('ordem_producao')
                    ->map(fn ($events) => $events->take(5));
            }
        }

        // Rodada 3: taxa_por_hora per SKU — for ritmo vs. taxa nominal comparison
        $taxaPorSku = [];
        if (!empty($programacaoIds)) {
            $todasSkusBatch = ItemProgramacao::whereIn('programacao_id', $programacaoIds)
                ->pluck('sku')
                ->filter()
                ->unique()
                ->values()
                ->toArray();

            if (!empty($todasSkusBatch)) {
                $taxaPorSku = Produto::whereIn('sku', $todasSkusBatch)
                    ->pluck('taxa_por_hora', 'sku')
                    ->toArray();
            }
        }

        // Batch: eficiencia por OP, agrupado por programacao_id — evita N+1 dentro do foreach
        $eficienciaPorProgPorOp = collect();
        if (!empty($programacaoIds)) {
            $eficienciaPorProgPorOp = CodiEficiencia::whereIn('programacao_id', $programacaoIds)
                ->get()
                ->groupBy('programacao_id')
                ->map(fn ($group) => $group->keyBy('numero_op'));
        }

        // Batch: resultados por item, agrupado por programacao_id — evita N+1 dentro do foreach
        $resultadosPorProgPorItem = collect();
        if (!empty($programacaoIds)) {
            $resultadosPorProgPorItem = ResultadoSequencia::whereIn('programacao_id', $programacaoIds)
                ->get()
                ->groupBy('programacao_id')
                ->map(fn ($group) => $group->groupBy('item_id'));
        }

        // ─────────────────────────────────────────────────────────────────────────

        // --- HISTÓRICO 7 DIAS (14d window for trend comparison) ---
        // Eventos PRODUCAO são incluídos integralmente.
        // Eventos PARADA excluem Intervalo e Parada Programada — mesma lógica do PainelPrincipal::abrirEventosOp()
        $cutoff14d = now()->subDays(14)->startOfDay();
        $rawHistorico = CodiEvento::selectRaw('
                codigo_recurso,
                tipo_evento,
                DATE(inicio_evento) AS dia,
                SUM(quantidade) AS total_qty,
                SUM(duracao_minutos) AS total_min
            ')
            ->where('inicio_evento', '>=', $cutoff14d)
            ->where(function ($q): void {
                $q->where('tipo_evento', 'PRODUCAO')
                  ->orWhere(function ($q2): void {
                      $q2->where('tipo_evento', 'PARADA')
                         ->where(function ($q3): void {
                             $q3->whereRaw("JSON_EXTRACT(dados_raw, '$.parada.nomeParada') IS NULL")
                                ->orWhereRaw("UPPER(JSON_UNQUOTE(JSON_EXTRACT(dados_raw, '$.parada.nomeParada'))) != 'PARADA PROGRAMADA'");
                         })
                         ->where(function ($q3): void {
                             $q3->whereRaw("JSON_EXTRACT(dados_raw, '$.parada.tipoParada.nomeTipoParada') IS NULL")
                                ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(dados_raw, '$.parada.tipoParada.nomeTipoParada')) != 'Intervalo'");
                         });
                  });
            })
            ->groupBy('codigo_recurso', 'tipo_evento', DB::raw('DATE(inicio_evento)'))
            ->get();

        // $historicoPorRecurso[codigo_recurso][dia]['producao_qty'|'producao_min'|'parada_min']
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

        // ─────────────────────────────────────────────────────────────────────────

        foreach ($linhasComProgramacao as $linha) {
            // Mapeia codigo da linha para nome do recurso CODI (LN04 → LINHA 4)
            $numLinha        = ltrim(str_replace('LN', '', strtoupper($linha->codigo)), '0');
            $nomeRecursoCodi = 'LINHA ' . $numLinha;

            // Performance CODI para esta linha — from batch pre-load (no per-line query)
            $performance = $performancePorNome[$nomeRecursoCodi] ?? null;

            // ── Histórico 7d — derivado do batch pre-load $historicoPorRecurso ────
            $codiRecurso  = $performance?->codigo_recurso;
            $diasHistorico = $codiRecurso !== null ? ($historicoPorRecurso[$codiRecurso] ?? []) : [];
            $hoje          = now()->toDateString();
            $seteAtras     = now()->subDays(7)->toDateString();

            // Split: últimos 7 dias vs 7 dias anteriores
            $semanaAtual    = array_filter($diasHistorico, fn ($d) => $d >= $seteAtras && $d <= $hoje, ARRAY_FILTER_USE_KEY);
            $semanaAnterior = array_filter($diasHistorico, fn ($d) => $d < $seteAtras, ARRAY_FILTER_USE_KEY);

            // Disponibilidade diária (só dias com alguma atividade)
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

            // KPIs 7d
            $producaoTotal7d = array_sum(array_column($dadosGrafico, 'producao_qty'));
            $horasParadas7d  = array_sum(array_column($dadosGrafico, 'paradas_min')) / 60;
            $dispValues      = array_filter(array_column($dadosGrafico, 'disponibilidade'), fn ($v) => $v !== null);
            $disponibilidadeMedia7d = count($dispValues) > 0
                ? round(array_sum($dispValues) / count($dispValues), 1)
                : null;

            // Tendência de ritmo
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
            // ─────────────────────────────────────────────────────────────────────

            // Programação confirmada mais recente com itens em ordem de sequência
            $programacao = Programacao::where('linha_id', $linha->id)
                ->where('status', 'confirmada')
                ->with(['itens' => fn ($q) => $q->orderBy('sequencia')])
                ->orderByDesc('created_at')
                ->first();

            if (!$programacao) {
                continue;
            }

            // OP numbers desta programação
            $opNums = $programacao->itens->pluck('numero_op')->filter()->toArray();

            if (empty($opNums)) {
                continue;
            }

            // Mapa: numero_op → quantidade total realizada (da fonte mais atual: CodiEvento)
            $realizadoPorOp = CodiEvento::whereIn('ordem_producao', $opNums)
                ->where('tipo_evento', 'PRODUCAO')
                ->selectRaw('ordem_producao, SUM(quantidade) as total_realizado')
                ->groupBy('ordem_producao')
                ->pluck('total_realizado', 'ordem_producao')
                ->toArray();

            // codigo_recurso para detecção de migração (conclusão real)
            $codigoRecurso = $performance?->codigo_recurso
                ?? CodiEvento::whereIn('ordem_producao', $opNums)->value('codigo_recurso');

            // ── Rodada 2: per-line derivations from batch pre-loads ───────────────

            // Parada em aberto agora (fim_evento IS NULL)
            $paradaAbertaEvento = $codigoRecurso ? ($paradaAbertaPorRecurso[$codigoRecurso] ?? null) : null;
            $paradaAbertaInfo   = null;
            if ($paradaAbertaEvento !== null) {
                $raw = is_array($paradaAbertaEvento->dados_raw)
                    ? $paradaAbertaEvento->dados_raw
                    : json_decode($paradaAbertaEvento->dados_raw ?? '{}', true);
                $paradaAbertaInfo = [
                    'minutos' => max(0, (int) Carbon::parse($paradaAbertaEvento->inicio_evento)->diffInMinutes(now())),
                    'nome'    => $raw['parada']['nomeParada'] ?? null,
                ];
            }

            // Total parado hoje (minutos)
            $tempoParadoHojeMin = $codigoRecurso
                ? (int) ($tempoParadoHojePorRecurso[$codigoRecurso] ?? 0)
                : 0;

            // Sem sinal CODI — last event > 2h ago during work hours (06:00–22:00, weekdays)
            $semSinalCodi = false;
            if ($codigoRecurso) {
                $ultimoEventoTs = $ultimoEventoPorRecurso[$codigoRecurso] ?? null;
                if ($ultimoEventoTs !== null) {
                    $hora         = (int) now()->format('H');
                    $horarioTurno = $hora >= 6 && $hora < 22 && !now()->isWeekend();
                    if ($horarioTurno && Carbon::parse($ultimoEventoTs)->lt(now()->subHours(2))) {
                        $semSinalCodi = true;
                    }
                }
            }

            // ─────────────────────────────────────────────────────────────────────

            // Pre-load MAX(fim_evento) per OP — avoids N+1 in the three foreach loops below
            $ultimoFimPorOp = CodiEvento::whereIn('ordem_producao', $opNums)
                ->selectRaw('ordem_producao, MAX(fim_evento) as ultimo_fim')
                ->groupBy('ordem_producao')
                ->pluck('ultimo_fim', 'ordem_producao')
                ->toArray();

            // Pre-load accumulated effective production time per OP (sum of PRODUCAO event durations)
            // Comparable to resultado_sequencia.duracao_minutos (planned time) for status/progress
            $duracaoEfetivaPorOp = CodiEvento::whereIn('ordem_producao', $opNums)
                ->where('tipo_evento', 'PRODUCAO')
                ->selectRaw('ordem_producao, SUM(duracao_minutos) as total_duracao')
                ->groupBy('ordem_producao')
                ->pluck('total_duracao', 'ordem_producao')
                ->toArray();

            // Bulk migration check for all OPs in this programacao
            $migradosOps = collect();
            if ($codigoRecurso) {
                $fimsFiltrados = array_filter($ultimoFimPorOp);  // remove nulls before min()
                $minFim = !empty($fimsFiltrados) ? min($fimsFiltrados) : null;
                if ($minFim) {
                    $eventosPos = CodiEvento::where('codigo_recurso', $codigoRecurso)
                        ->where('tipo_evento', 'PRODUCAO')
                        ->where('inicio_evento', '>=', $minFim)
                        ->select(['ordem_producao', 'inicio_evento'])
                        ->get();

                    foreach ($ultimoFimPorOp as $opNum => $ultimoFim) {
                        if (!$ultimoFim) {
                            continue;
                        }
                        $migrou = $eventosPos->contains(function ($e) use ($opNum, $ultimoFim) {
                            return $e->ordem_producao !== $opNum
                                && $e->inicio_evento >= $ultimoFim;
                        });
                        if ($migrou) {
                            $migradosOps->push($opNum);
                        }
                    }
                }
            }

            // Eficiência calculada por OP, indexada por numero_op — O(1) lookup do batch pre-load
            /** @var \Illuminate\Support\Collection<string, CodiEficiencia> $eficienciaPorOp */
            $eficienciaPorOp = $eficienciaPorProgPorOp[$programacao->id] ?? collect();

            // ResultadoSequencia agrupado por item_id — O(1) lookup do batch pre-load
            /** @var \Illuminate\Support\Collection<int, \Illuminate\Support\Collection> $resultadosPorItem */
            $resultadosPorItem = $resultadosPorProgPorItem[$programacao->id] ?? collect();

            // ---------------------------------------------------------------
            // Classificar cada item como nao_iniciada | em_andamento | finalizada
            // ---------------------------------------------------------------
            $totalOps       = $programacao->itens->count();
            $opsFinalizadas = 0;
            $opAtualItem    = null; // ItemProgramacao da OP atual

            foreach ($programacao->itens as $item) {
                $realizado  = (float) ($realizadoPorOp[$item->numero_op] ?? 0);
                $programado = (float) $item->quantidade;

                // Verifica migração de recurso CODI (conclusão real mesmo com qty < programada)
                $opConcluida = false;
                if ($codigoRecurso && $realizado > 0) {
                    $ultimoFimOp = $ultimoFimPorOp[$item->numero_op] ?? null;
                    if ($ultimoFimOp) {
                        $opConcluida = $migradosOps->contains($item->numero_op);
                    }
                }

                if ($realizado >= $programado || $opConcluida) {
                    $opsFinalizadas++;
                } elseif ($realizado > 0) {
                    // Keep overwriting — last em_andamento OP wins, matching PainelPrincipal behavior
                    $opAtualItem = $item;
                }
            }

            // Se nenhuma OP em andamento, op_atual é a primeira não iniciada
            if ($opAtualItem === null) {
                foreach ($programacao->itens as $item) {
                    $realizado = (float) ($realizadoPorOp[$item->numero_op] ?? 0);
                    if ($realizado <= 0) {
                        // Confirmar que não está finalizada via migração
                        $opConcluida = false;
                        if ($codigoRecurso) {
                            $ultimoFimOp = $ultimoFimPorOp[$item->numero_op] ?? null;
                            if ($ultimoFimOp) {
                                $opConcluida = $migradosOps->contains($item->numero_op);
                            }
                        }
                        if (!$opConcluida) {
                            $opAtualItem = $item;
                            break;
                        }
                    }
                }
            }

            // ─── FALLBACK: se nenhuma OP do PCP tem produção, verificar CODI ───────
            if ($codigoRecurso) {
                $opAtualItemRealizado = $opAtualItem
                    ? (float) ($realizadoPorOp[$opAtualItem->numero_op] ?? 0)
                    : 0;

                if ($opAtualItemRealizado <= 0) {
                    $inicioDiaFallback = \Carbon\Carbon::today()->setHour(6)->setMinute(0)->setSecond(0);

                    $opCODI = \Illuminate\Support\Facades\DB::table('codi_eventos')
                        ->where('codigo_recurso', $codigoRecurso)
                        ->where('tipo_evento', 'PRODUCAO')
                        ->where('inicio_evento', '>=', $inicioDiaFallback)
                        ->orderByDesc('inicio_evento')
                        ->first(['ordem_producao', 'inicio_evento', 'fim_evento']);

                    if ($opCODI && (!$opAtualItem || $opCODI->ordem_producao !== $opAtualItem->numero_op)) {
                        $itemCODI = \Illuminate\Support\Facades\DB::table('itens_programacao')
                            ->where('numero_op', $opCODI->ordem_producao)
                            ->first(['descricao_produto', 'quantidade', 'sku']);

                        // Se não encontrou no PCP, busca nome do produto no dados_raw do CODI
                        $descricaoProduto = $itemCODI->descricao_produto ?? null;
                        if (!$descricaoProduto) {
                            $eventoRaw = \Illuminate\Support\Facades\DB::table('codi_eventos')
                                ->where('ordem_producao', $opCODI->ordem_producao)
                                ->whereNotNull('dados_raw')
                                ->first(['dados_raw']);
                            if ($eventoRaw) {
                                $raw = is_array($eventoRaw->dados_raw)
                                    ? $eventoRaw->dados_raw
                                    : json_decode($eventoRaw->dados_raw, true);
                                $descricaoProduto = $raw['ordens'][0]['ordemProducao']['item']['nomeItem'] ?? '';
                            }
                        }

                        $realizadoCODI = (int) \Illuminate\Support\Facades\DB::table('codi_eventos')
                            ->where('ordem_producao', $opCODI->ordem_producao)
                            ->where('tipo_evento', 'PRODUCAO')
                            ->sum('quantidade');

                        $opAtualItem = (object) [
                            'numero_op'         => $opCODI->ordem_producao,
                            'descricao_produto'  => $descricaoProduto ?? '',
                            'quantidade'         => (float) ($itemCODI->quantidade ?? 0),
                            'sku'                => $itemCODI->sku ?? '',
                            'id'                 => null,
                            'divergente'         => true,
                        ];

                        $realizadoPorOp[$opCODI->ordem_producao] = $realizadoCODI;
                    }
                }
            }
            // ─── FIM FALLBACK ─────────────────────────────────────────────────────

            // ---------------------------------------------------------------
            // Dados detalhados da OP atual
            // ---------------------------------------------------------------
            $opAtualArray = null;
            $statusOp     = 'Aguardando';
            $corOp        = 'gray';

            if ($opAtualItem !== null) {
                $eficiencia = $eficienciaPorOp[$opAtualItem->numero_op] ?? null;

                // Blocos de producao planejados para este item
                $blocos = isset($resultadosPorItem[$opAtualItem->id])
                    ? $resultadosPorItem[$opAtualItem->id]->where('tipo', 'producao')
                    : collect();

                $tempoPrevMin = (int) $blocos->sum('duracao_minutos');
                $fimPrevisto  = $blocos->max('fim');  // Carbon|null

                // "Rodando" = accumulated effective production time (sum of PRODUCAO event durations)
                // Directly comparable to $tempoPrevMin (planned duration), handles multi-day OPs correctly
                $duracaoEfetiva  = $duracaoEfetivaPorOp[$opAtualItem->numero_op] ?? null;
                $tempoRodandoMin = $duracaoEfetiva !== null ? (int) $duracaoEfetiva : null;

                // Progresso em quantidade
                $realizado  = (float) ($realizadoPorOp[$opAtualItem->numero_op] ?? 0);
                $programado = (float) $opAtualItem->quantidade;
                $pct        = $programado > 0 ? min(100, (int) round($realizado / $programado * 100)) : 0;
                $faltam     = max(0.0, $programado - $realizado);

                // Ritmo: média dos últimos 5 eventos PRODUCAO — from batch pre-load
                $ultimosEventos = $ultimosEventosPorOp[$opAtualItem->numero_op] ?? collect();

                $ritmoMin = 0.0;
                if ($ultimosEventos->count() > 0) {
                    $totalDuracao = (float) $ultimosEventos->sum('duracao_minutos');
                    $totalQtd     = (float) $ultimosEventos->sum('quantidade');
                    if ($totalDuracao > 0) {
                        $ritmoMin = $totalQtd / $totalDuracao;
                    }
                }
                $ritmoCxH = (int) round($ritmoMin * 60);

                // ETA
                $etaFormatada = null;
                $atrasadoMin  = null;

                if ($pct >= 100) {
                    $etaFormatada = 'Concluída';
                    $atrasadoMin  = 0;
                } elseif ($ritmoMin > 0 && $faltam > 0) {
                    $etaDatetime  = now()->addMinutes((int) ceil($faltam / $ritmoMin));
                    $etaFormatada = $etaDatetime->isToday()
                        ? $etaDatetime->format('H:i')
                        : $etaDatetime->format('H:i') . ' de ' . $etaDatetime->format('d/m');
                    if ($fimPrevisto) {
                        $atrasadoMin = (int) Carbon::parse($fimPrevisto)->diffInMinutes($etaDatetime, false);
                        // positivo = atrasada (ETA após fim previsto), negativo = adiantada
                    }
                }

                // Início real vs. planejado — usado na cor da OP
                $atrasadoInicioMin = null;
                if ($eficiencia?->inicio_real && $eficiencia?->inicio_previsto) {
                    $inicioPrevDT = \DateTimeImmutable::createFromMutable(
                        Carbon::parse($eficiencia->inicio_previsto)->toDateTime()
                    );
                    $inicioRealDT = \DateTimeImmutable::createFromMutable(
                        Carbon::parse($eficiencia->inicio_real)->toDateTime()
                    );
                    // Calcular atraso em minutos úteis (descontando turnos, noites e fins de semana)
                    $calendarioIdLinha = $linha->calendario?->id ?? null;
                    if ($calendarioIdLinha && $inicioRealDT > $inicioPrevDT) {
                        $atrasadoInicioMin = (int) app(\App\Services\CalendarioService::class)
                            ->minutosUteisEntre($inicioPrevDT, $inicioRealDT, $calendarioIdLinha, []);
                    } elseif ($inicioRealDT <= $inicioPrevDT) {
                        // Adiantado — mantém cálculo corrido (negativo)
                        $atrasadoInicioMin = (int) Carbon::parse($eficiencia->inicio_previsto)
                            ->diffInMinutes(Carbon::parse($eficiencia->inicio_real), false);
                    }
                }

                // Hierarquia de status — ordem importa
                if ($pct >= 100) {
                    $statusOp = 'Concluída';
                    $corOp    = 'green';
                } elseif (!empty($paradaAbertaInfo)) {
                    $nomeParada = $paradaAbertaInfo['nome'] ?? '';
                    if (stripos($nomeParada, 'PARADA PROGRAMADA') !== false) {
                        $statusOp = 'Parada Prog.';
                        $corOp    = 'orange';
                    } else {
                        $statusOp = 'Parada';
                        $corOp    = 'red';
                    }
                } elseif ($atrasadoInicioMin !== null && $atrasadoInicioMin > 15) {
                    $statusOp = 'Atrasada';
                    $corOp    = 'red';
                } elseif ($atrasadoInicioMin !== null && $atrasadoInicioMin <= 15) {
                    $statusOp = 'Em dia';
                    $corOp    = 'green';
                } elseif ($tempoRodandoMin !== null && $tempoRodandoMin > 0) {
                    $statusOp = 'Em dia';
                    $corOp    = 'green';
                } elseif ($hasOpVencidaMais15min ?? false) {
                    $statusOp = 'Atrasada';
                    $corOp    = 'red';
                } elseif ($hasOpNaFilaVencida ?? false) {
                    $statusOp = 'Atenção';
                    $corOp    = 'yellow';
                } elseif ($semSinalCodi ?? false) {
                    $statusOp = 'Sem sinal';
                    $corOp    = 'gray';
                } else {
                    $statusOp = 'Aguardando';
                    $corOp    = 'gray';
                }

                // Bug 2: Para OPs em andamento, calcular desvio de prazo em tempo real
                // codi_eficiencia.desvio_prazo_dias pode estar obsoleto se a OP pausou e retomou
                $opEmAndamento = $realizado > 0 && $pct < 100;
                $desvioEmAndamentoHoras = null;
                if ($opEmAndamento && $fimPrevisto) {
                    // diffInHours(now(), false): positivo = now() > fimPrevisto = atrasado
                    $horasAtraso = (int) Carbon::parse($fimPrevisto)->diffInHours(now(), false);
                    if ($horasAtraso > 0) {
                        $desvioEmAndamentoHoras = $horasAtraso;
                    }
                }

                // Taxa nominal do produto (cx/h)
                $taxaNominalCxH = isset($taxaPorSku[$opAtualItem->sku])
                    ? (int) round((float) $taxaPorSku[$opAtualItem->sku])
                    : null;

                $opAtualArray = [
                    'numero_op'          => $opAtualItem->numero_op,
                    'sku'                => $opAtualItem->sku,
                    'descricao'          => $opAtualItem->descricao_produto,
                    'programado'         => $programado,
                    'realizado'          => $realizado,
                    'pct'                => $pct,
                    'faltam'             => $faltam,
                    'tempo_previsto_min' => $tempoPrevMin,
                    'tempo_rodando_min'  => $tempoRodandoMin,
                    'atrasado_min'       => $atrasadoMin,
                    'tempo_atrasado'     => $tempoPrevMin > 0 && $tempoRodandoMin !== null && $tempoRodandoMin > $tempoPrevMin,
                    'eta_formatada'      => $etaFormatada,
                    'ritmo_cxh'          => $ritmoCxH,
                    'taxa_nominal_cxh'   => $taxaNominalCxH,
                    'atraso_inicio_min'  => $atrasadoInicioMin,
                    'motivo_atraso'      => ($corOp === 'red' && $atrasadoInicioMin !== null && $atrasadoInicioMin > 15)
                        ? sprintf('Iniciou %s após o planejado', minutos_para_hhmm($atrasadoInicioMin))
                        : null,
                    'inicio_previsto_dt' => $eficiencia?->inicio_previsto?->format('d/m H:i'),
                    'inicio_real_dt'     => $eficiencia?->inicio_real?->format('d/m H:i'),
                    // Para OPs em andamento: calculado em tempo real (codi_eficiencia pode estar obsoleto)
                    // Para OPs concluídas: usar codi_eficiencia.desvio_prazo_dias normalmente
                    'op_em_andamento'                  => $opEmAndamento,
                    'desvio_prazo_em_andamento_horas'  => $desvioEmAndamentoHoras,
                    'desvio_prazo_dias'                => $opEmAndamento ? null : ($eficiencia?->desvio_prazo_dias),
                    'desvio_tempo_horas' => $eficiencia?->desvio_tempo_horas,
                    'oee'                => $eficiencia?->oee,
                    'eficiencia'         => $eficiencia?->eficiencia_quantidade,
                    'disponibilidade'    => $eficiencia?->disponibilidade,
                    'performance'        => $eficiencia?->performance_tempo,
                    'status_alerta'      => $eficiencia?->status,  // ok|aviso|critico|pendente
                    // Identifica a métrica com pior desvio abaixo do threshold crítico.
                    // Thresholds espelham EficienciaCalculator::classificarStatus():
                    //   OEE < 50, eficiencia_quantidade < 70, desvio_prazo_dias > 5
                    'critico_motivo'     => (function () use ($eficiencia, $opEmAndamento): ?array {
                        if ($eficiencia === null) {
                            return null;
                        }

                        $checks = [
                            [
                                'metrica'       => 'OEE',
                                'valor'         => (float) ($eficiencia->oee ?? 0),
                                'threshold_min' => 50.0,
                                'unidade'       => '%',
                            ],
                            [
                                'metrica'       => 'Eficiência',
                                'valor'         => (float) ($eficiencia->eficiencia_quantidade ?? 0),
                                'threshold_min' => 70.0,
                                'unidade'       => '%',
                            ],
                        ];

                        // desvio_prazo_dias: positivo = atrasado; threshold é >5 dias
                        // Para OPs em andamento, ignorar codi_eficiencia (pode estar obsoleto)
                        $desvioPrazo = $opEmAndamento ? null : $eficiencia->desvio_prazo_dias;
                        if ($desvioPrazo !== null && $desvioPrazo > 5) {
                            $checks[] = [
                                'metrica'       => 'Prazo',
                                'valor'         => (float) $desvioPrazo,
                                'threshold_min' => null, // não se aplica (é threshold_max)
                                'unidade'       => 'dias de atraso',
                            ];
                        }

                        // Encontra a métrica com maior desvio percentual abaixo do limiar crítico
                        $worst = null;
                        foreach ($checks as $check) {
                            if ($check['threshold_min'] === null) {
                                // Prazo: já foi incluído somente se violou o threshold
                                if ($worst === null) {
                                    $worst = $check;
                                }
                                continue;
                            }
                            if ($check['valor'] < $check['threshold_min']) {
                                if ($worst === null
                                    || $check['threshold_min'] === null
                                    || ($worst['threshold_min'] !== null
                                        && $check['valor'] < $worst['valor'])) {
                                    $worst = $check;
                                }
                            }
                        }

                        return $worst;
                    })(),
                    'status'             => $statusOp,
                    'cor'                => $corOp,
                ];
            }

            // ---------------------------------------------------------------
            // Próximas 3 OPs não iniciadas (após a op_atual)
            // ---------------------------------------------------------------
            $proximasOps         = [];
            $passouOpAtual       = ($opAtualItem === null);
            $proximasEncontradas = 0;
            $skuAnteriorSetup    = $opAtualItem?->sku; // tracks previous SKU for setup lookup

            foreach ($programacao->itens as $item) {
                // Pular a op_atual e tudo antes dela
                if (!$passouOpAtual) {
                    if ($opAtualItem !== null && $item->id === $opAtualItem->id) {
                        $passouOpAtual = true;
                    }
                    continue;
                }

                if ($proximasEncontradas >= 3) {
                    break;
                }

                $realizado = (float) ($realizadoPorOp[$item->numero_op] ?? 0);

                // Incluir apenas OPs não iniciadas
                if ($realizado > 0) {
                    continue;
                }

                // Verificar se não está finalizada via migração de recurso
                $opConcluida = false;
                if ($codigoRecurso) {
                    $ultimoFimOp = $ultimoFimPorOp[$item->numero_op] ?? null;
                    if ($ultimoFimOp) {
                        $opConcluida = $migradosOps->contains($item->numero_op);
                    }
                }

                if ($opConcluida) {
                    continue;
                }

                $blocosItem = isset($resultadosPorItem[$item->id])
                    ? $resultadosPorItem[$item->id]->where('tipo', 'producao')
                    : collect();

                $tempoPrevItemMin = (int) $blocosItem->sum('duracao_minutos');
                $inicioPrevisto   = $blocosItem->min('inicio');

                // Setup time: SKU of previous OP → this OP's SKU
                $setupMin = null;
                if ($skuAnteriorSetup !== null) {
                    $chaveSetup = $skuAnteriorSetup . '|' . $item->sku;
                    $setupMin   = ($setupMatrix[$chaveSetup] ?? null)?->duracao_minutos;
                    if ($setupMin === 0) {
                        $setupMin = null; // treat zero as "no setup" — don't display
                    }
                }
                $skuAnteriorSetup = $item->sku;

                $proximasOps[] = [
                    'numero_op'              => $item->numero_op,
                    'sequencia'              => $item->sequencia,
                    'sku'                    => $item->sku,
                    'descricao'              => $item->descricao_produto,
                    'programado'             => (float) $item->quantidade,
                    'tempo_previsto_min'     => $tempoPrevItemMin,
                    'inicio_previsto'        => $inicioPrevisto
                        ? Carbon::parse($inicioPrevisto)->format('d/m H:i')
                        : null,
                    'inicio_previsto_status' => $inicioPrevisto === null
                        ? null
                        : (Carbon::parse($inicioPrevisto)->lt(now()) ? 'vencido'
                            : (Carbon::parse($inicioPrevisto)->isToday() ? 'hoje' : 'futuro')),
                    'setup_min'              => $setupMin,
                ];

                $proximasEncontradas++;
            }

            // Bug 1: verificar se há OPs na fila (não iniciadas) com início previsto vencido
            // Usa resultado_sequencia já carregado em batch — sem query adicional
            $hasOpNaFilaVencida    = false;
            $hasOpVencidaMais15min = false;
            if ($opsFinalizadas < $totalOps) {
                foreach ($programacao->itens as $itemPendente) {
                    $realizadoPendente = (float) ($realizadoPorOp[$itemPendente->numero_op] ?? 0);
                    if ($realizadoPendente > 0) {
                        continue; // OP já iniciada
                    }
                    // Checar se está concluída via migração de recurso
                    $opConcluidaPendente = false;
                    if ($codigoRecurso) {
                        $ultimoFimOpPendente = $ultimoFimPorOp[$itemPendente->numero_op] ?? null;
                        if ($ultimoFimOpPendente) {
                            $opConcluidaPendente = $migradosOps->contains($itemPendente->numero_op);
                        }
                    }
                    if ($opConcluidaPendente) {
                        continue;
                    }
                    // OP pendente — verificar se o início planejado já venceu
                    $blocosPendente = isset($resultadosPorItem[$itemPendente->id])
                        ? $resultadosPorItem[$itemPendente->id]->where('tipo', 'producao')
                        : collect();
                    $inicioPrevistoPendente = $blocosPendente->min('inicio');
                    if ($inicioPrevistoPendente && Carbon::parse($inicioPrevistoPendente)->lt(now())) {
                        $hasOpNaFilaVencida = true;
                        if (Carbon::parse($inicioPrevistoPendente)->addMinutes(15)->isPast()) {
                            $hasOpVencidaMais15min = true;
                        }
                        break;
                    }
                }
            }

            // ---------------------------------------------------------------
            // Estado geral da linha — prioridade: OP em andamento > fila vencida > aguardando
            // ---------------------------------------------------------------
            if ($opsFinalizadas === $totalOps) {
                // Todas concluídas
                $corLinha    = 'green';
                $estadoLinha = 'Concluída';
            } elseif ($opAtualArray !== null) {
                // Tem OP rodando agora — herda cor da OP
                $corLinha    = $corOp;
                $estadoLinha = $statusOp;
            } elseif ($hasOpVencidaMais15min) {
                // Sem OP rodando + próxima deveria ter iniciado há mais de 15min
                $corLinha    = 'red';
                $estadoLinha = 'Atrasada';
            } elseif ($hasOpNaFilaVencida) {
                // Sem OP rodando + próxima vencida mas dentro da tolerância de 15min
                $corLinha    = 'yellow';
                $estadoLinha = 'Atenção';
            } else {
                // Sem OP rodando + próxima ainda não é hora
                $corLinha    = 'gray';
                $estadoLinha = 'Aguardando';
            }

            // Reclassifica com os mesmos sinais em tempo real que a TV usa
            // (Parada Programada/Intervalo/Troca de Kit/Troca de Líquido/
            // Desconexão/ritmo atrasado) — fonte única, compartilhada com
            // getPerformanceLinhas() e consumida sem re-derivação pela TV.
            $ritmoDia  = $this->calcularRitmoDoDia($codigoRecurso, $linha->id);
            $tempoAtrasoMinLinha = null;
            if ($codigoRecurso) {
                $atrasoInicioMinLinha = $opAtualArray['atraso_inicio_min'] ?? null;
                $reclassificado = $this->reclassificarComSinaisTempoReal(
                    $codigoRecurso,
                    $ritmoDia['prevXReal'],
                    (float) ($ritmoDia['ritmo'] ?? 0),
                    $atrasoInicioMinLinha,
                    $corLinha,
                    $estadoLinha,
                    $opAtualArray['numero_op'] ?? null
                );
                $corLinha            = $reclassificado['cor'];
                $estadoLinha         = $reclassificado['estado'];
                $tempoAtrasoMinLinha = $reclassificado['tempo_atraso_min'];
            }

            $ultimoSyncLinha = $performance?->sincronizado_em !== null
                ? Carbon::parse($performance->sincronizado_em)->locale('pt_BR')->diffForHumans()
                : 'nunca';

            $oeeTempoReal = $codigoRecurso
                ? $this->calcularOeeLinha($codigoRecurso, $opNums)
                : ['disponibilidade' => null, 'performance' => null, 'qualidade' => null, 'oee' => null];

            $resultado[] = [
                'id'                   => $linha->id,
                'codigo'               => $linha->codigo,
                'nome'                 => $nomeRecursoCodi,
                'programacao_id'       => $programacao->id,
                'estado'               => $estadoLinha,
                'cor'                  => $corLinha,
                'tempo_atraso_min'     => $tempoAtrasoMinLinha,
                'total_ops'            => $totalOps,
                'ops_concluidas'       => $opsFinalizadas,
                'qtd_realizada_total'  => (float) array_sum($realizadoPorOp),
                'qtd_programada_total' => (float) $programacao->itens->sum('quantidade'),
                'estado_atual_codi'    => $performance?->estado_atual,
                'parada_aberta'        => $paradaAbertaInfo,
                'tempo_parado_hoje_min'=> $tempoParadoHojeMin,
                'sem_sinal_codi'       => $semSinalCodi,
                'op_atual'             => $opAtualArray,
                'oee_tempo_real'       => $oeeTempoReal,
                'proximas_ops'         => $proximasOps,
                'ultimo_sync'          => $ultimoSyncLinha,
                'historico_7d'         => [
                    'producao_total'        => $producaoTotal7d,
                    'disponibilidade_media' => $disponibilidadeMedia7d,
                    'horas_paradas'         => round($horasParadas7d, 1),
                    'tendencia'             => [
                        'direcao'        => $tendencia,
                        'ritmo_atual'    => $ritmoAtual,
                        'ritmo_anterior' => $ritmoAnterior,
                    ],
                    'dados_grafico'         => $dadosGrafico,
                ],
            ];
        }

        $this->linhas = $resultado;
    }

    public function getMotivosParada(): array
    {
        $diasMap = ['hoje' => 0, '3d' => 3, '7d' => 7, '15d' => 15];
        $dias    = $diasMap[$this->motivoPeriodo] ?? 0;

        if ($this->motivoLinha === '') {
            $linhasAtivas = \Illuminate\Support\Facades\DB::table('linhas')->where('ativo', true)->get();
            $consolidado  = collect();

            foreach ($linhasAtivas as $linha) {
                $opsLinha = \Illuminate\Support\Facades\DB::table('itens_programacao as ip')
                    ->join('programacoes as p', 'p.id', '=', 'ip.programacao_id')
                    ->where('p.linha_id', $linha->id)
                    ->whereIn('p.status', ['confirmada', 'arquivada'])
                    ->whereNotNull('ip.numero_op')
                    ->pluck('ip.numero_op');

                if ($opsLinha->isEmpty()) continue;

                $inicioDia = \Carbon\Carbon::today()->setHour(6)->setMinute(0)->setSecond(0);

                $query = \Illuminate\Support\Facades\DB::table('codi_eventos')
                    ->where('tipo_evento', 'PARADA')
                    ->whereIn('ordem_producao', $opsLinha)
                    ->whereRaw('TIMESTAMPDIFF(MINUTE, inicio_evento, IFNULL(fim_evento, NOW())) < 240')
                    ->whereRaw('JSON_EXTRACT(dados_raw, "$.parada.nomeParada") IS NOT NULL')
                    ->whereRaw('JSON_UNQUOTE(JSON_EXTRACT(dados_raw, "$.parada.tipoParada.nomeTipoParada")) != "Intervalo"')
                    ->whereRaw('JSON_UNQUOTE(JSON_EXTRACT(dados_raw, "$.parada.nomeParada")) != "PARADA PROGRAMADA"');

                if ($dias === 0) {
                    $query->where('inicio_evento', '>=', $inicioDia);
                } else {
                    $query->where('inicio_evento', '>=', $inicioDia->copy()->subDays($dias));
                }

                $top3Linha = $query
                    ->selectRaw('
                        JSON_UNQUOTE(JSON_EXTRACT(dados_raw, "$.parada.nomeParada")) as motivo,
                        COUNT(*) as ocorrencias,
                        SUM(TIMESTAMPDIFF(MINUTE, inicio_evento, IFNULL(fim_evento, NOW()))) as total_min
                    ')
                    ->groupBy('motivo')
                    ->orderByDesc('total_min')
                    ->limit(3)
                    ->get();

                $consolidado = $consolidado->concat($top3Linha);
            }

            return $consolidado
                ->groupBy('motivo')
                ->map(fn($grupo) => (object)[
                    'motivo'      => $grupo->first()->motivo,
                    'ocorrencias' => $grupo->sum('ocorrencias'),
                    'total_min'   => $grupo->sum('total_min'),
                ])
                ->sortByDesc('total_min')
                ->take(3)
                ->values()
                ->toArray();
        }

        $ops = \Illuminate\Support\Facades\DB::table('itens_programacao as ip')
            ->join('programacoes as p', 'p.id', '=', 'ip.programacao_id')
            ->join('linhas as l', 'l.id', '=', 'p.linha_id')
            ->where('l.codigo', $this->motivoLinha)
            ->whereIn('p.status', ['confirmada', 'arquivada'])
            ->whereNotNull('ip.numero_op')
            ->pluck('ip.numero_op');

        if ($ops->isEmpty()) {
            return [];
        }

        $query = \Illuminate\Support\Facades\DB::table('codi_eventos')
            ->where('tipo_evento', 'PARADA')
            ->whereIn('ordem_producao', $ops)
            ->whereRaw('TIMESTAMPDIFF(MINUTE, inicio_evento, IFNULL(fim_evento, NOW())) < 240')
            ->whereRaw('JSON_EXTRACT(dados_raw, "$.parada.nomeParada") IS NOT NULL')
            ->whereRaw('JSON_UNQUOTE(JSON_EXTRACT(dados_raw, "$.parada.tipoParada.nomeTipoParada")) != "Intervalo"')
            ->whereRaw('JSON_UNQUOTE(JSON_EXTRACT(dados_raw, "$.parada.nomeParada")) != "PARADA PROGRAMADA"');

        $inicioDia = \Carbon\Carbon::today()->setHour(6)->setMinute(0)->setSecond(0);
        if ($dias === 0) {
            $query->where('inicio_evento', '>=', $inicioDia);
        } else {
            $query->where('inicio_evento', '>=', $inicioDia->copy()->subDays($dias));
        }

        return $query
            ->selectRaw('
                JSON_UNQUOTE(JSON_EXTRACT(dados_raw, "$.parada.nomeParada")) as motivo,
                COUNT(*) as ocorrencias,
                SUM(TIMESTAMPDIFF(MINUTE, inicio_evento, IFNULL(fim_evento, NOW()))) as total_min
            ')
            ->groupBy('motivo')
            ->orderByDesc('total_min')
            ->limit(3)
            ->get()
            ->toArray();
    }

    public function getTotalParado(): int
    {
        if ($this->motivoLinha === '') {
            $motivos = $this->getMotivosParada();
            return (int) array_sum(array_column((array) $motivos, 'total_min'));
        }

        $diasMap = ['hoje' => 0, '3d' => 3, '7d' => 7, '15d' => 15];
        $dias    = $diasMap[$this->motivoPeriodo] ?? 0;

        $opsQuery = \Illuminate\Support\Facades\DB::table('itens_programacao as ip')
            ->join('programacoes as p', 'p.id', '=', 'ip.programacao_id')
            ->join('linhas as l', 'l.id', '=', 'p.linha_id')
            ->whereIn('p.status', ['confirmada', 'arquivada'])
            ->whereNotNull('ip.numero_op');

        $opsQuery->where('l.codigo', $this->motivoLinha);

        $ops = $opsQuery->pluck('ip.numero_op');

        if ($ops->isEmpty()) {
            return 0;
        }

        $query = \Illuminate\Support\Facades\DB::table('codi_eventos')
            ->where('tipo_evento', 'PARADA')
            ->whereIn('ordem_producao', $ops)
            ->whereRaw('TIMESTAMPDIFF(MINUTE, inicio_evento, IFNULL(fim_evento, NOW())) < 240')
            ->whereRaw('JSON_EXTRACT(dados_raw, "$.parada.nomeParada") IS NOT NULL')
            ->whereRaw('JSON_UNQUOTE(JSON_EXTRACT(dados_raw, "$.parada.tipoParada.nomeTipoParada")) != "Intervalo"')
            ->whereRaw('JSON_UNQUOTE(JSON_EXTRACT(dados_raw, "$.parada.nomeParada")) != "PARADA PROGRAMADA"');

        $inicioDia = \Carbon\Carbon::today()->setHour(6)->setMinute(0)->setSecond(0);
        if ($dias === 0) {
            $query->where('inicio_evento', '>=', $inicioDia);
        } else {
            $query->where('inicio_evento', '>=', $inicioDia->copy()->subDays($dias));
        }

        return (int) $query
            ->selectRaw('SUM(TIMESTAMPDIFF(MINUTE, inicio_evento, IFNULL(fim_evento, NOW()))) as min')
            ->value('min');
    }

    public function setMotivoLinha(string $linha): void
    {
        $this->motivoLinha = $linha;
    }

    public function setMotivoPeriodo(string $periodo): void
    {
        $this->motivoPeriodo = $periodo;
    }

    public function setPerfPeriodo(string $periodo): void
    {
        $this->perfPeriodo = $periodo;
    }

    public function getPerformanceLinhas(): array
    {
        $diasMap  = ['hoje' => 0, '3d' => 3, '7d' => 7, '15d' => 15];
        $dias     = $diasMap[$this->perfPeriodo] ?? 0;
        $inicioDia = \Carbon\Carbon::today()->setHour(6)->setMinute(0)->setSecond(0);

        if (empty($this->linhas)) {
            $this->carregarLinhas();
        }

        $linhasAtivas = \Illuminate\Support\Facades\DB::table('linhas')
            ->where('ativo', true)
            ->whereNotNull('codigo_recurso')
            ->orderBy('codigo')
            ->get();

        $resultado = [];

        foreach ($linhasAtivas as $linha) {
            $ritmoDia = $this->calcularRitmoDoDia($linha->codigo_recurso, $linha->id);
            $cxProd      = $ritmoDia['cxProd'];
            $ritmo       = $ritmoDia['ritmo'];
            $prevXReal   = $ritmoDia['prevXReal'];
            $minProdReal = $ritmoDia['minProdReal'];
            $minParado   = $ritmoDia['minParado'];

            // Disponibilidade/Performance — mesma fonte do calcularKpis() (CODI,
            // calcularOeeLinha), em vez da fórmula local minProd/(minProd+minParado).
            $opNums = \Illuminate\Support\Facades\DB::table('itens_programacao as ip')
                ->join('programacoes as p', 'p.id', '=', 'ip.programacao_id')
                ->where('p.linha_id', $linha->id)
                ->where('p.status', 'confirmada')
                ->pluck('ip.numero_op')
                ->toArray();

            $oeeData     = $this->calcularOeeLinha($linha->codigo_recurso, $opNums);
            $disponib    = $oeeData['disponibilidade'];
            $performance = $oeeData['performance'];

            // Status/cor definitivos — mesma fonte única usada em carregarLinhas()
            // (reclassificarComSinaisTempoReal), garante que a tabela de
            // performance bate com o card grid e com a TV.
            $linhaGrid  = collect($this->linhas)->firstWhere('codigo', $linha->codigo);
            $atrasoInicioMin = $linhaGrid['op_atual']['atraso_inicio_min'] ?? null;

            $reclassificado = $this->reclassificarComSinaisTempoReal(
                $linha->codigo_recurso,
                $prevXReal,
                (float) ($ritmo ?? 0),
                $atrasoInicioMin,
                $linhaGrid['cor'] ?? 'gray',
                $linhaGrid['estado'] ?? 'Em dia',
                $linhaGrid['op_atual']['numero_op'] ?? null
            );

            $status         = $reclassificado['estado'];
            $opAtrasada     = $reclassificado['atrasada'];
            $tempoAtrasoMin = $reclassificado['tempo_atraso_min'];
            $atrasoMin      = $atrasoInicioMin;

            $resultado[] = [
                'codigo'           => $linha->codigo,
                'nome'             => $linha->nome,
                'cx_prod'          => (int) round($cxProd),
                'ritmo'            => $ritmo,
                'performance'      => $performance,
                'disponib'         => $disponib,
                'atrasada'         => $opAtrasada,
                'status'           => $status,
                'tempo_prod_h'     => round($minProdReal / 60, 1),
                'tempo_parado_h'   => round($minParado / 60, 1),
                'atraso_min'       => $atrasoMin,
                'tempo_atraso_min' => $tempoAtrasoMin,
            ];
        }

        return $resultado;
    }

    /**
     * Ritmo do dia (cx/h) e Prev.xReal (capacidade teórica × jornada − soma
     * proporcional das OPs programadas hoje) de uma linha — mesmo cálculo que
     * a TV usa pro Card 3, extraído aqui pra ser reaproveitado tanto por
     * carregarLinhas() (classificação de cor/estado) quanto por
     * getPerformanceLinhas() (tabela de performance).
     *
     * @return array{cxProd: float, ritmo: ?float, prevXReal: ?int, minProdReal: float, minParado: float}
     */
    private function calcularRitmoDoDia(?string $codigoRecurso, int $linhaId): array
    {
        $vazio = ['cxProd' => 0.0, 'ritmo' => null, 'prevXReal' => null, 'minProdReal' => 0.0, 'minParado' => 0.0];

        if ($codigoRecurso === null) {
            return $vazio;
        }

        $inicioDia6 = Carbon::today()->setHour(6)->setMinute(0)->setSecond(0);

        // Produção do dia — direto por codigo_recurso, igual à TV (não
        // depende de a OP estar registrada em itens_programacao).
        $cxProd = (float) DB::table('codi_eventos')
            ->where('codigo_recurso', $codigoRecurso)
            ->where('tipo_evento', 'PRODUCAO')
            ->where('inicio_evento', '>=', $inicioDia6)
            ->sum('quantidade');

        $eventosDiretos = DB::table('codi_eventos')
            ->where('codigo_recurso', $codigoRecurso)
            ->whereIn('tipo_evento', ['PRODUCAO', 'PARADA'])
            ->where('inicio_evento', '>=', $inicioDia6)
            ->get(['tipo_evento', 'inicio_evento', 'fim_evento', 'dados_raw']);

        // Igual à TV: soma duracao_minutos de TODOS os eventos desde 06:00
        // (produção + parada) — dilui as paradas no ritmo médio.
        $minProd = (float) DB::table('codi_eventos')
            ->where('codigo_recurso', $codigoRecurso)
            ->where('inicio_evento', '>=', $inicioDia6)
            ->sum('duracao_minutos');

        $minProdReal = $eventosDiretos->where('tipo_evento', 'PRODUCAO')
            ->sum(fn ($e) => max(0, Carbon::parse($e->inicio_evento)->diffInMinutes($e->fim_evento ?? now())));

        $minParado = $eventosDiretos->filter(function ($e) {
            if ($e->tipo_evento !== 'PARADA') {
                return false;
            }
            $raw        = json_decode($e->dados_raw ?? '{}', true);
            $tipoParada = data_get($raw, 'parada.tipoParada.nomeTipoParada', '');
            $nomeParada = data_get($raw, 'parada.nomeParada', '');
            return $tipoParada !== 'Intervalo' && $nomeParada !== 'PARADA PROGRAMADA';
        })->sum(fn ($e) => max(0, Carbon::parse($e->inicio_evento)->diffInMinutes(Carbon::parse($e->fim_evento ?? now()))));

        $ritmo = $minProd > 0 ? round($cxProd / ($minProd / 60), 0) : null;

        $calendarioService = app(\App\Services\CalendarioService::class);
        $hoje   = Carbon::today()->format('Y-m-d');
        $amanha = Carbon::tomorrow()->format('Y-m-d');
        $inicioDia6Prev = new \DateTimeImmutable($hoje . ' 06:00:00');
        $fimDiaPrev     = new \DateTimeImmutable($amanha . ' 03:00:00');

        $prog = DB::table('programacoes as p')
            ->join('calendarios as cal', 'cal.linha_id', '=', 'p.linha_id')
            ->where('p.linha_id', $linhaId)
            ->where('p.status', 'confirmada')
            ->select('p.id', 'p.dias_selecionados', 'p.eficiencia', 'cal.id as cal_id')
            ->first();

        $prevXReal = null;
        if ($prog && $minProd > 0) {
            $diasSel = json_decode($prog->dias_selecionados, true);
            $calId   = $prog->cal_id;
            $eficienciaProg = max(0.0, (float) $prog->eficiencia) / 100;
            $ritmoLinha     = $cxProd / ($minProd / 60);

            // Jornada inteira com fix overnight
            $diasSelFixo = $diasSel;
            $turnosHoje  = $diasSel[$hoje]['turnos'] ?? [];
            if (!empty($turnosHoje)) {
                $ids = implode(',', array_map('intval', $turnosHoje));
                $ivs = DB::select("SELECT id, hora_inicio, hora_fim FROM intervalos WHERE id IN ({$ids})");
                $turnosOvernight = array_filter($ivs, fn ($i) => $i->hora_fim <= '06:00' || $i->hora_inicio >= '22:00');
                if (!empty($turnosOvernight) && !isset($diasSelFixo[$amanha])) {
                    $diasSelFixo[$amanha] = [
                        'dia_semana' => (int) (new \DateTimeImmutable($amanha))->format('N'),
                        'turnos'     => array_map(fn ($i) => $i->id, array_values($turnosOvernight)),
                    ];
                }
            }
            $minJornada = $calendarioService->minutosUteisEntre($inicioDia6Prev, $fimDiaPrev, $calId, $diasSelFixo);
            $capacidade = (int) round($ritmoLinha * $minJornada / 60);

            // SomaOps proporcional
            $ops = DB::table('itens_programacao as ip')
                ->join('codi_eficiencia as ce', function ($j) use ($prog) {
                    $j->on('ce.numero_op', '=', 'ip.numero_op')
                      ->where('ce.programacao_id', '=', $prog->id);
                })
                ->leftJoin('produtos as prod', 'prod.sku', '=', 'ip.sku')
                ->where('ip.programacao_id', $prog->id)
                ->where('ce.fim_previsto', '>', $hoje . ' 06:00:00')
                ->where('ce.inicio_previsto', '<', $amanha . ' 03:00:00')
                ->select('ip.quantidade', 'ce.inicio_previsto', 'ce.fim_previsto', 'prod.taxa_por_hora')
                ->get();

            $somaOps = 0;
            foreach ($ops as $op) {
                $ini = new \DateTimeImmutable($op->inicio_previsto);
                $fim = new \DateTimeImmutable($op->fim_previsto);
                $iniCalc = $ini < $inicioDia6Prev ? $inicioDia6Prev : $ini;
                $fimCalc = $fim > $fimDiaPrev ? $fimDiaPrev : $fim;
                if ($fimCalc <= $iniCalc) continue;
                $minTotal   = $calendarioService->minutosUteisEntre($ini, $fim, $calId, $diasSel);
                $minOverlap = $calendarioService->minutosUteisEntre($iniCalc, $fimCalc, $calId, $diasSel);
                if ($minTotal <= 0) continue;

                $taxaPorHora = (float) ($op->taxa_por_hora ?? 0);
                if ($taxaPorHora > 0) {
                    $prevCxOp = min((int) $op->quantidade, (int) round($taxaPorHora * $eficienciaProg * $minOverlap / 60));
                } else {
                    $prevCxOp = (int) round($op->quantidade * ($minOverlap / $minTotal));
                }
                $somaOps += $prevCxOp;
            }
            $somaOps   = (int) round($somaOps);
            $prevXReal = $capacidade - $somaOps;
        }

        return [
            'cxProd'      => $cxProd,
            'ritmo'       => $ritmo,
            'prevXReal'   => $prevXReal,
            'minProdReal' => $minProdReal,
            'minParado'   => $minParado,
        ];
    }

    /**
     * Fonte única de classificação em tempo real de uma linha — mesma lógica
     * usada pela TV (resources/views/tv/static.blade.php): olha o último
     * evento CODI (qualquer tipo) da linha e aplica a prioridade Parada
     * Programada > Intervalo > Troca de Kit > Troca de Líquido > Desconexão
     * (sem sinal há 15 min) > ritmo atrasado (prevXReal < 0) > classificação
     * original (baseada em fila de OPs, calculada em carregarLinhas()).
     *
     * Chamada tanto por carregarLinhas() (card grid + TV, via $this->linhas)
     * quanto por getPerformanceLinhas() (tabela de performance do dashboard)
     * — garante que as três telas nunca mais divirjam entre si.
     *
     * @return array{cor: string, estado: string, atrasada: bool, tempo_atraso_min: ?int}
     */
    private function reclassificarComSinaisTempoReal(
        ?string $codigoRecurso,
        ?float $prevXReal,
        float $ritmo,
        ?float $atrasoInicioMin,
        string $corOriginal,
        string $estadoOriginal,
        ?string $opAtualNumero = null
    ): array {
        if ($codigoRecurso === null) {
            return ['cor' => $corOriginal, 'estado' => $estadoOriginal, 'atrasada' => false, 'tempo_atraso_min' => null];
        }

        $ultimoEvento = DB::table('codi_eventos')
            ->where('codigo_recurso', $codigoRecurso)
            ->orderByDesc('inicio_evento')
            ->first(['tipo_evento', 'dados_raw', 'fim_evento', 'inicio_evento']);

        $ehParadaProgramada = false;
        $ehIntervalo        = false;
        $ehTrocaKit         = false;
        $ehTrocaLiquido     = false;
        $ehDesconexaoEvento = false;

        if ($ultimoEvento && $ultimoEvento->tipo_evento === 'PARADA') {
            $raw        = is_array($ultimoEvento->dados_raw) ? $ultimoEvento->dados_raw : json_decode($ultimoEvento->dados_raw ?? '{}', true);
            $nomeParada = strtoupper(data_get($raw, 'parada.nomeParada', ''));
            $tipoParada = data_get($raw, 'parada.tipoParada.nomeTipoParada', '');
            $ehParadaProgramada = str_contains($nomeParada, 'PARADA PROGRAMADA');
            $ehIntervalo        = str_contains($tipoParada, 'Intervalo');
            $ehTrocaKit         = str_contains($nomeParada, 'TROCA DE KIT');
            $ehTrocaLiquido     = str_contains($nomeParada, 'TROCA DE LIQUIDO');
            $ehDesconexaoEvento = str_contains($nomeParada, 'DESCONEX');
        }

        // Desconexão automática: sem evento (qualquer tipo) nos últimos 15 min
        // — mesma lógica da TV (static.blade.php).
        $ultimoEventoTs = $ultimoEvento
            ? Carbon::parse($ultimoEvento->fim_evento ?? $ultimoEvento->inicio_evento)
            : null;
        $semSinalHa15min = $ultimoEventoTs === null || $ultimoEventoTs->diffInMinutes(now()) >= 15;
        $ultimoEventoAberto = $ultimoEvento
            && $ultimoEvento->tipo_evento === 'PRODUCAO'
            && $ultimoEvento->fim_evento === null;
        $ehDesconexao = $ehDesconexaoEvento || ($semSinalHa15min && !$ultimoEventoAberto);

        // Se a OP atual começou dentro da própria janela prevista, a linha não
        // é considerada atrasada por ritmo — mesmo que o Prev.xReal do dia
        // esteja negativo agora (ex.: ritmo ainda baixo logo após o início,
        // mas o início em si não foi tardio). Mesma regra da TV.
        $inicioDentroDoPlano = false;
        if ($opAtualNumero) {
            $inicioRealOp = DB::table('codi_eventos')
                ->where('codigo_recurso', $codigoRecurso)
                ->where('ordem_producao', $opAtualNumero)
                ->where('tipo_evento', 'PRODUCAO')
                ->min('inicio_evento');

            $previstoOp = DB::table('codi_eficiencia')
                ->where('numero_op', $opAtualNumero)
                ->select('inicio_previsto', 'fim_previsto')
                ->first();

            if ($inicioRealOp && $previstoOp) {
                $inicioDentroDoPlano = Carbon::parse($inicioRealOp)
                    ->between(Carbon::parse($previstoOp->inicio_previsto), Carbon::parse($previstoOp->fim_previsto));
            }
        }

        $atrasada = !$inicioDentroDoPlano && !$ehParadaProgramada && !$ehIntervalo && !$ehTrocaKit && !$ehTrocaLiquido && !$ehDesconexao
            && $prevXReal !== null && $prevXReal < 0;

        $estado = $ehParadaProgramada ? 'Parada Programada'
            : ($ehIntervalo ? 'Intervalo'
            : ($ehTrocaKit ? 'Troca de Kit'
            : ($ehTrocaLiquido ? 'Troca de Líquido'
            : ($ehDesconexao ? 'Desconexão'
            : ($atrasada ? 'Atrasada' : $estadoOriginal)))));

        $cor = $ehParadaProgramada ? 'yellow'
            : ($ehIntervalo ? 'blue'
            : ($ehTrocaKit ? 'orange'
            : ($ehTrocaLiquido ? 'orange'
            : ($ehDesconexao ? 'black'
            : ($atrasada ? 'red' : $corOriginal)))));

        $tempoAtrasoMin = null;
        if ($atrasada) {
            if ($atrasoInicioMin !== null && $atrasoInicioMin > 0) {
                // Prioridade: atraso real de início da OP (igual TV, sem cap).
                $tempoAtrasoMin = (int) $atrasoInicioMin;
            } elseif ($prevXReal !== null && $prevXReal < 0 && $ritmo > 0) {
                // Fallback: tempo equivalente pelo déficit de ritmo (capado em 999).
                $tempoAtrasoMin = min((int) round((abs($prevXReal) / $ritmo) * 60), 999);
            }
        }

        return ['cor' => $cor, 'estado' => $estado, 'atrasada' => $atrasada, 'tempo_atraso_min' => $tempoAtrasoMin];
    }
}
