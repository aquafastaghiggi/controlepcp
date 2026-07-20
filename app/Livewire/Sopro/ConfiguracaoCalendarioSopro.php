<?php

declare(strict_types=1);

namespace App\Livewire\Sopro;

use App\Models\CalendarioSopro;
use App\Models\FeriadoSopro;
use App\Models\IntervaloSopro;
use App\Models\Maquina;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class ConfiguracaoCalendarioSopro extends Component
{
    public ?int  $maquinaSelecionada = null;
    public array $maquinas           = [];
    public ?array $calendario        = null;
    public array $intervalos         = [];
    public array $feriados           = [];

    public ?int  $turnoEditando = null;
    public array $turnoForm     = [
        'nome'        => '',
        'hora_inicio' => '',
        'hora_fim'    => '',
        'ativo'       => true,
    ];

    public bool  $adicionandoTurno = false;
    public array $novoTurnoForm    = [
        'nome'        => '',
        'hora_inicio' => '',
        'hora_fim'    => '',
        'ativo'       => true,
    ];

    public string $feriadoData      = '';
    public string $feriadoDescricao = '';

    public string $mensagem     = '';
    public string $tipoMensagem = 'sucesso';

    public function mount(): void
    {
        $this->maquinas = Maquina::where('ativo', true)
            ->orderBy('codigo')
            ->get()
            ->map(fn ($m) => ['id' => $m->id, 'codigo' => $m->codigo, 'nome' => $m->nome])
            ->toArray();

        if (! empty($this->maquinas)) {
            $this->maquinaSelecionada = $this->maquinas[0]['id'];
            $this->carregarCalendario();
        }
    }

    public function updatedMaquinaSelecionada(): void
    {
        $this->turnoEditando = null;
        $this->mensagem      = '';
        $this->carregarCalendario();
    }

    public function carregarCalendario(): void
    {
        if (! $this->maquinaSelecionada) {
            $this->calendario = null;
            $this->intervalos = [];
            $this->feriados   = [];
            return;
        }

        $calendario = CalendarioSopro::where('maquina_id', $this->maquinaSelecionada)
            ->with(['intervalos' => fn ($q) => $q->orderBy('ordem'), 'feriados'])
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
        ])->values()->toArray();

        $this->feriados = $calendario->feriados
            ->sortBy('data')
            ->map(fn ($f) => [
                'id'        => $f->id,
                'data'      => $f->data instanceof \Carbon\Carbon ? $f->data->format('Y-m-d') : (string) $f->data,
                'descricao' => $f->descricao,
            ])
            ->values()
            ->toArray();
    }

    public function editarTurno(int $id): void
    {
        $turno = collect($this->intervalos)->firstWhere('id', $id);
        if (! $turno) return;

        $this->turnoEditando = $id;
        $this->turnoForm = [
            'nome'        => $turno['nome'],
            'hora_inicio' => substr($turno['hora_inicio'], 0, 5),
            'hora_fim'    => substr($turno['hora_fim'], 0, 5),
            'ativo'       => $turno['ativo'],
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
        ], [
            'turnoForm.nome.required'        => 'Informe o nome do turno.',
            'turnoForm.hora_inicio.required' => 'Informe o horário de início.',
            'turnoForm.hora_fim.required'    => 'Informe o horário de fim.',
        ]);

        IntervaloSopro::findOrFail($this->turnoEditando)->update([
            'nome'        => $this->turnoForm['nome'],
            'hora_inicio' => $this->turnoForm['hora_inicio'],
            'hora_fim'    => $this->turnoForm['hora_fim'],
            'ativo'       => $this->turnoForm['ativo'],
        ]);

        $this->turnoEditando = null;
        $this->mensagem      = 'Turno salvo com sucesso.';
        $this->tipoMensagem  = 'sucesso';
        $this->carregarCalendario();
    }

    public function iniciarNovoTurno(): void
    {
        $this->adicionandoTurno = true;
        $this->novoTurnoForm    = ['nome' => '', 'hora_inicio' => '', 'hora_fim' => '', 'ativo' => true];
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
        ], [
            'novoTurnoForm.nome.required'        => 'Informe o nome do turno.',
            'novoTurnoForm.hora_inicio.required' => 'Informe o horário de início.',
            'novoTurnoForm.hora_fim.required'    => 'Informe o horário de fim.',
        ]);

        $proximaOrdem = IntervaloSopro::where('calendario_sopro_id', $this->calendario['id'])->max('ordem') + 1;

        IntervaloSopro::create([
            'calendario_sopro_id' => $this->calendario['id'],
            'nome'                => $this->novoTurnoForm['nome'],
            'ordem'               => $proximaOrdem,
            'hora_inicio'         => $this->novoTurnoForm['hora_inicio'],
            'hora_fim'            => $this->novoTurnoForm['hora_fim'],
            'ativo'               => $this->novoTurnoForm['ativo'],
        ]);

        $this->adicionandoTurno = false;
        $this->novoTurnoForm    = ['nome' => '', 'hora_inicio' => '', 'hora_fim' => '', 'ativo' => true];
        $this->mensagem         = 'Turno adicionado com sucesso.';
        $this->tipoMensagem     = 'sucesso';
        $this->carregarCalendario();
    }

    public function removerTurno(int $id): void
    {
        IntervaloSopro::where('id', $id)
            ->where('calendario_sopro_id', $this->calendario['id'])
            ->delete();

        $this->mensagem     = 'Turno removido.';
        $this->tipoMensagem = 'sucesso';
        $this->carregarCalendario();
    }

    public function aplicarTodasMaquinas(): void
    {
        $calendarioOrigem = CalendarioSopro::where('maquina_id', $this->maquinaSelecionada)
            ->with(['intervalos'])
            ->firstOrFail();

        $todasMaquinas = Maquina::where('id', '!=', $this->maquinaSelecionada)->get();

        DB::transaction(function () use ($calendarioOrigem, $todasMaquinas) {
            foreach ($todasMaquinas as $maquina) {
                $calendarioDestino = CalendarioSopro::firstOrCreate(
                    ['maquina_id' => $maquina->id],
                    ['nome' => 'Calendário ' . $maquina->codigo]
                );

                $calendarioDestino->intervalos()->delete();

                foreach ($calendarioOrigem->intervalos as $intervalo) {
                    IntervaloSopro::create([
                        'calendario_sopro_id' => $calendarioDestino->id,
                        'nome'                => $intervalo->nome,
                        'ordem'               => $intervalo->ordem,
                        'hora_inicio'         => $intervalo->hora_inicio,
                        'hora_fim'            => $intervalo->hora_fim,
                        'ativo'               => $intervalo->ativo,
                    ]);
                }
            }
        });

        $this->mensagem     = 'Turnos aplicados a ' . $todasMaquinas->count() . ' máquinas com sucesso!';
        $this->tipoMensagem = 'sucesso';
    }

    public function adicionarFeriado(): void
    {
        $this->validate([
            'feriadoData'      => 'required|date',
            'feriadoDescricao' => 'required|string|max:120',
        ], [
            'feriadoData.required'      => 'Informe a data do feriado.',
            'feriadoDescricao.required' => 'Informe a descrição.',
        ]);

        FeriadoSopro::firstOrCreate(
            ['calendario_sopro_id' => $this->calendario['id'], 'data' => $this->feriadoData],
            ['descricao' => $this->feriadoDescricao]
        );

        $this->feriadoData      = '';
        $this->feriadoDescricao = '';
        $this->mensagem         = 'Feriado adicionado.';
        $this->tipoMensagem     = 'sucesso';
        $this->carregarCalendario();
    }

    public function removerFeriado(int $id): void
    {
        FeriadoSopro::where('id', $id)
            ->where('calendario_sopro_id', $this->calendario['id'])
            ->delete();

        $this->carregarCalendario();
    }

    public function render()
    {
        return view('livewire.sopro.configuracao-calendario-sopro');
    }
}
