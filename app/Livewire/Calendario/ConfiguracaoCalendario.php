<?php

declare(strict_types=1);

namespace App\Livewire\Calendario;

use App\Models\Calendario;
use App\Models\DiaUtil;
use App\Models\Feriado;
use App\Models\Intervalo;
use App\Models\Linha;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

/**
 * Interface de configuração do calendário de trabalho de uma linha.
 * Permite visualizar e editar turnos (com dias da semana) e gerenciar feriados.
 */
class ConfiguracaoCalendario extends Component
{
    public ?int  $linhaSelecionada = null;
    public array $linhas           = [];
    public ?array $calendario      = null;
    public array $intervalos       = [];
    public array $feriados         = [];

    // Edição inline de turno
    public ?int  $turnoEditando = null;
    public array $turnoForm     = [
        'nome'        => '',
        'hora_inicio' => '',
        'hora_fim'    => '',
        'ativo'       => true,
        'dias'        => [1, 2, 3, 4, 5],
    ];

    // Adição de novo turno
    public bool  $adicionandoTurno = false;
    public array $novoTurnoForm    = [
        'nome'        => '',
        'hora_inicio' => '',
        'hora_fim'    => '',
        'ativo'       => true,
        'dias'        => [1, 2, 3, 4, 5],
    ];

    // Novo feriado
    public string $feriadoData       = '';
    public string $feriadoDescricao  = '';

    // Feedback inline
    public string $mensagem     = '';
    public string $tipoMensagem = 'sucesso'; // 'sucesso' | 'erro'

    // ─── Inicialização ────────────────────────────────────────────────────────

    public function mount(): void
    {
        $this->linhas = Linha::ativas()
            ->orderBy('codigo')
            ->get()
            ->map(fn ($l) => ['id' => $l->id, 'codigo' => $l->codigo, 'nome' => $l->nome])
            ->toArray();

        if (! empty($this->linhas)) {
            $this->linhaSelecionada = $this->linhas[0]['id'];
            $this->carregarCalendario();
        }
    }

    public function updatedLinhaSelecionada(): void
    {
        $this->turnoEditando = null;
        $this->mensagem      = '';
        $this->carregarCalendario();
    }

    // ─── Carregar dados ───────────────────────────────────────────────────────

    public function carregarCalendario(): void
    {
        if (! $this->linhaSelecionada) {
            $this->calendario = null;
            $this->intervalos = [];
            $this->feriados   = [];
            return;
        }

        $calendario = Calendario::where('linha_id', $this->linhaSelecionada)
            ->with(['intervalos' => fn ($q) => $q->orderBy('ordem'), 'intervalos.diasUteis', 'feriados'])
            ->first();

        if (! $calendario) {
            $this->calendario = null;
            $this->intervalos = [];
            $this->feriados   = [];
            return;
        }

        $this->calendario = ['id' => $calendario->id, 'nome' => $calendario->nome];

        $this->intervalos = $calendario->intervalos->map(fn ($i) => [
            'id'          => $i->id,
            'nome'        => $i->nome,
            'hora_inicio' => $i->hora_inicio,
            'hora_fim'    => $i->hora_fim,
            'ativo'       => $i->ativo,
            'ordem'       => $i->ordem,
            'dias'        => $i->diasUteis->pluck('dia_semana')->sort()->values()->toArray(),
        ])->values()->toArray();

        $this->feriados = $calendario->feriados
            ->sortBy('data')
            ->map(fn ($f) => [
                'id'        => $f->id,
                'data'      => $f->data instanceof \Carbon\Carbon
                    ? $f->data->format('Y-m-d')
                    : (string) $f->data,
                'descricao' => $f->descricao,
            ])
            ->values()
            ->toArray();
    }

    // ─── Edição de turno ─────────────────────────────────────────────────────

    public function editarTurno(int $id): void
    {
        $turno = collect($this->intervalos)->firstWhere('id', $id);
        if (! $turno) {
            return;
        }

        $this->turnoEditando = $id;
        $this->turnoForm     = [
            'nome'        => $turno['nome'],
            'hora_inicio' => substr($turno['hora_inicio'], 0, 5),
            'hora_fim'    => substr($turno['hora_fim'], 0, 5),
            'ativo'       => $turno['ativo'],
            'dias'        => $turno['dias'],
        ];
        $this->mensagem = '';
    }

    public function cancelarEdicao(): void
    {
        $this->turnoEditando = null;
        $this->mensagem      = '';
    }

    public function salvarTurno(): void
    {
        $this->validate([
            'turnoForm.nome'        => 'required|string|max:60',
            'turnoForm.hora_inicio' => 'required',
            'turnoForm.hora_fim'    => 'required',
            'turnoForm.dias'        => 'required|array|min:1',
        ], [
            'turnoForm.nome.required'        => 'Informe o nome do turno.',
            'turnoForm.hora_inicio.required' => 'Informe o horário de início.',
            'turnoForm.hora_fim.required'    => 'Informe o horário de fim.',
            'turnoForm.dias.min'             => 'Selecione pelo menos um dia.',
        ]);

        DB::transaction(function () {
            $intervalo = Intervalo::findOrFail($this->turnoEditando);
            $intervalo->update([
                'nome'        => $this->turnoForm['nome'],
                'hora_inicio' => $this->turnoForm['hora_inicio'],
                'hora_fim'    => $this->turnoForm['hora_fim'],
                'ativo'       => $this->turnoForm['ativo'],
            ]);

            DiaUtil::where('intervalo_id', $intervalo->id)->delete();
            foreach ($this->turnoForm['dias'] as $dia) {
                DiaUtil::create([
                    'intervalo_id' => $intervalo->id,
                    'dia_semana'   => (int) $dia,
                ]);
            }
        });

        $this->turnoEditando = null;
        $this->mensagem      = 'Turno salvo com sucesso.';
        $this->tipoMensagem  = 'sucesso';
        $this->carregarCalendario();
    }

