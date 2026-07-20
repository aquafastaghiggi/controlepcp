<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Linha;
use App\Models\Codi\CodiEvento;
use App\Models\Codi\CodiEficiencia;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RelatorioController extends Controller
{
    public function desempenho(Request $request): \Illuminate\View\View
    {
        return view('relatorios.desempenho', $this->desempenhoData($request));
    }

    public function desempenhoPrint(Request $request): \Illuminate\View\View
    {
        return view('relatorios.desempenho-print', $this->desempenhoData($request));
    }

    public function prazo(Request $request): \Illuminate\View\View
    {
        return view('relatorios.prazo', $this->prazoData($request));
    }

    public function prazoPrint(Request $request): \Illuminate\View\View
    {
        return view('relatorios.prazo-print', $this->prazoData($request));
    }

    private function desempenhoData(Request $request): array
    {
        $linhas     = Linha::where('ativo', true)->orderBy('codigo')->get();
        $linhaId    = $request->input('linha_id');
        $dataInicio = $request->input('data_inicio', today()->subDays(7)->format('Y-m-d'));
        $dataFim    = $request->input('data_fim', today()->format('Y-m-d'));

        $linhasComProg = DB::table('programacoes as p')
            ->join('linhas as l', 'l.id', '=', 'p.linha_id')
            ->whereIn('p.status', ['confirmada', 'arquivada'])
            ->when($linhaId, fn ($q) => $q->where('p.linha_id', $linhaId))
            ->select('p.linha_id', 'l.nome as linha_nome', 'l.codigo as linha_codigo')
            ->distinct()
            ->get();

        $resultado = [];

        foreach ($linhasComProg as $linhaRow) {
            $lId         = $linhaRow->linha_id;
            $linhaNome   = $linhaRow->linha_nome;
            $linhaCodigo = $linhaRow->linha_codigo;

            $numerosConfirmados = DB::table('itens_programacao as ip')
                ->join('programacoes as p', 'p.id', '=', 'ip.programacao_id')
                ->where('p.linha_id', $lId)
                ->where('p.status', 'confirmada')
                ->whereNotNull('ip.numero_op')
                ->pluck('ip.numero_op')
                ->toArray();

            if (empty($numerosConfirmados)) {
                continue;
            }

            $numerosRealizado = DB::table('itens_programacao as ip')
                ->join('programacoes as p', 'p.id', '=', 'ip.programacao_id')
                ->where('p.linha_id', $lId)
                ->whereIn('p.status', ['confirmada', 'arquivada'])
                ->whereNotNull('ip.numero_op')
                ->pluck('ip.numero_op')
                ->toArray();

            $previsto = (float) (DB::table('itens_programacao as ip')
                ->join('programacoes as p', 'p.id', '=', 'ip.programacao_id')
                ->join('codi_eficiencia as ce', function ($join) {
                    $join->on('ce.numero_op', '=', 'ip.numero_op')
                         ->on('ce.programacao_id', '=', 'p.id');
                })
                ->where('p.linha_id', $lId)
                ->where('p.status', 'confirmada')
                ->whereNotNull('ip.numero_op')
                ->where('ce.inicio_previsto', '<=', $dataFim . ' 23:59:59')
                ->where(function ($q) use ($dataInicio) {
                    $q->whereNull('ce.fim_previsto')
                      ->orWhere('ce.fim_previsto', '>=', $dataInicio . ' 00:00:00');
                })
                ->selectRaw("
                    SUM(
                        ip.quantidade *
                        LEAST(
                            TIMESTAMPDIFF(MINUTE,
                                GREATEST(ce.inicio_previsto, ?),
                                LEAST(IFNULL(ce.fim_previsto, ?), ?)
                            ), 1440 * DATEDIFF(?, ?) + 1440
                        ) /
                        NULLIF(TIMESTAMPDIFF(MINUTE, ce.inicio_previsto, ce.fim_previsto), 0)
                    ) as previsto_calc
                ", [
                    $dataInicio . ' 00:00:00',
                    $dataFim . ' 23:59:59',
                    $dataFim . ' 23:59:59',
                    $dataFim,
                    $dataInicio,
                ])
                ->value('previsto_calc') ?? 0);

            $previsto = (int) round($previsto);

            $realizado = (int) CodiEvento::where('tipo_evento', 'PRODUCAO')
                ->whereBetween('inicio_evento', [
                    $dataInicio . ' 00:00:00',
                    $dataFim . ' 23:59:59',
                ])
                ->whereIn('ordem_producao', $numerosRealizado)
                ->sum('quantidade');

            $atrasoMedio = CodiEficiencia::whereIn('numero_op', $numerosConfirmados)
                ->whereNotNull('inicio_real')
                ->whereNotNull('inicio_previsto')
                ->selectRaw('AVG(TIMESTAMPDIFF(MINUTE, inicio_previsto, inicio_real)) as media')
                ->value('media');

            $oeeMedio = CodiEficiencia::whereIn('numero_op', $numerosConfirmados)
                ->whereNotNull('oee')
                ->where('calculado_em', '>=', $dataInicio)
                ->avg('oee');

            $resultado[] = [
                'linha'        => $linhaCodigo . ' — ' . $linhaNome,
                'codigo'       => $linhaCodigo,
                'total_ops'    => count($numerosConfirmados),
                'previsto'     => $previsto,
                'realizado'    => $realizado,
                'pct'          => $previsto > 0 ? round($realizado / $previsto * 100, 1) : null,
                'atraso_medio' => $atrasoMedio !== null ? (int) round((float) $atrasoMedio) : null,
                'oee_medio'    => $oeeMedio !== null ? round((float) $oeeMedio, 1) : null,
            ];
        }

        usort($resultado, fn ($a, $b) => strcmp($a['codigo'], $b['codigo']));

        return [
            'linhas'     => $linhas,
            'resultado'  => $resultado,
            'linhaId'    => $linhaId,
            'dataInicio' => $dataInicio,
            'dataFim'    => $dataFim,
            'gerado'     => now()->format('d/m/Y H:i'),
        ];
    }

    private function prazoData(Request $request): array
    {
        $linhas     = Linha::where('ativo', true)->orderBy('codigo')->get();
        $linhaId    = $request->input('linha_id');
        $dataInicio = $request->input('data_inicio', today()->subDays(30)->format('Y-m-d'));
        $dataFim    = $request->input('data_fim', today()->format('Y-m-d'));

        $ops = DB::table('codi_eficiencia as ce')
            ->join('itens_programacao as ip', 'ip.numero_op', '=', 'ce.numero_op')
            ->join('programacoes as p', function ($join) {
                $join->on('p.id', '=', 'ip.programacao_id')
                     ->on('p.id', '=', 'ce.programacao_id');
            })
            ->join('linhas as l', 'l.id', '=', 'p.linha_id')
            ->leftJoin('produtos as pr', 'pr.sku', '=', 'ip.sku')
            ->whereIn('p.status', ['confirmada', 'arquivada'])
            ->whereNotNull('ce.fim_real')
            ->whereBetween('ce.fim_real', [
                $dataInicio . ' 00:00:00',
                $dataFim . ' 23:59:59',
            ])
            ->when($linhaId, fn ($q) => $q->where('p.linha_id', $linhaId))
            ->whereRaw('p.id = (
                SELECT p2.id FROM programacoes p2
                JOIN itens_programacao ip2 ON ip2.programacao_id = p2.id
                JOIN codi_eficiencia ce2 ON ce2.programacao_id = p2.id AND ce2.numero_op = ip2.numero_op
                WHERE ip2.numero_op = ce.numero_op
                  AND p2.status IN ("confirmada", "arquivada")
                ORDER BY p2.created_at DESC
                LIMIT 1
            )')
            ->select(
                'l.codigo as linha',
                'ce.numero_op',
                'ip.sku',
                'pr.descricao as produto',
                'ip.descricao_produto',
                'ip.quantidade',
                'ce.quantidade_realizada',
                'ce.inicio_previsto',
                'ce.fim_previsto',
                'ce.inicio_real',
                'ce.fim_real',
                DB::raw('TIMESTAMPDIFF(MINUTE, ce.fim_previsto, ce.fim_real) as desvio_min')
            )
            ->orderBy('l.codigo')
            ->orderBy('ce.fim_real')
            ->get();

        $resumoPorLinha = $ops->groupBy('linha')->map(fn ($grupo) => [
            'linha'        => $grupo->first()->linha,
            'total'        => $grupo->count(),
            'no_prazo'     => $grupo->where('desvio_min', '<=', 0)->count(),
            'atrasadas'    => $grupo->where('desvio_min', '>', 0)->count(),
            'atraso_medio' => $grupo->where('desvio_min', '>', 0)->avg('desvio_min'),
        ])->values();

        $totais = [
            'total'        => $ops->count(),
            'no_prazo'     => $ops->where('desvio_min', '<=', 0)->count(),
            'atrasadas'    => $ops->where('desvio_min', '>', 0)->count(),
            'pct_prazo'    => $ops->count() > 0
                ? round($ops->where('desvio_min', '<=', 0)->count() / $ops->count() * 100, 1)
                : null,
            'maior_atraso' => $ops->where('desvio_min', '>', 0)->sortByDesc('desvio_min')->first(),
        ];

        return compact('linhas', 'ops', 'resumoPorLinha', 'totais', 'linhaId', 'dataInicio', 'dataFim');
    }

    public function setup(Request $request): \Illuminate\View\View
    {
        return view('relatorios.setup', $this->setupData($request));
    }

    public function setupPrint(Request $request): \Illuminate\View\View
    {
        return view('relatorios.setup-print', $this->setupData($request));
    }

    private function setupData(Request $request): array
    {
        $linhas     = Linha::where('ativo', true)->orderBy('codigo')->get();
        $linhaId    = $request->input('linha_id');
        $dataInicio = $request->input('data_inicio', today()->subDays(30)->format('Y-m-d'));
        $dataFim    = $request->input('data_fim', today()->format('Y-m-d'));

        $setups = DB::table('resultado_sequencia as rs')
            ->join('programacoes as p', 'p.id', '=', 'rs.programacao_id')
            ->join('linhas as l', 'l.id', '=', 'p.linha_id')
            ->where('rs.tipo', 'setup')
            ->where('rs.duracao_minutos', '>', 0)
            ->whereIn('p.status', ['confirmada', 'arquivada'])
            ->whereBetween('rs.inicio', [
                $dataInicio . ' 00:00:00',
                $dataFim . ' 23:59:59',
            ])
            ->when($linhaId, fn ($q) => $q->where('p.linha_id', $linhaId))
            ->select('l.codigo as linha', 'rs.duracao_minutos')
            ->get();

        $producao = DB::table('resultado_sequencia as rs')
            ->join('programacoes as p', 'p.id', '=', 'rs.programacao_id')
            ->join('linhas as l', 'l.id', '=', 'p.linha_id')
            ->where('rs.tipo', 'producao')
            ->where('rs.duracao_minutos', '>', 0)
            ->whereIn('p.status', ['confirmada', 'arquivada'])
            ->whereBetween('rs.inicio', [
                $dataInicio . ' 00:00:00',
                $dataFim . ' 23:59:59',
            ])
            ->when($linhaId, fn ($q) => $q->where('p.linha_id', $linhaId))
            ->select('l.codigo as linha', 'rs.duracao_minutos')
            ->get()
            ->groupBy('linha')
            ->map(fn ($g) => (int) $g->sum('duracao_minutos'));

        $setupRealizado = DB::table('codi_eventos as ce')
            ->join('linhas as l', DB::raw('CAST(REGEXP_SUBSTR(l.codigo, "[0-9]+") AS UNSIGNED)'), '=', DB::raw('CAST(ce.codigo_recurso AS UNSIGNED)'))
            ->where('ce.tipo_evento', 'PARADA')
            ->whereRaw('JSON_UNQUOTE(JSON_EXTRACT(ce.dados_raw, "$.parada.nomeParada")) IN ("TROCA DE LIQUIDO", "TROCA DE KIT")')
            ->whereBetween('ce.inicio_evento', [
                $dataInicio . ' 00:00:00',
                $dataFim . ' 23:59:59',
            ])
            ->when($linhaId, fn ($q) => $q->where('l.id', $linhaId))
            ->select(
                'l.codigo as linha',
                'ce.codigo_recurso',
                'ce.inicio_evento',
                'ce.fim_evento',
                DB::raw('TIMESTAMPDIFF(MINUTE, ce.inicio_evento, ce.fim_evento) as duracao_min'),
                DB::raw('JSON_UNQUOTE(JSON_EXTRACT(ce.dados_raw, "$.parada.nomeParada")) as nome_parada')
            )
            ->get()
            ->groupBy('linha')
            ->map(fn ($g) => [
                'total_min'   => (int) $g->sum('duracao_min'),
                'total_horas' => round($g->sum('duracao_min') / 60, 1),
                'qtd'         => $g->count(),
                'media_min'   => $g->count() > 0 ? (int) round($g->avg('duracao_min')) : 0,
                'detalhes'    => $g->map(fn ($e) => [
                    'inicio'      => $e->inicio_evento,
                    'fim'         => $e->fim_evento,
                    'duracao'     => (int) $e->duracao_min,
                    'nome_parada' => $e->nome_parada,
                ])->sortBy('inicio')->values()->toArray(),
            ]);

        $setupPlanejadoDetalhe = DB::table('resultado_sequencia as rs')
            ->join('programacoes as p', 'p.id', '=', 'rs.programacao_id')
            ->join('linhas as l', 'l.id', '=', 'p.linha_id')
            ->where('rs.tipo', 'setup')
            ->where('rs.duracao_minutos', '>', 0)
            ->whereIn('p.status', ['confirmada', 'arquivada'])
            ->whereBetween('rs.inicio', [
                $dataInicio . ' 00:00:00',
                $dataFim . ' 23:59:59',
            ])
            ->when($linhaId, fn ($q) => $q->where('p.linha_id', $linhaId))
            ->select('l.codigo as linha', 'rs.sku', 'rs.inicio', 'rs.fim', 'rs.duracao_minutos')
            ->orderBy('rs.inicio')
            ->get()
            ->groupBy('linha')
            ->map(fn ($g) => $g->map(fn ($r) => [
                'inicio'   => $r->inicio,
                'fim'      => $r->fim,
                'duracao'  => (int) $r->duracao_minutos,
                'sku'      => $r->sku,
            ])->values()->toArray());

        $porLinha = $setups->groupBy('linha')->map(function ($grupo) use ($producao, $setupRealizado, $setupPlanejadoDetalhe) {
            $linha    = $grupo->first()->linha;
            $setupMin = (int) $grupo->sum('duracao_minutos');
            $prodMin  = (int) ($producao[$linha] ?? 0);
            $totalMin = $setupMin + $prodMin;
            $pct      = $totalMin > 0 ? round($setupMin / $totalMin * 100, 1) : null;
            $real     = $setupRealizado[$linha] ?? null;

            return [
                'linha'          => $linha,
                'prod_horas'     => round($prodMin / 60, 1),
                'setup_horas'    => round($setupMin / 60, 1),
                'total_horas'    => round($totalMin / 60, 1),
                'setup_min'      => $setupMin,
                'prod_min'       => $prodMin,
                'total_min'      => $totalMin,
                'qtd_trocas'     => $grupo->count(),
                'media_min'      => $grupo->count() > 0 ? (int) round($grupo->avg('duracao_minutos')) : 0,
                'pct_setup'      => $pct,
                'real_horas'     => $real ? $real['total_horas'] : null,
                'real_min'       => $real ? $real['total_min'] : null,
                'real_qtd'       => $real ? $real['qtd'] : null,
                'real_media'     => $real ? $real['media_min'] : null,
                'real_detalhes'  => $real ? $real['detalhes'] : [],
                'plan_detalhes'  => $setupPlanejadoDetalhe[$linha] ?? [],
                'desvio_min'     => $real ? ($real['total_min'] - $setupMin) : null,
            ];
        })->sortBy('linha')->values();

        $totais = [
            'prod_horas'  => round($porLinha->sum('prod_min') / 60, 1),
            'setup_horas' => round($porLinha->sum('setup_min') / 60, 1),
            'total_horas' => round($porLinha->sum('total_min') / 60, 1),
            'qtd_trocas'  => $porLinha->sum('qtd_trocas'),
            'media_min'   => $porLinha->count() > 0
                ? (int) round($porLinha->avg('media_min'))
                : 0,
            'pct_setup'   => $porLinha->sum('total_min') > 0
                ? round($porLinha->sum('setup_min') / $porLinha->sum('total_min') * 100, 1)
                : null,
        ];

        return compact('linhas', 'porLinha', 'totais', 'linhaId', 'dataInicio', 'dataFim');
    }
}
