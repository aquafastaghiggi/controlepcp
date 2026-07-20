<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\MatrizSetup;
use App\Models\Produto;
use App\Models\Programacao;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Engine principal de cálculo de sequenciamento de produção.
 *
 * Recebe uma Programacao com seus itens já na sequência desejada
 * e calcula para cada item:
 *   1. Tempo de setup (troca) usando a matriz de tempos cadastrada
 *   2. Tempo de produção = quantidade ÷ taxa efetiva (considerando eficiência)
 *   3. Janelas reais de trabalho (distribuídas nos turnos via CalendarioService)
 *   4. Quantidade estimada produzida até um momento de consulta
 *   5. Memória de cálculo detalhada para auditoria
 *
 * NÃO decide a sequência — apenas calcula a que foi recebida.
 */
class SequenciadorService
{
    public function __construct(
        private readonly CalendarioService $calendario
    ) {}

    /**
     * Ponto de entrada principal.
     *
     * @return array{
     *   resultados: array<array>,
     *   erros: array<string>,
     *   resumo: array{total_setup_min: int, total_producao_min: int, fim_previsto: string|null}
     * }
     */
    public function calcular(
        Programacao $programacao,
        ?DateTimeImmutable $momentoConsulta = null
    ): array {
        $programacao->loadMissing([
            'itens',
            'linha.calendario.intervalosAtivos.diasUteis',
            'linha.calendario.feriados',
        ]);

        $calendario = $programacao->linha->calendario
            ?? throw new InvalidArgumentException(
                "A linha '{$programacao->linha->nome}' não possui calendário configurado. " .
                "Configure pelo menos um turno antes de calcular."
            );

        $calendarioId = $calendario->id;
        $eficiencia   = max(1.0, (float) $programacao->eficiencia);

        // Normalizar $diasOverride para o CalendarioService
        $diasOverride = $this->normalizarDiasOverride($programacao->dias_selecionados ?? []);

        $inicioBase = DateTimeImmutable::createFromInterface($programacao->data_inicio_planejada);
        $itens      = $programacao->itens->sortBy('sequencia')->values();

        $resultados       = [];
        $erros            = [];
        $totalSetupMin    = 0;
        $totalProducaoMin = 0;
        $fimPrevisto      = null;
        $skuAnterior      = null;
        $fimAnterior      = null;
        $primeiroItem     = true;

        foreach ($itens as $item) {
            $sku       = trim($item->sku);
            $quantidade = (float) $item->quantidade;

            $produto = Produto::where('sku', $sku)->first();

            if ($produto === null) {
                $erros[] = "SKU '{$sku}' (item #{$item->sequencia}) não encontrado no cadastro de produtos.";
                continue;
            }

            if ((float) $produto->taxa_por_hora <= 0) {
                $erros[] = "SKU '{$sku}' possui taxa por hora inválida ({$produto->taxa_por_hora}).";
                continue;
            }

            $taxaEfetiva     = $this->calcularTaxaEfetiva((float) $produto->taxa_por_hora, $eficiencia);
            $minutosProducao = $this->calcularMinutosProducao($quantidade, $taxaEfetiva);

            // ── Setup ────────────────────────────────────────────────────────
            if (! $primeiroItem && $skuAnterior !== null) {
                $minutosSetup = MatrizSetup::buscarDuracao($skuAnterior, $sku);

                if ($minutosSetup > 0) {
                    $planoSetup = $this->calendario->distribuirMinutos(
                        $fimAnterior,
                        $minutosSetup,
                        $calendarioId,
                        $diasOverride
                    );

                    $totalSetupMin += $minutosSetup;

                    $resultados[] = [
                        'item_id'             => null,
                        'tipo'                => 'setup',
                        'sku'                 => $sku,
                        'descricao'           => "Setup: {$skuAnterior} → {$sku}",
                        'quantidade'          => null,
                        'taxa_efetiva'        => null,
                        'duracao_minutos'     => $minutosSetup,
                        'inicio'              => $fimAnterior,
                        'fim'                 => $planoSetup['fim'],
                        'quantidade_estimada' => null,
                        'memoria_calculo'     => $planoSetup['memoria'],
                    ];

                    $fimAnterior = $planoSetup['fim'];
                }
            }

            // ── Início da produção ───────────────────────────────────────────
            $inicioProducao = $primeiroItem
                ? $this->calendario->proximoMomentoValido($inicioBase, $calendarioId, $diasOverride)
                : $this->calendario->proximoMomentoValido($fimAnterior, $calendarioId, $diasOverride);

            // ── Produção ─────────────────────────────────────────────────────
            $planoProducao = $this->calendario->distribuirMinutos(
                $inicioProducao,
                $minutosProducao,
                $calendarioId,
                $diasOverride
            );

            $fimProducao       = $planoProducao['fim'];
            $totalProducaoMin += $minutosProducao;
            $fimPrevisto       = $fimProducao;

            $quantidadeEstimada = $this->estimarProduzido(
                $quantidade,
                $taxaEfetiva,
                $inicioProducao,
                $fimProducao,
                $momentoConsulta,
                $calendarioId,
                $diasOverride
            );

            $resultados[] = [
                'item_id'             => $item->id,
                'tipo'                => 'producao',
                'sku'                 => $sku,
                'descricao'           => $produto->descricao,
                'quantidade'          => $quantidade,
                'taxa_efetiva'        => round($taxaEfetiva, 4),
                'duracao_minutos'     => $minutosProducao,
                'inicio'              => $inicioProducao,
                'fim'                 => $fimProducao,
                'quantidade_estimada' => round($quantidadeEstimada, 2),
                'memoria_calculo'     => $planoProducao['memoria'],
            ];

            $skuAnterior  = $sku;
            $fimAnterior  = $fimProducao;
            $primeiroItem = false;
        }

        return [
            'resultados' => $resultados,
            'erros'      => $erros,
            'resumo'     => [
                'total_setup_min'    => $totalSetupMin,
                'total_producao_min' => $totalProducaoMin,
                'fim_previsto'       => $fimPrevisto?->format('Y-m-d H:i:s'),
            ],
        ];
    }

