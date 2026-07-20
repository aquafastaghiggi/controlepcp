<?php

declare(strict_types=1);

namespace App\Livewire\Sopro;

use Livewire\Component;
use Illuminate\Support\Facades\DB;

class GerenciarFrascos extends Component
{
    public array   $frascos       = [];
    public array   $maquinas      = [];
    public string  $filtroMaterial = '';
    public string  $busca          = '';

    public string  $mensagem      = '';
    public string  $tipoMensagem  = '';

    public ?int    $editandoId    = null;
    public string  $editTaxa      = '';
    public string  $editMaquinaId = '';

    public function mount(): void
    {
        $this->maquinas = DB::table('maquinas')
            ->where('ativo', true)
            ->orderBy('codigo')
            ->get()
            ->map(fn ($m) => (array) $m)
            ->toArray();

        $this->carregar();
    }

    public function carregar(): void
    {
        $query = DB::table('frascos as f')
            ->leftJoin('maquinas as m', 'm.id', '=', 'f.maquina_id')
            ->select(
                'f.id', 'f.sku', 'f.descricao', 'f.material',
                'f.taxa_por_hora', 'f.maquina_id', 'f.ativo',
                'm.codigo as maquina_codigo', 'm.nome as maquina_nome'
            )
            ->orderBy('f.sku');

        if ($this->filtroMaterial) {
            $query->where('f.material', $this->filtroMaterial);
        }

        if ($this->busca) {
            $query->where(function ($q) {
                $q->where('f.sku', 'like', "%{$this->busca}%")
                  ->orWhere('f.descricao', 'like', "%{$this->busca}%");
            });
        }

        $this->frascos = $query->get()->map(fn ($r) => (array) $r)->toArray();
    }

    public function updatedFiltroMaterial(): void { $this->carregar(); }
    public function updatedBusca(): void          { $this->carregar(); }

    public function editar(int $id): void
    {
        $f = DB::table('frascos')->find($id);
        if (!$f) return;

        $this->editandoId    = $id;
        $this->editTaxa      = $f->taxa_por_hora !== null ? (string) $f->taxa_por_hora : '';
        $this->editMaquinaId = $f->maquina_id !== null ? (string) $f->maquina_id : '';
    }

    public function salvarEdicao(): void
    {
        $taxa     = $this->editTaxa !== '' ? (float) str_replace(',', '.', $this->editTaxa) : null;
        $maquinaId = $this->editMaquinaId !== '' ? (int) $this->editMaquinaId : null;

        DB::table('frascos')->where('id', $this->editandoId)->update([
            'taxa_por_hora' => $taxa,
            'maquina_id'    => $maquinaId,
            'updated_at'    => now(),
        ]);

        $this->mensagem     = 'Frasco atualizado.';
        $this->tipoMensagem = 'success';
        $this->editandoId   = null;
        $this->carregar();
    }

    public function cancelarEdicao(): void
    {
        $this->editandoId = null;
    }

    public function toggleAtivo(int $id): void
    {
        $f = DB::table('frascos')->find($id);
        if (!$f) return;

        DB::table('frascos')->where('id', $id)->update([
            'ativo'      => !$f->ativo,
            'updated_at' => now(),
        ]);

        $this->mensagem     = $f->ativo ? 'Frasco desativado.' : 'Frasco ativado.';
        $this->tipoMensagem = 'success';
        $this->carregar();
    }

    public function render(): \Illuminate\View\View
    {
        return view('livewire.sopro.gerenciar-frascos');
    }
}
