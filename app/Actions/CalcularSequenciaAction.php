<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\ItemProgramacao;
use App\Models\Programacao;
use App\Models\ResultadoSequencia;
use App\Services\OtimizadorService;
use App\Services\SequenciadorService;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Orquestra o fluxo completo de cálculo de uma programação:
 *
 *   1. Valida que a programação existe, tem itens e tem calendário configurado
 *   2. (Opcional) Otimiza a sequência via OtimizadorService
 *   3. Reordena os itens da programação com a sequência otimizada
 *   4. Calcula via SequenciadorService (distribui nos turnos reais)
 *   5. Persiste os ResultadoSequencia no banco
 *   6. Atualiza o status da programação para 'calculada'
 *
 * Toda a operação de escrita (passos 3–6) roda dentro de uma transaction
 * para garantir consistência: ou tudo é salvo, ou nada é alterado.
 */
class CalcularSequenciaAction
{
    public function __construct(
        private readonly SequenciadorService $sequenciador,
        private readonly OtimizadorService   $otimizador
    ) {}

    /**
     * Executa o cálculo completo.
     *
     * @param  int   $programacaoId
     * @param  bool  $otimizarSequencia   true = aplica Nearest Neighbor + Simulated Annealing
     * @param  DateTimeImmutable|null  $momentoConsulta  para calcular produzido estimado em tempo real
     *
     * @return array{
     *   programacao: Programacao,
     *   resultados:  array,
     *   otimizacao:  array|null,
     *   resumo:      array{total_setup_min: int, total_producao_min: int, fim_previsto: string|null}
     * }
     *
     * @throws RuntimeException se a programação não puder ser calculada
     */
    public function executar(
        int $programacaoId,
        bool $otimizarSequencia = false,
        ?DateTimeImmutable $momentoConsulta = null
    ): array {
        $programacao = Programacao::with([
            'itens',
            'linha.calendario.intervalosAtivos.diasUteis',
            'linha.calendario.feriados',
        ])->findOrFail($programacaoId);

        $this->validarProgramacao($programacao);

        $resultadoOtimizacao = null;

        // ── Etapa de otimização (opcional) ───────────────────────────────────
        if ($otimizarSequencia && $programacao->itens->count() > 1) {
            $resultadoOtimizacao = $this->aplicarOtimizacao($programacao);
        }

        // ── Etapa de cálculo ─────────────────────────────────────────────────
        $resultadoCalculo = $this->sequenciador->calcular(
            $programacao,
            $momentoConsulta
        );

        // ── Persiste tudo em uma transaction ─────────────────────────────────
        $programacao = DB::transaction(function () use ($programacao, $resultadoCalculo) {
            // Remove resultados anteriores para re-cálculo idempotente
            ResultadoSequencia::where('programacao_id', $programacao->id)->delete();

            foreach ($resultadoCalculo['resultados'] as $linha) {
                ResultadoSequencia::create([
                    'programacao_id'      => $programacao->id,
                    'item_id'             => $linha['item_id'],
                    'tipo'                => $linha['tipo'],
                    'sku'                 => $linha['sku'],
                    'inicio'              => $linha['inicio'],
                    'fim'                 => $linha['fim'],
                    'duracao_minutos'     => $linha['duracao_minutos'],
                    'quantidade_estimada' => $linha['quantidade_estimada'],
                    'memoria_calculo'     => $linha['memoria_calculo'],
                ]);
            }

            $programacao->update(['status' => 'calculada']);

            return $programacao->fresh(['itens', 'resultados']);
        });

        return [
            'programacao' => $programacao,
            'resultados'  => $resultadoCalculo['resultados'],
            'otimizacao'  => $resultadoOtimizacao,
            'resumo'      => $resultadoCalculo['resumo'],
        ];
    }

    // ─── Métodos privados ────────────────────────────────────────────────────

    /**
     * Valida as pré-condições antes do cálculo.
     * Falhar cedo aqui evita operações incompletas no banco.
     */
    private function validarProgramacao(Programacao $programacao): void
    {
        if (! $programacao->estaEditavel()) {
            throw new RuntimeException(
                "A programação #{$programacao->id} (OP: {$programacao->numero_op}) " .
                "não pode ser recalculada no status '{$programacao->status}'. " .
                "Apenas programações em 'rascunho' ou 'calculada' podem ser recalculadas."
            );
        }

        if ($programacao->itens->isEmpty()) {
            throw new RuntimeException(
                "A programação #{$programacao->id} não possui itens. " .
                "Adicione pelo menos um item antes de calcular."
            );
        }

        if ($programacao->linha->calendario === null) {
            throw new RuntimeException(
                "A linha '{$programacao->linha->nome}' não possui calendário configurado. " .
                "Configure os turnos de trabalho antes de calcular."
            );
        }
    }

    /**
     * Aplica o OtimizadorService e reordena os itens da programação
     * conforme a sequência otimizada.
     *
     * A reordenação é feita em memória (atualiza os itens carregados) e
     * persistida na transaction principal para garantir atomicidade.
     */
    private function aplicarOtimizacao(Programacao $programacao): array
    {
        $inicioDisponivel = DateTimeImmutable::createFromInterface(
            $programacao->data_inicio_planejada
        );

        // Monta o array de itens com prazo_entrega para o otimizador
        $itensParaOtimizar = $programacao->itens->map(fn (ItemProgramacao $item) => [
            'sku'           => $item->sku,
            'quantidade'    => (float) $item->quantidade,
            'prazo_entrega' => null, // Sem prazo por item ainda — expandir quando houver campo
        ])->toArray();

        $resultadoOtimizacao = $this->otimizador->otimizar(
            $itensParaOtimizar,
            $inicioDisponivel
        );

        // Reordena os itens em memória segundo a sequência otimizada
        $sequenciaOtimizada = $resultadoOtimizacao['sequencia_otimizada'];
        $skusOrdenados      = array_column($sequenciaOtimizada, 'sku');

        DB::transaction(function () use ($programacao, $skusOrdenados) {
            foreach ($skusOrdenados as $posicao => $sku) {
                // Atualiza a sequência do primeiro item que bate o SKU (mantém integridade)
                $item = $programacao->itens
                    ->where('sku', $sku)
                    ->first();

                if ($item !== null) {
                    $item->update(['sequencia' => $posicao + 1]);
                }
            }
        });

        // Recarrega os itens após reordenação
        $programacao->load('itens');

        return $resultadoOtimizacao;
    }
}