    // ─── Privados ────────────────────────────────────────────────────────────

    /**
     * Normaliza dias_selecionados da Programacao para o formato esperado pelo CalendarioService.
     *
     * Formato data (novo):  ['Y-m-d' => ['dia_semana' => int, 'turnos' => [id, ...]]]
     * Formato legado:       [diaSemana => [turnoId, ...]] com chaves int (string após JSON)
     */
    private function normalizarDiasOverride(array $rawDias): array
    {
        if (empty($rawDias)) {
            return [];
        }

        $firstKey = (string) array_key_first($rawDias);

        if (strlen($firstKey) === 10) {
            // Formato data: normalizar turnoIds para int
            $override = [];
            foreach ($rawDias as $data => $info) {
                $override[$data] = [
                    'dia_semana' => (int) ($info['dia_semana'] ?? 0),
                    'turnos'     => is_array($info['turnos'] ?? null)
                        ? array_values(array_map('intval', $info['turnos']))
                        : [],
                ];
            }
            return $override;
        }

        // Formato legado: converter chave string para int
        $override = [];
        foreach ($rawDias as $dia => $turnos) {
            $override[(int) $dia] = is_array($turnos) ? array_values(array_map('intval', $turnos)) : [];
        }
        return $override;
    }

    private function calcularTaxaEfetiva(float $taxaPorHora, float $eficiencia): float
    {
        return $taxaPorHora * ($eficiencia / 100.0);
    }

    private function calcularMinutosProducao(float $quantidade, float $taxaEfetiva): int
    {
        if ($taxaEfetiva <= 0) {
            throw new InvalidArgumentException(
                "Taxa efetiva inválida: {$taxaEfetiva}. Verifique a taxa do produto e a eficiência."
            );
        }

        return (int) ceil(($quantidade / $taxaEfetiva) * 60);
    }

    private function estimarProduzido(
        float $quantidade,
        float $taxaEfetiva,
        DateTimeImmutable $inicioProducao,
        DateTimeImmutable $fimProducao,
        ?DateTimeImmutable $momentoConsulta,
        int $calendarioId,
        array $diasOverride
    ): float {
        if ($momentoConsulta === null || $momentoConsulta <= $inicioProducao) {
            return 0.0;
        }

        if ($momentoConsulta >= $fimProducao) {
            return $quantidade;
        }

        $minutosDecorridos = $this->calendario->minutosUteisEntre(
            $inicioProducao,
            $momentoConsulta,
            $calendarioId,
            $diasOverride
        );

        $estimado = ($minutosDecorridos / 60.0) * $taxaEfetiva;

        return min($quantidade, max(0.0, $estimado));
    }
}
