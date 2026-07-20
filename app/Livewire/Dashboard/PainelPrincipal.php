<?php

declare(strict_types=1);

namespace App\Livewire\Dashboard;

use App\Models\Codi\CodiPerformance;
use App\Models\Feriado;
use App\Models\Produto;
use App\Models\Programacao;
use Carbon\Carbon;
use Livewire\Component;

class PainelPrincipal extends Component
{
    public array  $metricas            = [];
    public array  $ultimasProgramacoes = [];
    public ?array $proximoFeriado      = null;
    public array  $programacoesMes     = [];
    public array  $oeeTempoReal        = [];
    public string $ultimaSincCodiPerformance = '';
    public string $ultimaSincCodiEventos     = '';

    public array  $eventosModal = [];

    public function abrirEventosOp(string $numeroOp, string $descricao): void
    {
        // Buscar setup previsto para esta OP
        $setupPrevisto = null;
        $itemAtual = \App\Models\ItemProgramacao::where('numero_op', $numeroOp)->first();
        if ($itemAtual) {
            $itemAnterior = \App\Models\ItemProgramacao::where('programacao_id', $itemAtual->programacao_id)
                ->where('sequencia', $itemAtual->sequencia - 1)
                ->first();
            if ($itemAnterior) {
                $setup = \App\Models\MatrizSetup::where('sku_origem', $itemAnterior->sku)
                    ->where('sku_destino', $itemAtual->sku)
                    ->first();
                $setupPrevisto = $setup?->duracao_minutos;
            }
        }

        $this->eventosModal = \App\Models\Codi\CodiEvento::where('ordem_producao', $numeroOp)
            ->where(function ($q) {
                $q->where('tipo_evento', 'PRODUCAO')
                  ->orWhere(function ($q2) {
                      $q2->where('tipo_evento', 'PARADA')
                         ->where('duracao_minutos', '<', 240);
                  });
            })
            ->orderBy('inicio_evento')
            ->get()
            ->map(function ($e) use ($setupPrevisto) {
                $raw = is_array($e->dados_raw)
                    ? $e->dados_raw
                    : json_decode($e->dados_raw ?? '{}', true);

                $nomeParada = $raw['parada']['nomeParada'] ?? null;
                $tipoParada = $raw['parada']['tipoParada']['nomeTipoParada'] ?? null;

                $duracaoMin = isset($raw['duracao'])
                    ? (float) $raw['duracao']
                    : (int) ($e->duracao_minutos ?? 0);

                $h = intdiv((int) $duracaoMin, 60);
                $m = (int) round(fmod($duracaoMin, 60));

                return [
                    'tipo'           => $e->tipo_evento,
                    'inicio'         => \Carbon\Carbon::parse($e->inicio_evento)
                                            ->locale('pt_BR')->isoFormat('ddd DD/MM HH:mm'),
                    'fim'            => \Carbon\Carbon::parse($e->fim_evento)
                                            ->locale('pt_BR')->isoFormat('ddd DD/MM HH:mm'),
                    'duracao'        => $h . 'h ' . str_pad((string) $m, 2, '0', STR_PAD_LEFT) . 'min',
                    'quantidade'     => ($qtd = (float) str_replace(',', '.', (string) ($e->quantidade ?? '0'))) > 0
                                            ? number_format($qtd, 0, ',', '.')
                                            : '—',
                    'nome_parada'    => $nomeParada,
                    'tipo_parada'    => $tipoParada,
                    'setup_previsto' => $tipoParada === 'Setup' ? $setupPrevisto : null,
                ];
            })
            ->filter(fn ($e) => $e['tipo_parada'] !== 'Intervalo'
                && strtoupper((string) ($e['nome_parada'] ?? '')) !== 'PARADA PROGRAMADA')
            ->values()
            ->toArray();

        $eventosJson = json_encode($this->eventosModal);
        $opJson      = json_encode($numeroOp);
        $descJson    = json_encode($descricao);

        $this->js("
            window.dispatchEvent(new CustomEvent('abrir-modal-eventos', {
                detail: {
                    op: {$opJson},
                    descricao: {$descJson},
                    eventos: {$eventosJson}
                }
            }));
        ");
    }

    public function mount(): void
    {
        $this->carregarMetricas();
        $this->carregarUltimasProgramacoes();
        $this->carregarProximoFeriado();
        $this->carregarResumoMes();
        $this->carregarOeeTempoReal();
        $this->carregarUltimasSincronizacoes();
    }

    public function carregarMetricas(): void
    {
        $this->metricas = [
            'total_mes'      => Programacao::whereMonth('created_at', now()->month)
                                    ->whereYear('created_at', now()->year)
                                    ->count(),
            'confirmadas'    => Programacao::where('status', 'confirmada')->count(),
            'produtos_ativos' => Produto::where('ativo', true)
                                    ->where('taxa_por_hora', '>', 0)
                                    ->count(),
        ];
    }

    public function carregarUltimasProgramacoes(): void
    {
        $this->ultimasProgramacoes = Programacao::with(['linha', 'itens'])
            ->where('status', 'confirmada')
            ->join('linhas', 'linhas.id', '=', 'programacoes.linha_id')
            ->orderBy('linhas.codigo', 'asc')
            ->select('programacoes.*')
            ->get()
            ->map(fn (Programacao $p) => [
                'id'                    => $p->id,
                'linha'                 => $p->linha?->nome ?? '—',
                'status'                => $p->status,
                'data_inicio_planejada' => $p->data_inicio_planejada?->format('d/m/Y H:i'),
                'total_ops'             => $p->itens->count(),
                'total_cx'              => number_format((float) $p->itens->sum('quantidade'), 0, ',', '.'),
                'eficiencia'            => $p->eficiencia,
            ])
            ->toArray();
    }

    public function carregarProximoFeriado(): void
    {
        $feriado = Feriado::where('data', '>=', now()->toDateString())
            ->orderBy('data')
            ->first();

        if ($feriado) {
            $data = Carbon::parse($feriado->data);
            $this->proximoFeriado = [
                'data'      => $data->format('d/m/Y'),
                'descricao' => $feriado->descricao ?? 'Feriado',
                'dias'      => (int) now()->startOfDay()->diffInDays($data->startOfDay()),
            ];
        }
    }

    public function carregarResumoMes(): void
    {
        $base = Programacao::whereMonth('created_at', now()->month)
                    ->whereYear('created_at', now()->year);

        $this->programacoesMes = [
            'total'      => (clone $base)->count(),
            'confirmadas'=> (clone $base)->where('status', 'confirmada')->count(),
            'calculadas' => (clone $base)->where('status', 'calculada')->count(),
            'rascunhos'  => (clone $base)->where('status', 'rascunho')->count(),
        ];
    }

    private function carregarOeeTempoReal(): void
    {
        $linhasComProgramacao = \App\Models\Linha::whereHas('programacoes', function ($q) {
            $q->where('status', 'confirmada');
        })->get();

        $this->oeeTempoReal = [];

        foreach ($linhasComProgramacao as $linha) {
            $numLinha        = ltrim(str_replace('LN', '', strtoupper($linha->codigo)), '0');
            $nomeRecursoCodi = 'LINHA ' . $numLinha;

            $performance   = CodiPerformance::where('nome_recurso', $nomeRecursoCodi)
                ->orderByDesc('sincronizado_em')
                ->first();
            $codigoRecurso = $performance?->codigo_recurso;

            $programacao = \App\Models\Programacao::where('linha_id', $linha->id)
                ->where('status', 'confirmada')
                ->with(['itens' => fn ($q) => $q->orderBy('sequencia')])
                ->orderByDesc('created_at')
                ->first();

            if (!$programacao) continue;

            $opNums = $programacao->itens->pluck('numero_op')->filter()->toArray();

            $realizadoPorOp = \App\Models\Codi\CodiEvento::whereIn('ordem_producao', $opNums)
                ->where('tipo_evento', 'PRODUCAO')
                ->selectRaw('ordem_producao, SUM(quantidade) as total_realizado')
                ->groupBy('ordem_producao')
                ->pluck('total_realizado', 'ordem_producao')
                ->toArray();

            // Código do recurso CODI para esta linha (necessário para detecção de OP concluída)
            if (!$codigoRecurso) {
                $codigoRecurso = \App\Models\Codi\CodiEvento::whereIn('ordem_producao', $opNums)
                    ->value('codigo_recurso');
            }

            $ops           = [];
            $totalOps      = $programacao->itens->count();
            $opsFinalizadas = 0;
            $opEmAndamento  = null;

            foreach ($programacao->itens as $item) {
                $realizado  = (float) ($realizadoPorOp[$item->numero_op] ?? 0);
                $programado = (float) $item->quantidade;

                // Verifica se o CODI já migrou para outra OP neste recurso (sinal de conclusão real)
                $opConcluida = false;
                if ($codigoRecurso && $realizado > 0) {
                    $ultimoFimOp = \App\Models\Codi\CodiEvento::where('ordem_producao', $item->numero_op)
                        ->max('fim_evento');

                    if ($ultimoFimOp) {
                        $opConcluida = \App\Models\Codi\CodiEvento::where('codigo_recurso', $codigoRecurso)
                            ->where('ordem_producao', '!=', $item->numero_op)
                            ->where('inicio_evento', '>=', $ultimoFimOp)
                            ->exists();
                    }
                }

                if ($realizado <= 0 && !$opConcluida) {
                    $status = 'nao_iniciada';
                    $cor    = 'gray';
                } elseif ($realizado >= $programado || $opConcluida) {
                    $status = $opConcluida && $realizado < $programado ? 'concluida' : 'finalizada';
                    $cor    = 'green';
                    $opsFinalizadas++;
                } else {
                    $status = 'em_andamento';
                    $cor    = 'red';
                    $opEmAndamento = $item->numero_op;
                }

                $ops[] = [
                    'numero_op'  => $item->numero_op,
                    'sequencia'  => $item->sequencia,
                    'sku'        => $item->sku,
                    'descricao'  => $item->descricao_produto,
                    'programado' => $programado,
                    'realizado'  => $realizado,
                    'pct'        => $programado > 0
                        ? min(100, round(($realizado / $programado) * 100))
                        : 0,
                    'status'     => $status,
                    'cor'        => $cor,
                ];
            }

            if ($opsFinalizadas === $totalOps) {
                $corLinha    = 'green';
                $estadoLinha = 'Concluída';
            } elseif ($opEmAndamento) {
                $corLinha    = 'yellow';
                $estadoLinha = 'Produzindo';
            } else {
                $corLinha    = 'gray';
                $estadoLinha = 'Aguardando';
            }

            $ultimoEvento = \App\Models\Codi\CodiEvento::where('codigo_recurso', $codigoRecurso)
                ->whereDate('inicio_evento', today())
                ->orderByDesc('inicio_evento')
                ->first();

            $this->oeeTempoReal[] = [
                'linha'           => $linha->codigo,
                'nome'            => $nomeRecursoCodi,
                'cor'             => $corLinha,
                'estado'          => $estadoLinha,
                'ops'             => $ops,
                'total_ops'       => $totalOps,
                'ops_finalizadas' => $opsFinalizadas,
                'op_andamento'    => $opEmAndamento,
                'sincronizado'    => $performance
                    ? Carbon::parse($performance->sincronizado_em)->locale('pt_BR')->diffForHumans()
                    : 'nunca',
                'ultimo_evento'   => $ultimoEvento
                    ? Carbon::parse($ultimoEvento->inicio_evento)->locale('pt_BR')->isoFormat('HH:mm')
                    : null,
            ];
        }
    }

    /**
     * Dispara sincronização CODI e recarrega o dashboard.
     * Chamado pelo timer do frontend ao zerar.
     */
    public function sincronizarEAtualizar(): void
    {
        // Roda o sync CODI de forma síncrona antes de re-renderizar
        \Artisan::call('codi:sincronizar', ['--tipo' => 'todos']);
        $this->carregarOeeTempoReal();
    }

    private function carregarUltimasSincronizacoes(): void
    {
        $syncPerf = \App\Models\Codi\CodiSincronizacaoLog::where('tipo', 'performance')
            ->where('status', 'sucesso')
            ->orderByDesc('created_at')
            ->first();

        $this->ultimaSincCodiPerformance = $syncPerf
            ? Carbon::parse($syncPerf->created_at)->locale('pt_BR')->diffForHumans()
            : 'nunca';

        $syncEv = \App\Models\Codi\CodiSincronizacaoLog::where('tipo', 'eventos')
            ->where('status', 'sucesso')
            ->orderByDesc('created_at')
            ->first();

        $this->ultimaSincCodiEventos = $syncEv
            ? Carbon::parse($syncEv->created_at)->locale('pt_BR')->diffForHumans()
            : 'nunca';
    }

    public function render()
    {
        return view('livewire.dashboard.painel-principal');
    }
}
