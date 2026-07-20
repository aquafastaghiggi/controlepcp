<?php

declare(strict_types=1);

namespace App\Livewire\Produto;

use App\Models\Linha;
use App\Models\MatrizSetup;
use App\Models\Produto;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Throwable;

class MatrizSetupGrid extends Component
{
    public ?int $linhaIdSelecionada = null;

    /** ['SKU_ORIGEM|SKU_DESTINO' => minutos] */
    public array $celulas = [];

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

        $primeira = Linha::where('ativo', true)->orderBy('codigo')->first();
        if ($primeira) {
            $this->linhaIdSelecionada = $primeira->id;
            $this->carregarCelulas();
        }
    }

    public function updatedLinhaIdSelecionada(): void
    {
        $this->celulas  = [];
        $this->mensagem = '';
        $this->carregarCelulas();
    }

    public function selecionarLinha(int $linhaId): void
    {
        $this->linhaIdSelecionada = $linhaId;
        $this->carregarCelulas();
    }

    public function salvar(): void
    {
        if (! $this->linhaIdSelecionada) {
            return;
        }

        try {
            DB::transaction(function () {
                foreach ($this->celulas as $chave => $minutos) {
                    [$origem, $destino] = explode('|', $chave);

                    if ($origem === $destino) {
                        continue;
                    }

                    // Busca pela unique key real (sku_origem, sku_destino) — sem linha_id,
                    // pois a unique constraint não inclui linha_id.
                    // linha_id vai nos valores para ser atualizado se necessário.
                    MatrizSetup::updateOrCreate(
                        [
                            'sku_origem'  => $origem,
                            'sku_destino' => $destino,
                        ],
                        [
                            'linha_id'        => $this->linhaIdSelecionada,
                            'duracao_minutos' => max(0, (int) $minutos),
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
        $linhas = Linha::where('ativo', true)->orderBy('codigo')->get();

        $produtos = $this->linhaIdSelecionada
            ? Produto::where('linha_id', $this->linhaIdSelecionada)
                ->where('ativo', true)
                ->orderBy('descricao')
                ->get(['sku', 'descricao'])
            : collect();

        $skus        = $produtos->pluck('sku')->toArray();
        $nomesPorSku = $produtos->pluck('descricao', 'sku')->toArray();

        return view('livewire.produto.matriz-setup-grid', compact('linhas', 'skus', 'nomesPorSku'));
    }

    private function carregarCelulas(): void
    {
        if (! $this->linhaIdSelecionada) {
            return;
        }

        $entradas = MatrizSetup::where('linha_id', $this->linhaIdSelecionada)->get();

        foreach ($entradas as $e) {
            $this->celulas["{$e->sku_origem}|{$e->sku_destino}"] = $e->duracao_minutos;
        }
    }

    public function exportarTxt(): mixed
    {
        if (! $this->linhaIdSelecionada) {
            return null;
        }

        $linha = Linha::find($this->linhaIdSelecionada);

        $pares = MatrizSetup::where('linha_id', $this->linhaIdSelecionada)
            ->where('duracao_minutos', '>', 0)
            ->orderBy('sku_origem')
            ->orderBy('sku_destino')
            ->get();

        $skus = $pares->pluck('sku_origem')->merge($pares->pluck('sku_destino'))->unique();
        $nomesPorSku = Produto::whereIn('sku', $skus)->pluck('descricao', 'sku');

        $linhasTxt = [];
        $linhasTxt[] = 'MATRIZ DE SETUP — LINHA: ' . ($linha->nome ?? $linha->codigo ?? '');
        $linhasTxt[] = 'Gerado em: ' . now()->locale('pt_BR')->isoFormat('DD/MM/YYYY HH:mm');
        $linhasTxt[] = str_repeat('=', 40);
        $linhasTxt[] = 'ORIGEM | DESTINO | TEMPO (min)';
        $linhasTxt[] = str_repeat('-', 40);

        foreach ($pares as $par) {
            $nomeOrigem  = $nomesPorSku[$par->sku_origem]  ?? '';
            $nomeDestino = $nomesPorSku[$par->sku_destino] ?? '';
            $linhasTxt[] = "{$nomeOrigem} ({$par->sku_origem}) | {$nomeDestino} ({$par->sku_destino}) | {$par->duracao_minutos}";
        }

        $linhasTxt[] = str_repeat('=', 40);
        $linhasTxt[] = 'Total de pares: ' . $pares->count();

        $conteudo = implode(PHP_EOL, $linhasTxt);

        $nomeArquivo = 'setup_' . ($linha->codigo ?? 'linha') . '.txt';

        return response()->streamDownload(function () use ($conteudo) {
            echo $conteudo;
        }, $nomeArquivo);
    }
}