    // ─── Adicionar novo turno ─────────────────────────────────────────────────

    public function iniciarNovoTurno(): void
    {
        $this->adicionandoTurno = true;
        $this->novoTurnoForm    = ['nome' => '', 'hora_inicio' => '', 'hora_fim' => '', 'ativo' => true, 'dias' => [1, 2, 3, 4, 5]];
        $this->turnoEditando    = null;
        $this->mensagem         = '';
    }

    public function cancelarNovoTurno(): void
    {
        $this->adicionandoTurno = false;
    }

    public function salvarNovoTurno(): void
    {
        $this->validate([
            'novoTurnoForm.nome'        => 'required|string|max:60',
            'novoTurnoForm.hora_inicio' => 'required',
            'novoTurnoForm.hora_fim'    => 'required',
            'novoTurnoForm.dias'        => 'required|array|min:1',
        ], [
            'novoTurnoForm.nome.required'        => 'Informe o nome do turno.',
            'novoTurnoForm.hora_inicio.required' => 'Informe o horário de início.',
            'novoTurnoForm.hora_fim.required'    => 'Informe o horário de fim.',
            'novoTurnoForm.dias.min'             => 'Selecione pelo menos um dia.',
        ]);

        $proximaOrdem = \App\Models\Intervalo::where('calendario_id', $this->calendario['id'])
            ->max('ordem') + 1;

        DB::transaction(function () use ($proximaOrdem) {
            $intervalo = \App\Models\Intervalo::create([
                'calendario_id' => $this->calendario['id'],
                'nome'          => $this->novoTurnoForm['nome'],
                'ordem'         => $proximaOrdem,
                'hora_inicio'   => $this->novoTurnoForm['hora_inicio'],
                'hora_fim'      => $this->novoTurnoForm['hora_fim'],
                'ativo'         => $this->novoTurnoForm['ativo'],
            ]);

            foreach ($this->novoTurnoForm['dias'] as $dia) {
                DiaUtil::create([
                    'intervalo_id' => $intervalo->id,
                    'dia_semana'   => (int) $dia,
                ]);
            }
        });

        $this->adicionandoTurno = false;
        $this->novoTurnoForm    = ['nome' => '', 'hora_inicio' => '', 'hora_fim' => '', 'ativo' => true, 'dias' => [1, 2, 3, 4, 5]];
        $this->mensagem         = 'Turno adicionado com sucesso.';
        $this->tipoMensagem     = 'sucesso';
        $this->carregarCalendario();
    }

    // ─── Aplicar a todas as linhas ────────────────────────────────────────────

    public function aplicarTodasLinhas(): void
    {
        $calendarioOrigem = Calendario::where('linha_id', $this->linhaSelecionada)
            ->with(['intervalos.diasUteis'])
            ->firstOrFail();

        $todasLinhas = Linha::where('id', '!=', $this->linhaSelecionada)->get();

        DB::transaction(function () use ($calendarioOrigem, $todasLinhas) {
            foreach ($todasLinhas as $linha) {
                $calendarioDestino = Calendario::firstOrCreate(
                    ['linha_id' => $linha->id],
                    ['nome'     => 'Calendário ' . $linha->codigo]
                );

                // Remove turnos existentes, mantém feriados
                $idsIntervalos = $calendarioDestino->intervalos()->pluck('id');
                DiaUtil::whereIn('intervalo_id', $idsIntervalos)->delete();
                $calendarioDestino->intervalos()->delete();

                foreach ($calendarioOrigem->intervalos as $intervalo) {
                    $novoIntervalo = Intervalo::create([
                        'calendario_id' => $calendarioDestino->id,
                        'nome'          => $intervalo->nome,
                        'ordem'         => $intervalo->ordem,
                        'hora_inicio'   => $intervalo->hora_inicio,
                        'hora_fim'      => $intervalo->hora_fim,
                        'ativo'         => $intervalo->ativo,
                    ]);

                    foreach ($intervalo->diasUteis as $diaUtil) {
                        DiaUtil::create([
                            'intervalo_id' => $novoIntervalo->id,
                            'dia_semana'   => $diaUtil->dia_semana,
                        ]);
                    }
                }
            }
        });

        $this->mensagem     = 'Turnos aplicados a ' . $todasLinhas->count() . ' linhas com sucesso!';
        $this->tipoMensagem = 'sucesso';
    }

    // ─── Feriados ─────────────────────────────────────────────────────────────

    public function adicionarFeriado(): void
    {
        $this->validate([
            'feriadoData'      => 'required|date',
            'feriadoDescricao' => 'required|string|max:120',
        ], [
            'feriadoData.required'      => 'Informe a data do feriado.',
            'feriadoDescricao.required' => 'Informe a descrição.',
        ]);

        Feriado::firstOrCreate(
            ['calendario_id' => $this->calendario['id'], 'data' => $this->feriadoData],
            ['descricao' => $this->feriadoDescricao]
        );

        $this->feriadoData       = '';
        $this->feriadoDescricao  = '';
        $this->mensagem          = 'Feriado adicionado.';
        $this->tipoMensagem      = 'sucesso';
        $this->carregarCalendario();
    }

    public function removerFeriado(int $id): void
    {
        Feriado::where('id', $id)
            ->where('calendario_id', $this->calendario['id'])
            ->delete();

        $this->carregarCalendario();
    }

    // ─── Render ───────────────────────────────────────────────────────────────

    public function render()
    {
        return view('livewire.calendario.configuracao-calendario');
    }
}
