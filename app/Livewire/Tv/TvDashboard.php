<?php

declare(strict_types=1);

namespace App\Livewire\Tv;

use App\Livewire\Dashboard\AcompanharProducao;
use Livewire\Component;

class TvDashboard extends Component
{
    public array  $linhas          = [];
    public array  $kpis            = [];
    public string $ultimoSync      = 'nunca';
    public array  $linhasComParada = [];

    public function mount(): void
    {
        $this->carregarDados();
        $this->verificarParadas();
    }

    public function verificarParadas(): void
    {
        $abertas = \Illuminate\Support\Facades\DB::table('codi_eventos as ce')
            ->join('itens_programacao as ip', 'ip.numero_op', '=', 'ce.ordem_producao')
            ->join('programacoes as p', 'p.id', '=', 'ip.programacao_id')
            ->join('linhas as l', 'l.id', '=', 'p.linha_id')
            ->where('ce.tipo_evento', 'PARADA')
            ->whereNull('ce.fim_evento')
            ->where('p.status', 'confirmada')
            ->whereRaw("UPPER(JSON_UNQUOTE(JSON_EXTRACT(ce.dados_raw, '$.parada.nomeParada'))) LIKE '%MICRO%'")
            ->pluck('l.codigo')
            ->unique()
            ->toArray();

        $this->linhasComParada = $abertas;
    }

    public function carregarDados(): void
    {
        $painel = new AcompanharProducao();
        $painel->carregarDados();

        $this->linhas     = $painel->linhas;
        $this->ultimoSync = $painel->ultimoSync;

        // Total produzido hoje por linha (desde 06:00)
        $inicioDia6 = \Carbon\Carbon::today()->setHour(6)->setMinute(0)->setSecond(0);

        $linhasComRecurso = \Illuminate\Support\Facades\DB::table('linhas')
            ->whereNotNull('codigo_recurso')
            ->where('ativo', true)
            ->get(['id', 'codigo_recurso']);

        $produzidoHojePorLinha = collect();
        foreach ($linhasComRecurso as $linhaRec) {
            $total = \Illuminate\Support\Facades\DB::table('codi_eventos')
                ->where('codigo_recurso', $linhaRec->codigo_recurso)
                ->where('tipo_evento', 'PRODUCAO')
                ->where('inicio_evento', '>=', $inicioDia6)
                ->sum('quantidade');
            $produzidoHojePorLinha[$linhaRec->id] = $total;
        }

        $this->linhas = array_map(function ($linha) use ($produzidoHojePorLinha) {
            $linha['total_hoje'] = (int) ($produzidoHojePorLinha[$linha['id']] ?? 0);
            return $linha;
        }, $this->linhas);

        // Mescla kpis do AcompanharProducao com aliases esperados pela view TV
        $this->kpis = array_merge($painel->kpis, [
            'total_linhas'  => $painel->kpis['linhas_ativas']    ?? 0,
            'em_alerta'     => $painel->kpis['linhas_em_alerta'] ?? 0,
            'producao_hoje' => $painel->kpis['produzido_hoje']   ?? 0,
        ]);

        // Projeção linha a linha pelo ritmo médio desde 06:00
        $inicioDia6     = \Carbon\Carbon::today()->setHour(6)->setMinute(0)->setSecond(0);
        $fimDiaUtil     = \Carbon\Carbon::today()->setHour(22)->setMinute(0)->setSecond(0);
        $agora          = \Carbon\Carbon::now();
        $horasRestantes = max(0, $agora->diffInMinutes($fimDiaUtil) / 60);

        $linhasAtivas = \Illuminate\Support\Facades\DB::table('linhas')
            ->whereNotNull('codigo_recurso')
            ->where('ativo', true)
            ->get(['id', 'codigo', 'codigo_recurso']);

        $projecaoTotal = 0;
        foreach ($linhasAtivas as $linhaItem) {
            $prodLinha = (float) \Illuminate\Support\Facades\DB::table('codi_eventos')
                ->where('codigo_recurso', $linhaItem->codigo_recurso)
                ->where('tipo_evento', 'PRODUCAO')
                ->where('inicio_evento', '>=', $inicioDia6)
                ->sum('quantidade');

            $horasDecorridas = max(0.1, $inicioDia6->diffInMinutes($agora) / 60);
            $ritmoMedio      = $prodLinha / $horasDecorridas;
            $projecaoTotal  += $prodLinha + ($ritmoMedio * $horasRestantes);
        }

        $producaoHoje = $this->kpis['produzido_hoje'] ?? 0;
        $totalProg    = $this->kpis['previsto_hoje'] ?? 0;
        $projecao     = (int) round($projecaoTotal);
        $pctProj      = $totalProg > 0 ? round($projecao / $totalProg * 100, 1) : 0;
        $diferenca    = $projecao - $totalProg;

        $this->kpis['projecao']  = $projecao;
        $this->kpis['pct_proj']  = $pctProj;
        $this->kpis['diferenca'] = $diferenca;

        // Total de paradas do dia (desde 06:00) — todas as linhas confirmadas
        $inicioDia = \Carbon\Carbon::today()->setHour(6)->setMinute(0)->setSecond(0);

        $totalParadaMin = (int) \Illuminate\Support\Facades\DB::table('codi_eventos as ce')
            ->join('itens_programacao as ip', 'ip.numero_op', '=', 'ce.ordem_producao')
            ->join('programacoes as p', 'p.id', '=', 'ip.programacao_id')
            ->where('p.status', 'confirmada')
            ->where('ce.tipo_evento', 'PARADA')
            ->where('ce.inicio_evento', '>=', $inicioDia)
            ->whereRaw("TIMESTAMPDIFF(MINUTE, ce.inicio_evento, IFNULL(ce.fim_evento, NOW())) < 240")
            ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(ce.dados_raw, '$.parada.tipoParada.nomeTipoParada')) != 'Intervalo'")
            ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(ce.dados_raw, '$.parada.nomeParada')) != 'PARADA PROGRAMADA'")
            ->selectRaw("SUM(TIMESTAMPDIFF(MINUTE, ce.inicio_evento, IFNULL(ce.fim_evento, NOW()))) as total")
            ->value('total');

        $this->kpis['total_parada_min'] = $totalParadaMin;
    }

    public function render(): \Illuminate\View\View
    {
        return view('livewire.tv.tv-dashboard')
            ->layout('layouts.tv');
    }
}
