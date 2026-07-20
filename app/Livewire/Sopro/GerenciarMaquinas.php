<?php

declare(strict_types=1);

namespace App\Livewire\Sopro;

use Livewire\Component;
use Illuminate\Support\Facades\DB;

class GerenciarMaquinas extends Component
{
    public array  $maquinas     = [];
    public string $mensagem     = '';
    public string $tipoMensagem = '';

    // Form nova máquina
    public bool   $formAberto = false;
    public string $novoCodigo = '';
    public string $novoNome   = '';

    // Edição inline
    public ?int   $editandoId   = null;
    public string $editCodigo   = '';
    public string $editNome     = '';

    public function mount(): void
    {
        $this->carregar();
    }

    public function carregar(): void
    {
        $this->maquinas = DB::table('maquinas')
            ->orderBy('codigo')
            ->get()
            ->map(fn ($m) => (array) $m)
            ->toArray();
    }

    public function abrirForm(): void
    {
        $this->formAberto = true;
        $this->novoCodigo = '';
        $this->novoNome   = '';
    }

    public function cancelarForm(): void
    {
        $this->formAberto = false;
    }

    public function salvar(): void
    {
        $codigo = strtoupper(trim($this->novoCodigo));
        $nome   = trim($this->novoNome);

        if (!$codigo || !$nome) {
            $this->mensagem     = 'Código e nome são obrigatórios.';
            $this->tipoMensagem = 'erro';
            return;
        }

        if (DB::table('maquinas')->where('codigo', $codigo)->exists()) {
            $this->mensagem     = "Código {$codigo} já cadastrado.";
            $this->tipoMensagem = 'erro';
            return;
        }

        DB::table('maquinas')->insert([
            'codigo'     => $codigo,
            'nome'       => $nome,
            'ativo'      => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->mensagem     = "Máquina {$codigo} cadastrada.";
        $this->tipoMensagem = 'success';
        $this->formAberto   = false;
        $this->carregar();
    }

    public function editar(int $id): void
    {
        $m = DB::table('maquinas')->find($id);
        if (!$m) return;

        $this->editandoId = $id;
        $this->editCodigo = $m->codigo;
        $this->editNome   = $m->nome;
    }

    public function salvarEdicao(): void
    {
        $codigo = strtoupper(trim($this->editCodigo));
        $nome   = trim($this->editNome);

        if (!$codigo || !$nome) {
            $this->mensagem     = 'Código e nome são obrigatórios.';
            $this->tipoMensagem = 'erro';
            return;
        }

        $duplicado = DB::table('maquinas')
            ->where('codigo', $codigo)
            ->where('id', '!=', $this->editandoId)
            ->exists();

        if ($duplicado) {
            $this->mensagem     = "Código {$codigo} já existe em outra máquina.";
            $this->tipoMensagem = 'erro';
            return;
        }

        DB::table('maquinas')->where('id', $this->editandoId)->update([
            'codigo'     => $codigo,
            'nome'       => $nome,
            'updated_at' => now(),
        ]);

        $this->mensagem     = 'Máquina atualizada.';
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
        $m = DB::table('maquinas')->find($id);
        if (!$m) return;

        DB::table('maquinas')->where('id', $id)->update([
            'ativo'      => !$m->ativo,
            'updated_at' => now(),
        ]);

        $this->mensagem     = $m->ativo ? 'Máquina desativada.' : 'Máquina ativada.';
        $this->tipoMensagem = 'success';
        $this->carregar();
    }

    public function render(): \Illuminate\View\View
    {
        return view('livewire.sopro.gerenciar-maquinas');
    }
}
