<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class TvStaticController extends Controller
{
    public function index(): View
    {
        return view('tv.static', $this->prepararDados());
    }

    public function index2(): View
    {
        return view('tv.static2', $this->prepararDados());
    }

    private function prepararDados(): array
    {
        $painel = new \App\Livewire\Dashboard\AcompanharProducao();
        $painel->carregarDados();

        $linhas = $painel->linhas;
        $kpis   = $painel->kpis;

        // Total produzido hoje por linha (desde 06:00)
        $inicioDia6 = \Carbon\Carbon::today()->setHour(6)->setMinute(0)->setSecond(0);

        $linhasComRecurso = DB::table('linhas')
            ->whereNotNull('codigo_recurso')
            ->where('ativo', true)
            ->get(['id', 'codigo_recurso']);

        $produzidoHojePorLinha = collect();
        foreach ($linhasComRecurso as $linhaRec) {
            $total = DB::table('codi_eventos')
                ->where('codigo_recurso', $linhaRec->codigo_recurso)
                ->where('tipo_evento', 'PRODUCAO')
                ->where('inicio_evento', '>=', $inicioDia6)
                ->sum('quantidade');
            $produzidoHojePorLinha[$linhaRec->id] = $total;
        }

        $linhas = array_map(function ($linha) use ($produzidoHojePorLinha) {
            $linha['total_hoje'] = (int) ($produzidoHojePorLinha[$linha['id']] ?? 0);
            return $linha;
        }, $linhas);

        // Alteração 1: Previsto dinâmico proporcional via CalendarioService
        $hoje      = \Carbon\Carbon::today();
        $amanha    = $hoje->copy()->addDay();
        $inicioDia = new \DateTimeImmutable($hoje->format('Y-m-d') . ' 06:00:00');
        $fimDia    = new \DateTimeImmutable($amanha->format('Y-m-d') . ' 03:00:00');
        $agora     = \Carbon\Carbon::now();
        $agoraImm  = new \DateTimeImmutable($agora->format('Y-m-d H:i:s'));

        $calendarioService = app(\App\Services\CalendarioService::class);

        $programacoes = \Illuminate\Support\Facades\DB::table('itens_programacao as ip')
            ->join('programacoes as p', 'p.id', '=', 'ip.programacao_id')
            ->join('linhas as l', 'l.id', '=', 'p.linha_id')
            ->leftJoin('codi_eficiencia as ce', function($j) {
                $j->on('ce.numero_op', '=', 'ip.numero_op')
                  ->on('ce.programacao_id', '=', 'p.id');
            })
            ->leftJoin('calendarios as cal', 'cal.linha_id', '=', 'l.id')
            ->leftJoin('produtos as prod', 'prod.sku', '=', 'ip.sku')
            ->where('p.status', 'confirmada')
            ->where('l.ativo', true)
            ->whereNotNull('ce.inicio_previsto')
            ->whereNotNull('ce.fim_previsto')
            ->where('ce.fim_previsto', '>', $hoje->format('Y-m-d') . ' 06:00:00')
            ->where('ce.inicio_previsto', '<', $amanha->format('Y-m-d') . ' 03:00:00')
            ->select('l.id as linha_id', 'l.codigo', 'l.codigo_recurso', 'ip.numero_op',
                     'ip.quantidade', 'ce.inicio_previsto', 'ce.fim_previsto',
                     'p.dias_selecionados', 'p.eficiencia', 'cal.id as calendario_id',
                     'prod.taxa_por_hora')
            ->get();

        // Linhas cujo último evento é uma Parada Programada em andamento — excluídas
        // do cálculo de previsto/dia, já que não vão produzir enquanto durar a parada
        $linhasEmParadaProgramada = DB::table('codi_eventos as ce')
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

        $previstoTotal = 0;
        $projecaoTotal = 0;

        $porLinha = [];
        foreach ($programacoes as $prog) {
            if (in_array($prog->linha_id, $linhasEmParadaProgramada)) continue;
            if (!$prog->calendario_id || !$prog->taxa_por_hora) continue;
            $diasSel    = json_decode($prog->dias_selecionados ?? '[]', true);
            $inicioOp   = new \DateTimeImmutable($prog->inicio_previsto);
            $fimOp      = new \DateTimeImmutable($prog->fim_previsto);
            $inicioCalc = $inicioOp < $inicioDia ? $inicioDia : $inicioOp;
            $fimCalc    = $fimOp > $fimDia ? $fimDia : $fimOp;

            if ($fimCalc <= $inicioCalc) continue;

            try {
                // Janela sempre limitada por $fimDia (03:00 amanhã) — o fallback de
                // diasUteis já corrigido em CalendarioService::resolverTurnosPermitidos()
                // resolve sozinho o(s) turno(s) de amanhã a partir do $diasSel original,
                // sem precisar do override overnight-only (esse só é necessário para
                // janelas não limitadas, como a duração completa de uma OP multi-dia).
                $minUteisDia = $calendarioService->minutosUteisEntre($inicioCalc, $fimCalc, $prog->calendario_id, $diasSel);
                if ($minUteisDia <= 0) continue;

                // Previsto = taxa cadastrada × eficiência da programação × horas úteis
                // na janela de hoje, nunca ultrapassando a quantidade total da própria OP.
                $eficiencia = max(0.0, (float) $prog->eficiencia) / 100;
                $ritmoOp    = (float) $prog->taxa_por_hora * $eficiencia;
                $qtdHoje    = min((int) $prog->quantidade, (int) round($ritmoOp * $minUteisDia / 60));

                $previstoTotal += $qtdHoje;
                $porLinha[$prog->codigo_recurso][] = [
                    'numero_op' => $prog->numero_op,
                    'qtd_hoje'  => $qtdHoje,
                    'calendario_id' => $prog->calendario_id,
                    'dias_selecionados' => $diasSel,
                ];
            } catch (\Throwable $e) {
                continue;
            }
        }

        // Linhas reprogramadas hoje: a programação anterior foi arquivada durante
        // a janela produtiva (ex.: Colemar reagendou por atraso). OPs que rodaram
        // e terminaram sob a agenda antiga podem não aparecer mais na confirmada —
        // soma o que foi REALMENTE produzido dessas OPs desde 06:00, sem duplicar
        // (só entra quem não está na lista já contabilizada acima via a confirmada).
        $numeroOpsContabilizadosPorLinha = [];
        foreach ($programacoes as $prog) {
            $numeroOpsContabilizadosPorLinha[$prog->linha_id][] = $prog->numero_op;
        }

        $itensArquivadosHoje = DB::table('itens_programacao as ip')
            ->join('programacoes as p', 'p.id', '=', 'ip.programacao_id')
            ->join('linhas as l', 'l.id', '=', 'p.linha_id')
            ->where('p.status', 'arquivada')
            ->where('p.arquivada_em', '>=', $inicioDia6)
            ->where('l.ativo', true)
            ->select('l.id as linha_id', 'l.codigo_recurso', 'ip.numero_op')
            ->get();

        $numeroOpsJaSomadosDeArquivada = [];
        foreach ($itensArquivadosHoje as $item) {
            if (in_array($item->linha_id, $linhasEmParadaProgramada)) continue;

            $chave = $item->linha_id . '|' . $item->numero_op;
            if (isset($numeroOpsJaSomadosDeArquivada[$chave])) continue;

            $jaContabilizada = in_array(
                $item->numero_op,
                $numeroOpsContabilizadosPorLinha[$item->linha_id] ?? [],
                true
            );
            if ($jaContabilizada) continue;

            $numeroOpsJaSomadosDeArquivada[$chave] = true;

            $produzidoOp = (int) round((float) DB::table('codi_eventos')
                ->where('codigo_recurso', $item->codigo_recurso)
                ->where('ordem_producao', $item->numero_op)
                ->where('tipo_evento', 'PRODUCAO')
                ->where('inicio_evento', '>=', $inicioDia6)
                ->sum('quantidade'));

            if ($produzidoOp > 0) {
                $previstoTotal += $produzidoOp;
            }
        }

        // Alteração 2: Projeção com CalendarioService por linha
        $inicioDia6 = \Carbon\Carbon::today()->setHour(6)->setMinute(0)->setSecond(0);
        foreach ($porLinha as $codigoRecurso => $ops) {
            $prodLinha = (float) \Illuminate\Support\Facades\DB::table('codi_eventos')
                ->where('codigo_recurso', $codigoRecurso)
                ->where('tipo_evento', 'PRODUCAO')
                ->where('inicio_evento', '>=', $inicioDia6)
                ->sum('quantidade');

            $minRodando = (float) \Illuminate\Support\Facades\DB::table('codi_eventos')
                ->where('codigo_recurso', $codigoRecurso)
                ->where('inicio_evento', '>=', $inicioDia6)
                ->sum('duracao_minutos');

            $horasRodando = max(0.1, $minRodando / 60);
            $ritmo        = $prodLinha / $horasRodando;

            // Horas úteis restantes via CalendarioService
            $op = $ops[0]; // usa calendário/turnos da primeira OP da linha
            try {
                $minRestantes  = $calendarioService->minutosUteisEntre($agoraImm, $fimDia, $op['calendario_id'], $op['dias_selecionados']);
                $horasRestantes = $minRestantes / 60;
            } catch (\Throwable $e) {
                $horasRestantes = 0;
            }

            // Capacidade teórica = já produzido + projeção do ritmo atual pelo
            // tempo restante — equivalente a ritmo × jornada inteira (06:00→03:00).
            $capacidadeTeorica = $prodLinha + ($ritmo * $horasRestantes);

            // Soma proporcional das OPs programadas para hoje nesta linha —
            // já calculada por OP (qtd_hoje) no loop anterior.
            $somaOpsHoje = array_sum(array_column($ops, 'qtd_hoje'));

            $prevXRealVal = $capacidadeTeorica - $somaOpsHoje;

            // Previsão da linha: se atrasada usa a capacidade teórica (menor que
            // o programado); se adiantada, trava no programado (não há OP na fila
            // pra sustentar produção além do somaOpsHoje). Equivalente a
            // min($capacidadeTeorica, $somaOpsHoje), mais claro semanticamente.
            $projecaoLinha = $capacidadeTeorica;

            $projecaoTotal += $projecaoLinha;
        }

        // Previsto/dia novo: taxa × eficiência × horas úteis por OP, substitui o
        // valor travado de kpis_diarios como fonte deste KPI na TV.
        $totalProg = $previstoTotal;
        $projecao  = (int) round($projecaoTotal);
        $pctProj   = $totalProg > 0 ? round($projecao / $totalProg * 100, 1) : 0;
        $diferenca = $projecao - $totalProg;

        $kpis['previsto_hoje'] = $totalProg;
        $kpis['projecao']      = $projecao;
        $kpis['pct_proj']      = $pctProj;
        $kpis['diferenca']     = $diferenca;

        // Recalcula pct_hoje com o novo previsto_hoje — o valor calculado em
        // AcompanharProducao usava kpis_diarios como denominador, que acabou de
        // ser sobrescrito acima e deixaria a % incoerente com o "de X cx" exibido.
        $kpis['pct_hoje'] = $totalProg > 0
            ? round($kpis['produzido_hoje'] / $totalProg * 100, 1)
            : 0;

        // Total paradas
        $inicioDia6 = Carbon::today()->setHour(6)->setMinute(0)->setSecond(0);
        $ops = DB::table('itens_programacao as ip')
            ->join('programacoes as p', 'p.id', '=', 'ip.programacao_id')
            ->join('linhas as l', 'l.id', '=', 'p.linha_id')
            ->where('p.status', 'confirmada')
            ->where('l.ativo', true)
            ->pluck('ip.numero_op')->unique();

        $totalParadaMin = (int) DB::table('codi_eventos')
            ->whereIn('ordem_producao', $ops)
            ->where('tipo_evento', 'PARADA')
            ->where('inicio_evento', '>=', $inicioDia6)
            ->whereRaw("TIMESTAMPDIFF(MINUTE, inicio_evento, IFNULL(fim_evento, NOW())) < 240")
            ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(dados_raw, '$.parada.tipoParada.nomeTipoParada')) != 'Intervalo'")
            ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(dados_raw, '$.parada.nomeParada')) != 'PARADA PROGRAMADA'")
            ->selectRaw("SUM(TIMESTAMPDIFF(MINUTE, inicio_evento, IFNULL(fim_evento, NOW()))) as total")
            ->value('total');

        $kpis['total_parada_min'] = $totalParadaMin;

        // linhasComParada necessário para a view (substitui propriedade Livewire)
        $linhasComParada = [];

        // Resumo "Programação de Produção" — card extra ao lado das linhas,
        // uma linha por programação confirmada (mesmos dados/regras da tela
        // Envase > Histórico, ver ListaProgramacoes::gradeTurnosPorProgramacao()).
        $turnosFixosResumo = [
            ['label' => 'T1', 'inicio' => '07:05', 'fim' => '11:30'],
            ['label' => 'T2', 'inicio' => '13:27', 'fim' => '17:45'],
            ['label' => 'T3', 'inicio' => '17:45', 'fim' => '22:00'],
            ['label' => 'T4', 'inicio' => '23:00', 'fim' => '03:00'],
        ];

        $programacoesResumo = \App\Models\Programacao::with(['linha.calendario.intervalos'])
            ->join('linhas', 'linhas.id', '=', 'programacoes.linha_id')
            ->select('programacoes.*')
            ->where('programacoes.status', 'confirmada')
            ->where('linhas.ativo', true)
            ->orderBy('linhas.codigo')
            ->get();

        $hojeDataResumo = $hoje->format('Y-m-d');
        $hojeIsoResumo  = $hoje->isoWeekday();

        $gradeTurnosResumo = [];
        foreach ($programacoesResumo as $prog) {
            $grade = array_fill(0, count($turnosFixosResumo), false);

            $diasSelecionados = $prog->dias_selecionados ?? [];
            if (! empty($diasSelecionados)) {
                $primeiraChave = (string) array_key_first($diasSelecionados);
                $turnoIdsHoje  = strlen($primeiraChave) === 10
                    ? ($diasSelecionados[$hojeDataResumo]['turnos'] ?? [])
                    : ($diasSelecionados[$hojeIsoResumo] ?? $diasSelecionados[(string) $hojeIsoResumo] ?? []);
                $turnoIdsHoje  = is_array($turnoIdsHoje) ? array_map('intval', $turnoIdsHoje) : [];

                $intervalosLinha = $prog->linha?->calendario?->intervalos ?? collect();
                if (! empty($turnoIdsHoje)) {
                    foreach ($turnosFixosResumo as $indice => $turnoFixo) {
                        $intervalo = $intervalosLinha->first(
                            fn ($int) => substr((string) $int->hora_inicio, 0, 5) === $turnoFixo['inicio']
                                && substr((string) $int->hora_fim, 0, 5) === $turnoFixo['fim']
                        );
                        $grade[$indice] = $intervalo !== null && in_array($intervalo->id, $turnoIdsHoje, true);
                    }
                }
            }

            $gradeTurnosResumo[$prog->id] = $grade;
        }

        // Mesmo total já calculado acima ($totalProg) — evita duplicar a query.
        $producaoPrevistaResumo = $totalProg;

        // Exposto separadamente pro KPI "Total programado" do topo da TV.
        $totalProgramado = $totalProg;

        return compact(
            'linhas', 'kpis', 'linhasComParada',
            'programacoesResumo', 'gradeTurnosResumo', 'turnosFixosResumo', 'producaoPrevistaResumo',
            'totalProgramado'
        );
    }
}
