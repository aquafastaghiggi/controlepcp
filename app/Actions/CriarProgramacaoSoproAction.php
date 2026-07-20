<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Frasco;
use App\Models\ItemProgramacaoSopro;
use App\Models\Maquina;
use App\Models\ProgramacaoSopro;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Cria ou atualiza o cabeçalho de uma programação Sopro e seus itens.
 * Equivalente ao CriarProgramacaoAction do Envase, adaptado para Maquina e Frasco.
 */
class CriarProgramacaoSoproAction
{
    public function executar(array $dados): ProgramacaoSopro
    {
        $maquina = Maquina::findOrFail($dados['maquina_id']);

        if (! $maquina->ativo) {
            throw new InvalidArgumentException("A máquina '{$maquina->codigo}' está inativa.");
        }

        return DB::transaction(function () use ($dados, $maquina) {
            $programacao = ProgramacaoSopro::create([
                'maquina_id'            => $maquina->id,
                'numero_op'             => $dados['numero_op'] ?? null,
                'data_inicio_planejada' => $dados['data_inicio_planejada'],
                'eficiencia'            => $dados['eficiencia'] ?? 70.0,
                'dias_selecionados'     => ! empty($dados['dias_selecionados']) ? $dados['dias_selecionados'] : null,
                'status'                => 'rascunho',
                'origem'                => $dados['origem'] ?? 'excel',
            ]);

            $this->criarItens($programacao, $dados['itens'] ?? []);

            return $programacao->load('itens');
        });
    }

    private function criarItens(ProgramacaoSopro $programacao, array $itens): void
    {
        foreach ($itens as $itemDados) {
            $sku = trim((string) ($itemDados['sku'] ?? ''));

            $frasco = Frasco::where('sku', $sku)->first();

            if ($frasco === null) {
                throw new InvalidArgumentException("SKU '{$sku}' não encontrado no cadastro de frascos.");
            }

            ItemProgramacaoSopro::create([
                'programacao_sopro_id' => $programacao->id,
                'sequencia'            => (int) ($itemDados['sequencia'] ?? 0),
                'numero_op'            => $itemDados['numero_op'] ?? null,
                'sku'                  => $frasco->sku,
                'descricao_produto'    => $frasco->descricao,
                'quantidade'           => (float) ($itemDados['quantidade'] ?? 0),
                'data_programada'      => $itemDados['prazo'] ?? null,
            ]);
        }
    }
}
