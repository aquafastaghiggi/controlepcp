<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\ItemProgramacao;
use App\Models\Linha;
use App\Models\Produto;
use App\Models\Programacao;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Cria ou atualiza o cabeçalho de uma programação e seus itens.
 *
 * Separado do cálculo propositalmente: o usuário pode montar os itens,
 * salvar como rascunho e só calcular depois — possivelmente ajustando
 * a eficiência ou reordenando antes de confirmar.
 */
class CriarProgramacaoAction
{
    /**
     * @param  array{
     *   linha_id: int,
     *   numero_op: string|null,
     *   descricao: string|null,
     *   data_inicio_planejada: string,
     *   eficiencia: float,
     *   origem: string,
     *   itens: array<int, array{sku: string, quantidade: float, sequencia: int}>
     * }  $dados
     */
    public function executar(array $dados): Programacao
    {
        $linha = Linha::findOrFail($dados['linha_id']);

        if (! $linha->ativo) {
            throw new InvalidArgumentException("A linha '{$linha->nome}' está inativa.");
        }

        return DB::transaction(function () use ($dados, $linha) {
            $programacao = Programacao::create([
                'linha_id'              => $linha->id,
                'numero_op'             => $dados['numero_op'] ?? null,
                'descricao'             => $dados['descricao'] ?? null,
                'data_inicio_planejada' => $dados['data_inicio_planejada'],
                'eficiencia'            => $dados['eficiencia'] ?? 100.0,
                'dias_selecionados'     => ! empty($dados['dias_selecionados']) ? $dados['dias_selecionados'] : null,
                'status'                => 'rascunho',
                'origem'                => $dados['origem'] ?? 'manual',
            ]);

            $this->criarItens($programacao, $dados['itens'] ?? []);

            return $programacao->load('itens');
        });
    }

    private function criarItens(Programacao $programacao, array $itens): void
    {
        foreach ($itens as $itemDados) {
            $sku = trim((string) ($itemDados['sku'] ?? ''));

            $produto = Produto::where('sku', $sku)->first();

            if ($produto === null) {
                throw new InvalidArgumentException("SKU '{$sku}' não encontrado no cadastro de produtos.");
            }

            ItemProgramacao::create([
                'programacao_id'   => $programacao->id,
                'sequencia'        => (int) ($itemDados['sequencia'] ?? 0),
                'numero_op'        => $itemDados['numero_op'] ?? null,
                'sku'              => $produto->sku,
                'descricao_produto'=> $produto->descricao,
                'quantidade'       => (float) ($itemDados['quantidade'] ?? 0),
            ]);
        }
    }
}
