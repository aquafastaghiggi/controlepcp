<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class TvController extends Controller
{
    public function index()
    {
        $linhas = DB::table('linhas')
            ->where('ativo', true)
            ->whereIn('id', function ($query) {
                $query->select('linha_id')
                    ->from('programacoes')
                    ->where('status', 'confirmada');
            })
            ->orderBy('codigo')
            ->get();

        $dados = $linhas->map(function ($linha) {
            $prog = DB::table('programacoes as p')
                ->join('linhas as l', 'l.id', '=', 'p.linha_id')
                ->where('l.id', $linha->id)
                ->where('p.status', 'confirmada')
                ->select('p.id', 'p.status')
                ->first();

            if (! $prog) {
                return [
                    'codigo' => $linha->codigo,
                    'nome'   => $linha->nome,
                    'status' => 'Aguardando',
                    'cor'    => 'cinza',
                    'op'     => null,
                ];
            }

            $inicioDia = \Carbon\Carbon::today()->setHour(6)->setMinute(0)->setSecond(0);

            // PASSO 1: identificar OP ativa pela que tem evento PRODUCAO mais recente hoje
            $opAtual = DB::table('codi_eventos as ce')
                ->join('itens_programacao as ip', 'ip.numero_op', '=', 'ce.ordem_producao')
                ->where('ip.programacao_id', $prog->id)
                ->where('ce.tipo_evento', 'PRODUCAO')
                ->where('ce.inicio_evento', '>=', $inicioDia)
                ->orderByDesc('ce.inicio_evento')
                ->select('ce.ordem_producao', 'ip.quantidade as qtd_prog',
                         'ip.descricao_produto as produto', 'ip.sku')
                ->first();

            // PASSO 2: se não achou evento hoje, tenta a OP com maior quantidade hoje
            if (!$opAtual) {
                $ops = DB::table('itens_programacao')
                    ->where('programacao_id', $prog->id)
                    ->pluck('numero_op');

                $opComProd = DB::table('codi_eventos')
                    ->whereIn('ordem_producao', $ops)
                    ->where('tipo_evento', 'PRODUCAO')
                    ->where('inicio_evento', '>=', $inicioDia)
                    ->groupBy('ordem_producao')
                    ->orderByDesc(DB::raw('SUM(quantidade)'))
                    ->select('ordem_producao', DB::raw('SUM(quantidade) as total'))
                    ->first();

                if ($opComProd) {
                    $opAtual = DB::table('itens_programacao as ip')
                        ->where('ip.programacao_id', $prog->id)
                        ->where('ip.numero_op', $opComProd->ordem_producao)
                        ->select(DB::raw("'{$opComProd->ordem_producao}' as ordem_producao"),
                                 'ip.quantidade as qtd_prog',
                                 'ip.descricao_produto as produto',
                                 'ip.sku')
                        ->first();
                }
            }

            if (! $opAtual) {
                $proxima = DB::table('itens_programacao as ip')
                    ->join('codi_eficiencia as ce', function ($j) use ($prog) {
                        $j->on('ce.numero_op', '=', 'ip.numero_op')
                          ->where('ce.programacao_id', $prog->id);
                    })
                    ->where('ip.programacao_id', $prog->id)
                    ->where('ce.status', 'pendente')
                    ->select('ip.numero_op', 'ip.quantidade as qtd_prog', 'ip.descricao_produto as produto', 'ip.sku', 'ce.inicio_previsto')
                    ->orderBy('ce.inicio_previsto')
                    ->first();

                return [
                    'codigo'      => $linha->codigo,
                    'nome'        => $linha->nome,
                    'status'      => 'Aguardando',
                    'cor'         => 'cinza',
                    'op'          => $proxima?->numero_op,
                    'produto'     => $proxima?->produto,
                    'sku'         => $proxima?->sku,
                    'qtd_real'    => 0,
                    'qtd_prog'    => (int) ($proxima?->qtd_prog ?? 0),
                    'pct'         => 0,
                    'inicio_prev' => $proxima?->inicio_previsto
                        ? Carbon::parse($proxima->inicio_previsto)->format('H:i')
                        : null,
                    'atraso_min'  => null,
                ];
            }

            $qtdReal = (float) DB::table('codi_eventos')
                ->where('ordem_producao', $opAtual->ordem_producao)
                ->where('tipo_evento', 'PRODUCAO')
                ->where('inicio_evento', '>=', $inicioDia)
                ->sum('quantidade');

            // PASSO 3: buscar dados de eficiência da OP encontrada
            $ef = null;
            if ($opAtual) {
                $ef = DB::table('codi_eficiencia')
                    ->where('numero_op', $opAtual->ordem_producao)
                    ->where('programacao_id', $prog->id)
                    ->orderByDesc('calculado_em')
                    ->first();
            }

            $pct = $opAtual->qtd_prog > 0
                ? round($qtdReal / $opAtual->qtd_prog * 100, 1)
                : 0;

            $atrasMin = null;
            if ($ef?->inicio_real && $ef?->inicio_previsto) {
                $atrasMin = (int) Carbon::parse($ef->inicio_previsto)
                    ->diffInMinutes($ef->inicio_real, false);
            }

            $cor = match (true) {
                $atrasMin === null      => 'cinza',
                $atrasMin <= 15         => 'verde',
                default                 => 'vermelho',
            };

            return [
                'codigo'      => $linha->codigo,
                'nome'        => $linha->nome,
                'status'      => $cor === 'verde' ? 'Em dia' : ($cor === 'vermelho' ? 'Atrasada' : 'Aguardando'),
                'cor'         => $cor,
                'op'          => $opAtual->ordem_producao,
                'produto'     => $opAtual->produto,
                'sku'         => $opAtual->sku,
                'qtd_real'    => (int) round($qtdReal),
                'qtd_prog'    => (int) $opAtual->qtd_prog,
                'pct'         => min(100, $pct),
                'inicio_prev' => $ef?->inicio_previsto
                    ? Carbon::parse($ef->inicio_previsto)->format('d/m H:i')
                    : null,
                'inicio_real' => $ef?->inicio_real
                    ? Carbon::parse($ef->inicio_real)->format('d/m H:i')
                    : null,
                'atraso_min'  => $atrasMin,
            ];
        });

        $producaoHoje = (float) DB::table('codi_eventos as ce')
            ->join('itens_programacao as ip', 'ip.numero_op', '=', 'ce.ordem_producao')
            ->join('programacoes as p', 'p.id', '=', 'ip.programacao_id')
            ->where('p.status', 'confirmada')
            ->where('ce.tipo_evento', 'PRODUCAO')
            ->whereDate('ce.inicio_evento', today())
            ->sum('ce.quantidade');

        $inicioDia = today()->startOfDay();
        $fimDia    = today()->endOfDay();

        $totalProg = (int) DB::table('itens_programacao as ip')
            ->join('programacoes as p', 'p.id', '=', 'ip.programacao_id')
            ->join('codi_eficiencia as ce_plan', function ($j): void {
                $j->on('ce_plan.numero_op', '=', 'ip.numero_op')
                  ->on('ce_plan.programacao_id', '=', 'p.id');
            })
            ->where('p.status', 'confirmada')
            ->where('ce_plan.inicio_previsto', '<=', $fimDia)
            ->where(function ($q) use ($inicioDia): void {
                $q->whereNull('ce_plan.fim_previsto')
                  ->orWhere('ce_plan.fim_previsto', '>=', $inicioDia);
            })
            ->whereNotNull('ip.numero_op')
            ->selectRaw('
                SUM(GREATEST(0,
                    ip.quantidade - COALESCE((
                        SELECT SUM(ce.quantidade)
                        FROM codi_eventos ce
                        WHERE ce.ordem_producao = ip.numero_op
                          AND ce.tipo_evento = "PRODUCAO"
                          AND ce.inicio_evento < ?
                    ), 0)
                )) as saldo
            ', [$inicioDia])
            ->value('saldo') ?? 0;

        $inicioDiaOee = \Carbon\Carbon::today()->setHour(6)->setMinute(0)->setSecond(0);

        $oeeMedio = DB::table(function ($sub) use ($inicioDiaOee) {
            $sub->from('codi_eficiencia as ce')
                ->join('itens_programacao as ip', 'ip.numero_op', '=', 'ce.numero_op')
                ->join('programacoes as p', 'p.id', '=', 'ip.programacao_id')
                ->whereIn('p.status', ['confirmada', 'arquivada'])
                ->whereNotNull('ce.oee')
                ->where('ce.calculado_em', '>=', $inicioDiaOee)
                ->select('ce.numero_op', 'ce.oee')
                ->groupBy('ce.numero_op', 'ce.oee');
        }, 'ops_unicas')->avg('oee');

        $dispMedia = DB::table(function ($sub) use ($inicioDiaOee) {
            $sub->from('codi_eficiencia as ce')
                ->join('itens_programacao as ip', 'ip.numero_op', '=', 'ce.numero_op')
                ->join('programacoes as p', 'p.id', '=', 'ip.programacao_id')
                ->whereIn('p.status', ['confirmada', 'arquivada'])
                ->whereNotNull('ce.disponibilidade')
                ->where('ce.calculado_em', '>=', $inicioDiaOee)
                ->select('ce.numero_op', 'ce.disponibilidade')
                ->groupBy('ce.numero_op', 'ce.disponibilidade');
        }, 'ops_unicas')->avg('disponibilidade');

        $perfMedia = DB::table(function ($sub) use ($inicioDiaOee) {
            $sub->from('codi_eficiencia as ce')
                ->join('itens_programacao as ip', 'ip.numero_op', '=', 'ce.numero_op')
                ->join('programacoes as p', 'p.id', '=', 'ip.programacao_id')
                ->whereIn('p.status', ['confirmada', 'arquivada'])
                ->whereNotNull('ce.performance_tempo')
                ->where('ce.calculado_em', '>=', $inicioDiaOee)
                ->select('ce.numero_op', 'ce.performance_tempo')
                ->groupBy('ce.numero_op', 'ce.performance_tempo');
        }, 'ops_unicas')->avg('performance_tempo');

        $kpis = [
            'total_linhas'  => $linhas->count(),
            'em_alerta'     => $dados->where('cor', 'vermelho')->count(),
            'producao_hoje' => (int) round($producaoHoje),
            'total_prog'    => (int) $totalProg,
            'pct_geral'     => $totalProg > 0 ? round($producaoHoje / $totalProg * 100, 1) : 0,
            'em_dia'        => $dados->where('cor', 'verde')->count(),
            'atrasadas'     => $dados->where('cor', 'vermelho')->count(),
            'aguardando'    => $dados->where('cor', 'cinza')->count(),
            'oee_medio'     => $oeeMedio  !== null ? round((float) $oeeMedio,  1) : null,
            'disp_media'    => $dispMedia !== null ? round((float) $dispMedia, 1) : null,
            'perf_media'    => $perfMedia !== null ? round((float) $perfMedia, 1) : null,
            'qual_media'    => 100.0,
        ];

        return view('tv.index', compact('dados', 'kpis'));
    }
}
