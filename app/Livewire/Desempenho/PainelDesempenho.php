<?php

declare(strict_types=1);

namespace App\Livewire\Desempenho;

use App\Models\Codi\CodiEficiencia;
use App\Models\Programacao;
use App\Services\Codi\EficienciaCalculator;
use Illuminate\View\View;
use Livewire\Component;

class PainelDesempenho extends Component
{
    public ?int   $programacaoId   = null;
    public string $ultimaSincCodi  = '';

    public string $filtroStatus = 'confirmada';

    public array $resumo        = [];
    public array $ops           = [];
    public array $programacoes  = [];

    public function mount(): void
    {
        $sync = \App\Models\Codi\CodiSincronizacaoLog::where('tipo', 'eventos')
            ->where('status', 'sucesso')
            ->orderByDesc('created_at')
            ->first();

        $this->ultimaSincCodi = $sync
            ? 'Última sincronização CODI: ' .
              \Carbon\Carbon::parse($sync->created_at)
                  ->locale('pt_BR')->isoFormat('ddd DD/MM/YYYY [às] HH:mm')
            : 'CODI ainda não sincronizado';

        $this->carregarProgramacoes();

        if ($this->programacaoId) {
            $this->carregarDados();
        }
    }

    public function carregarProgramacoes(): void
    {
        $this->programacoes = Programacao::with('linha')
            ->whereIn('status', $this->filtroStatus === 'todas'
                ? ['confirmada', 'arquivada']
                : [$this->filtroStatus]
            )
            ->join('linhas', 'linhas.id', '=', 'programacoes.linha_id')
            ->orderBy('linhas.codigo', 'asc')
            ->orderByDesc('programacoes.created_at')
            ->select('programacoes.*')
            ->take(30)
            ->get()
            ->map(fn (Programacao $p) => [
                'id'         => $p->id,
                'label'      => $p->linha?->codigo ?? ('#' . $p->id),
                'linha_nome' => $p->linha?->nome ?? '',
                'status'     => $p->status,
                'criada_em'  => $p->created_at?->format('d/m/Y'),
            ])
            ->toArray();
    }

    public function setFiltroStatus(string $status): void
    {
        $this->filtroStatus  = $status;
        $this->programacaoId = null;
        $this->carregarProgramacoes();
    }

    public function updatedProgramacaoId(): void
    {
        $this->carregarDados();
    }

    public function carregarDados(): void
    {
        if (!$this->programacaoId) {
            $this->resumo = [];
            $this->ops    = [];
            return;
        }

        $linhaNome = Programacao::find($this->programacaoId)?->linha?->nome ?? '—';

        $registros = CodiEficiencia::where('programacao_id', $this->programacaoId)
            ->orderBy('numero_op')
            ->get();

        // Primeira vez que esta programação é aberta: popula codi_eficiencia
        // com os dados planejados do PCP (status='pendente'). Sem CODI, mostra
        // o plano; com CODI sincronizado, mostra realizado × planejado.
        if ($registros->isEmpty()) {
            try {
                app(EficienciaCalculator::class)->calcularParaProgramacao($this->programacaoId);
            } catch (\Throwable $e) {
                report($e); // loga sem propagar — tela fica vazia mas o erro é rastreável
            }

            $registros = CodiEficiencia::where('programacao_id', $this->programacaoId)
                ->orderBy('numero_op')
                ->get();
        }

        $descricaoPorOp = \App\Models\ItemProgramacao::where('programacao_id', $this->programacaoId)
            ->pluck('descricao_produto', 'numero_op')
            ->toArray();

        $comDados = $registros->whereNotNull('oee');

        $this->resumo = [
            'oee_medio'       => $comDados->count() > 0
                ? round($comDados->avg('oee'), 1)
                : null,
            'eficiencia_media' => $comDados->count() > 0
                ? round($comDados->avg('eficiencia_quantidade'), 1)
                : null,
            'ops_total'        => $registros->count(),
            'ops_ok'           => $registros->where('status', 'ok')->count(),
            'ops_aviso'        => $registros->where('status', 'aviso')->count(),
            'ops_critico'      => $registros->where('status', 'critico')->count(),
            'ops_pendente'     => $registros->where('status', 'pendente')->count(),
        ];

        $this->ops = $registros->map(fn (CodiEficiencia $e) => [
            'id'                    => $e->id,
            'numero_op'             => $e->numero_op,
            'sku'                   => $e->sku,
            'quantidade_programada' => $e->quantidade_programada,
            'quantidade_realizada'  => $e->quantidade_realizada,
            'desvio_quantidade_pct' => $e->desvio_quantidade_pct,
            'oee'                   => $e->oee,
            'eficiencia_quantidade' => $e->eficiencia_quantidade,
            'disponibilidade'       => $e->disponibilidade,
            'performance_tempo'     => $e->performance_tempo,
            'desvio_prazo_dias'     => $e->desvio_prazo_dias,
            'status'                => $e->status,
            'calculado_em'          => $e->calculado_em?->format('d/m H:i'),
            'descricao_produto'     => $descricaoPorOp[$e->numero_op] ?? null,
            'inicio_real'           => $e->inicio_real?->format('d/m H:i'),
            'fim_previsto'          => $e->fim_previsto?->format('d/m H:i'),
            'fim_real'              => $e->fim_real?->format('d/m H:i'),
            'tempo_prod_min'        => $e->tempo_real_minutos !== null
                ? (int) $e->tempo_real_minutos
                : null,
            'linha_nome'            => $linhaNome,
        ])->toArray();
    }

    public function render(): View
    {
        return view('livewire.desempenho.painel-desempenho');
    }
}
