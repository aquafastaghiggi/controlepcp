<?php

declare(strict_types=1);

namespace App\Livewire\Produto;

use App\Models\MatrizSetup;
use App\Models\Produto;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithPagination;
use Throwable;

class GerenciarProdutos extends Component
{
    use WithPagination;

    // Formulário novo produto
    public bool   $mostrarFormNovo     = false;
    public string $skuNovo             = '';
    public string $descricaoNova       = '';
    public float  $taxaNova            = 0.0;
    public string $referenciaSetupNova = '';

    // Edição inline
    public ?int   $editandoId       = null;
    public string $editandoTaxa     = '';
    public ?int   $editandoLinhaId  = null;
    public string $editandoRefSetup = '';
    public bool   $editandoAtivo    = true;
    public array  $linhasDisponiveis = [];

    /** Armazena os valores da matriz editável: ['SKU_ORIGEM|SKU_DESTINO' => minutos] */
    public array $matrizEditavel = [];

    public string $mensagem      = '';
    public string $tipoMensagem  = 'sucesso';
    public string $ultimaSincCigam = '';

    public function mount(): void
    {
        $ultimoProduto = Produto::orderByDesc('updated_at')->first();
        $this->ultimaSincCigam = $ultimoProduto
            ? \Carbon\Carbon::parse($ultimoProduto->updated_at)
                ->locale('pt_BR')->isoFormat('ddd DD/MM/YYYY [às] HH:mm')
            : 'nunca';

        $this->linhasDisponiveis = \App\Models\Linha::where('ativo', true)
            ->orderBy('codigo')
            ->get()
            ->map(fn ($l) => ['id' => $l->id, 'nome' => $l->codigo . ' — ' . $l->nome])
            ->toArray();

        $this->carregarMatriz();
    }

    // ─── Produtos ────────────────────────────────────────────────────────────

    public function salvarNovoProduto(): void
    {
        $this->validate([
            'skuNovo'       => 'required|string|max:120|unique:produtos,sku',
            'descricaoNova' => 'required|string|max:255',
            'taxaNova'      => 'required|numeric|min:0.01',
        ], [
            'skuNovo.required'       => 'Informe o SKU.',
            'skuNovo.unique'         => 'Este SKU já está cadastrado.',
            'descricaoNova.required' => 'Informe a descrição.',
            'taxaNova.min'           => 'Taxa deve ser maior que zero.',
        ]);

        try {
            $produto = Produto::create([
                'sku'              => strtoupper(trim($this->skuNovo)),
                'descricao'        => $this->descricaoNova,
                'taxa_por_hora'    => $this->taxaNova,
                'referencia_setup' => $this->referenciaSetupNova ?: null,
                'ativo'            => true,
            ]);

            // Adiciona entradas zeradas na matriz para o novo produto
            $this->expandirMatrizParaNovoProduto($produto->sku);
            $this->carregarMatriz();

            $this->skuNovo             = '';
            $this->descricaoNova       = '';
            $this->taxaNova            = 0.0;
            $this->referenciaSetupNova = '';
            $this->mostrarFormNovo     = false;
            $this->flashSucesso("Produto {$produto->sku} cadastrado.");

        } catch (Throwable $e) {
            $this->flashErro($e->getMessage());
        }
    }

    public function alternarAtivo(int $id): void
    {
        $produto = Produto::findOrFail($id);
        $produto->update(['ativo' => ! $produto->ativo]);
    }

    // ─── Matriz de Setup ─────────────────────────────────────────────────────

    public function salvarMatriz(): void
    {
        try {
            DB::transaction(function () {
                foreach ($this->matrizEditavel as $chave => $minutos) {
                    [$origem, $destino] = explode('|', $chave);

                    MatrizSetup::updateOrCreate(
                        ['sku_origem' => $origem, 'sku_destino' => $destino],
                        ['duracao_minutos' => max(0, (int) $minutos)]
                    );
                }
            });

            $this->flashSucesso('Matriz de setup salva.');
        } catch (Throwable $e) {
            $this->flashErro($e->getMessage());
        }
    }

    // ─── Edição inline ───────────────────────────────────────────────────────

    public function editar(int $id): void
    {
        $produto = Produto::findOrFail($id);
        $this->editandoId       = $id;
        $this->editandoTaxa     = (string) $produto->taxa_por_hora;
        $this->editandoLinhaId  = $produto->linha_id;
        $this->editandoRefSetup = $produto->referencia_setup ?? '';
        $this->editandoAtivo    = (bool) $produto->ativo;
    }

    public function salvarEdicao(): void
    {
        $this->validate([
            'editandoTaxa'    => 'required|numeric|min:0',
            'editandoLinhaId' => 'nullable|exists:linhas,id',
        ], [
            'editandoTaxa.required' => 'Informe a taxa de produção.',
            'editandoTaxa.numeric'  => 'A taxa deve ser um número.',
            'editandoTaxa.min'      => 'A taxa não pode ser negativa.',
        ]);

        Produto::findOrFail($this->editandoId)->update([
            'taxa_por_hora'    => (float) str_replace(',', '.', $this->editandoTaxa),
            'linha_id'         => $this->editandoLinhaId,
            'referencia_setup' => $this->editandoRefSetup ?: null,
            'ativo'            => $this->editandoAtivo,
        ]);

        $this->editandoId = null;
        $this->flashSucesso('Produto atualizado.');
    }

    public function cancelarEdicao(): void
    {
        $this->editandoId = null;
    }

    public function toggleAtivo(int $id): void
    {
        $produto = Produto::findOrFail($id);
        $produto->update(['ativo' => ! $produto->ativo]);
    }

    // ─── Render ──────────────────────────────────────────────────────────────

    public function render()
    {
        $produtos = Produto::with('linha')
            ->orderBy('linha_id')
            ->orderBy('descricao')
            ->paginate(50);

        return view('livewire.produto.gerenciar-produtos', compact('produtos'));
    }

    // ─── Privados ────────────────────────────────────────────────────────────

    private function carregarMatriz(): void
    {
        $this->matrizEditavel = [];

        $entradas = MatrizSetup::all();

        foreach ($entradas as $entrada) {
            $this->matrizEditavel["{$entrada->sku_origem}|{$entrada->sku_destino}"] = $entrada->duracao_minutos;
        }
    }

    private function expandirMatrizParaNovoProduto(string $novoSku): void
    {
        $skusExistentes = Produto::where('sku', '!=', $novoSku)->pluck('sku');

        DB::transaction(function () use ($novoSku, $skusExistentes) {
            foreach ($skusExistentes as $sku) {
                MatrizSetup::firstOrCreate(['sku_origem' => $novoSku, 'sku_destino' => $sku], ['duracao_minutos' => 0]);
                MatrizSetup::firstOrCreate(['sku_origem' => $sku, 'sku_destino' => $novoSku], ['duracao_minutos' => 0]);
            }
        });
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
