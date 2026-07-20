<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\ProgramacaoSopro;
use App\Models\ResultadoSequenciaSopro;
use App\Services\SequenciadorSoproService;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Orquestra o cálculo completo de uma programação Sopro.
 * Equivalente ao CalcularSequenciaAction do Envase, adaptado para Sopro.
 * Sem otimizador por enquanto — sequência definida pelo Colemar via Excel.
 */
class CalcularSequenciaSoproAction
{
    public function __construct(
        private readonly SequenciadorSoproService $sequenciador,
    ) {}

    public function executar(
        int $programacaoId,
        ?DateTimeImmutable $momentoConsulta = null
    ): array {
        $programacao = ProgramacaoSopro::with([
            'itens',
            'maquina.calendarioSopro.intervalosAtivos',
            'maquina.calendarioSopro.feriados',
        ])->findOrFail($programacaoId);

        $this->validarProgramacao($programacao);

        $resultadoCalculo = $this->sequenciador->calcular(
            $programacao,
            $momentoConsulta
        );

        $programacao = DB::transaction(function () use ($programacao, $resultadoCalculo) {
            ResultadoSequenciaSopro::where('programacao_sopro_id', $programacao->id)->delete();

            foreach ($resultadoCalculo['resultados'] as $linha) {
                ResultadoSequenciaSopro::create([
                    'programacao_sopro_id' => $programacao->id,
                    'item_id'              => $linha['item_id'],
                    'tipo'                 => $linha['tipo'],
                    'sku'                  => $linha['sku'],
                    'inicio'               => $linha['inicio'],
                    'fim'                  => $linha['fim'],
                    'duracao_minutos'      => $linha['duracao_minutos'],
                    'quantidade_estimada'  => $linha['quantidade_estimada'] ?? 0,
                    'memoria_calculo'      => $linha['memoria_calculo'],
                ]);
            }

            $programacao->update(['status' => 'calculada']);

            return $programacao->fresh(['itens', 'resultados']);
        });

        return [
            'programacao' => $programacao,
            'resultados'  => $resultadoCalculo['resultados'],
            'resumo'      => $resultadoCalculo['resumo'],
        ];
    }

    private function validarProgramacao(ProgramacaoSopro $programacao): void
    {
        if (! $programacao->estaEditavel()) {
            throw new RuntimeException(
                "A programação #{$programacao->id} não pode ser recalculada no status '{$programacao->status}'. " .
                "Apenas programações em 'rascunho' ou 'calculada' podem ser recalculadas."
            );
        }

        if ($programacao->itens->isEmpty()) {
            throw new RuntimeException(
                "A programação #{$programacao->id} não possui itens. " .
                "Adicione pelo menos um item antes de calcular."
            );
        }

        if ($programacao->maquina->calendarioSopro === null) {
            throw new RuntimeException(
                "A máquina '{$programacao->maquina->codigo}' não possui calendário configurado. " .
                "Configure os turnos antes de calcular."
            );
        }
    }
}
