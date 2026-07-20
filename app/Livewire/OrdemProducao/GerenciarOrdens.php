<?php

declare(strict_types=1);

namespace App\Livewire\OrdemProducao;

use App\Models\Linha;
use App\Models\OrdemProducao;
use App\Services\OrdemProducaoService;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Component;
use Livewire\WithPagination;
use Throwable;

class GerenciarOrdens extends Component
{
    use WithPagination;

    // ─── Filtros ─────────────────────────────────────────────────────────────

    public string $busca          = '';
    public string $filtroStatus   = '';
    public string $filtroLinhaId  = '';

    // ─── Formulário nova ordem ────────────────────────────────────────────────

    public bool    $mostrarFormNova   = false;
    public string  $novoNumeroOp      = '';
    public string  $novoSku           = '';
    public string  $novaDescricao     = '';
    public string  $novaQuantidade    = '';
    public string  $novaDataEntrega   = '';
    public int     $novaPrioridade    = 5;
    public string  $novoLinhaId       = '';
    public string  $novasObservacoes  = '';

    // ─── Feedback ────────────────────────────────────────────────────────────

    public string $mensagem     = '';
    public string $tipoMensagem = 'sucesso';

    // ─── Watchers de filtro ───────────────────────────────────────────────────

    public function updatedBusca(): void
    {
        $this->resetPage();
    }

    public function updatedFiltroStatus(): void
    {
        $this->resetPage();
    }

    public function updatedFiltroLinhaId(): void
    {
        $this->resetPage();
    }

    // ─── Formulário ───────────────────────────────────────────────────────────

    public function abrirFormNova(): void
    {
        $this->mostrarFormNova = true;
        $this->resetFormNova();
    }

    public function fecharFormNova(): void
    {
        $this->mostrarFormNova = false;
        $this->resetFormNova();
    }

    public function salvarNova(): void
    {
        /** @var OrdemProducaoService $service */
        $service = app(OrdemProducaoService::class);

        try {
            $service->criar([
                'numero_op'         => $this->novoNumeroOp      ?: null,
                'sku'               => $this->novoSku,
                'descricao_produto' => $this->novaDescricao,
                'quantidade'        => $this->novaQuantidade,
                'linha_id'          => $this->novoLinhaId        ?: null,
                'data_entrega'      => $this->novaDataEntrega     ?: null,
                'prioridade'        => $this->novaPrioridade,
                'observacoes'       => $this->novasObservacoes    ?: null,
                'origem'            => 'manual',
            ]);

            $this->fecharFormNova();
            $this->flashSucesso('Ordem de produção criada com sucesso.');

        } catch (ValidationException $e) {
            foreach ($e->errors() as $field => $messages) {
                $this->addError($field, $messages[0]);
            }
        } catch (Throwable $e) {
            $this->flashErro('Erro ao criar ordem: ' . $e->getMessage());
        }
    }

    // ─── Ações de linha ───────────────────────────────────────────────────────

    public function cancelarOrdem(int $id): void
    {
        /** @var OrdemProducaoService $service */
        $service = app(OrdemProducaoService::class);

        try {
            $ordem = OrdemProducao::findOrFail($id);
            $service->atualizarStatus($ordem, OrdemProducao::STATUS_CANCELADA);
            $this->flashSucesso('Ordem cancelada com sucesso.');
        } catch (Throwable $e) {
            $this->flashErro('Não foi possível cancelar a ordem: ' . $e->getMessage());
        }
    }

    // ─── Render ──────────────────────────────────────────────────────────────

    public function render(): View
    {
        /** @var OrdemProducaoService $service */
        $service = app(OrdemProducaoService::class);

        $ordens = $service->listar([
            'busca'    => $this->busca,
            'status'   => $this->filtroStatus  ?: null,
            'linha_id' => $this->filtroLinhaId ?: null,
        ]);

        $linhas = Linha::where('ativo', true)
            ->orderBy('codigo')
            ->get();

        return view('livewire.ordem-producao.gerenciar-ordens', compact('ordens', 'linhas'));
    }

    // ─── Privados ────────────────────────────────────────────────────────────

    private function resetFormNova(): void
    {
        $this->novoNumeroOp     = '';
        $this->novoSku          = '';
        $this->novaDescricao    = '';
        $this->novaQuantidade   = '';
        $this->novaDataEntrega  = '';
        $this->novaPrioridade   = 5;
        $this->novoLinhaId      = '';
        $this->novasObservacoes = '';
        $this->resetValidation();
    }

    private function flashSucesso(string $msg): void
    {
        $this->mensagem    = $msg;
        $this->tipoMensagem = 'sucesso';
    }

    private function flashErro(string $msg): void
    {
        $this->mensagem    = $msg;
        $this->tipoMensagem = 'erro';
    }
}
