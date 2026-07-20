<?php

namespace App\Livewire\Planejamento;

use Livewire\Component;
use Illuminate\Support\Facades\DB;

class DivergenciasOp extends Component
{
    public array $divergencias     = [];
    public array $divergenciasHist = [];
    public int   $totalAtivas      = 0;

    public function mount(): void
    {
        $this->carregarDados();
    }

    public function carregarDados(): void
    {
        $this->divergencias = DB::table('divergencias_op')
            ->whereNull('resolvida_em')
            ->orderByDesc('detectado_em')
            ->get()
            ->map(fn($d) => (array) $d)
            ->toArray();

        $this->divergenciasHist = DB::table('divergencias_op')
            ->whereNotNull('resolvida_em')
            ->orderByDesc('resolvida_em')
            ->limit(20)
            ->get()
            ->map(fn($d) => (array) $d)
            ->toArray();

        $this->totalAtivas = count($this->divergencias);
    }

    public function marcarCorrigido(int $id): void
    {
        DB::table('divergencias_op')
            ->where('id', $id)
            ->update(['resolvida_em' => now(), 'updated_at' => now()]);

        $this->carregarDados();
    }

    public function render(): \Illuminate\View\View
    {
        return view('livewire.planejamento.divergencias-op');
    }
}
