<?php

declare(strict_types=1);

namespace App\Livewire\Sopro;

use App\Models\Frasco;
use App\Models\Maquina;
use App\Models\MatrizSetupSopro;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Throwable;

class MatrizSetupSoproGrid extends Component
{
    public ?int $maquinaIdSelecionada = null;
    public array $celulas      = [];
    public array $tiposSetup   = [];
    public string $mensagem     = '';
    public string $tipoMensagem = 'sucesso';

    public function mount(): void
    {
        $primeira = Maquina::where('ativo', true)->orderBy('codigo')->first();
        if ($primeira) {
            $this->maquinaIdSelecionada = $primeira->id;
            $this->carregarCelulas();
        }
    }

    public function updatedMaquinaIdSelecionada(): void
    {
        $this->celulas     = [];
        $this->tiposSetup  = [];
        $this->mensagem    = '';
        $this->carregarCelulas();
    }

    public function salvar(): void
    {
        if (! $this->maquinaIdSelecionada) return;

        try {
            DB::transaction(function () {
                foreach ($this->celulas as $chave => $minutos) {
                    [$origem, $destino] = explode('|', $chave);
                    if ($origem === $destino) continue;

                    MatrizSetupSopro::updateOrCreate(
                        [
                            'maquina_id'  => $this->maquinaIdSelecionada,
                            'sku_origem'  => $origem,
                            'sku_destino' => $destino,
                        ],
                        [
                            'duracao_minutos' => max(0, (int) $minutos),
                            'tipo_setup'      => $this->tiposSetup[$chave] ?? null,
                        ]
                    );
                }
            });

            $this->mensagem     = 'Matriz salva com sucesso.';
            $this->tipoMensagem = 'sucesso';
        } catch (Throwable $e) {
            $this->mensagem     = 'Erro ao salvar: ' . $e->getMessage();
            $this->tipoMensagem = 'erro';
        }
    }

    public function render()
    {
        $maquinas = Maquina::where('ativo', true)->orderBy('codigo')->get();
        $skus     = $this->maquinaIdSelecionada
            ? Frasco::where('maquina_id', $this->maquinaIdSelecionada)
                ->where('ativo', true)
                ->orderBy('descricao')
                ->pluck('sku')
                ->toArray()
            : [];

        return view('livewire.sopro.matriz-setup-sopro-grid', compact('maquinas', 'skus'));
    }

    private function carregarCelulas(): void
    {
        if (! $this->maquinaIdSelecionada) return;

        $entradas = MatrizSetupSopro::where('maquina_id', $this->maquinaIdSelecionada)->get();
        foreach ($entradas as $e) {
            $chave = "{$e->sku_origem}|{$e->sku_destino}";
            $this->celulas[$chave]    = $e->duracao_minutos;
            $this->tiposSetup[$chave] = $e->tipo_setup;
        }
    }
}
