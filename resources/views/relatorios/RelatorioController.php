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
}
