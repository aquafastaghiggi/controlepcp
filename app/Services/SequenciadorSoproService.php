<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Frasco;
use App\Models\MatrizSetupSopro;
use App\Models\ProgramacaoSopro;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Engine de cálculo de sequenciamento para o módulo Sopro.
 *
 * Equivalente ao SequenciadorService do Envase, adaptado para:
 *   - Frascos em vez de Produtos
 *   - Máquinas em vez de Linhas
 *   - MatrizSetupSopro (troca_cor / troca_molde) por máquina
 *   - CalendarioSopro vinculado à máquina
 */
class SequenciadorSoproService
{
    public function __construct(
        private readonly CalendarioSoproService $calendario
    ) {}

    public function calcular(
        ProgramacaoSopro $programacao,
        ?DateTimeImmutable $momentoConsulta = null
    ): array {
        $programacao->loadMissing([
            'itens',
            'maquina.calendarioSopro.intervalosAtivos',
            'maquina.calendarioSopro.feriados',
        ]);

        $calendario = $programacao->maquina->calendarioSopro
            ?? throw new InvalidArgumentException(
                "A máquina '{$programacao->maquina->codigo}' não possui calendário configurado. " .
                "Configure pelo menos um turno antes de calcular."
            );

        $calendarioId = $calendario->id;
        $maquinaId    = $programacao->maquina_id;
        $eficiencia   = max(1.0, (float) $programacao->eficiencia);
        $diasOverride = $this->normalizarDiasOverride($programacao->dias_selecionados ?? []);
        $inicioBase   = DateTimeImmutable::createFromInterface($programacao->data_inicio_planejada);
        $itens        = $programacao->itens->sortBy('sequencia')->values();

        $resultados       = [];
        $erros            = [];
        $totalSetupMin    = 0;
        $totalProducaoMin = 0;
        $fimPrevisto      = null;
        $skuAnterior      = null;
        $fimAnterior      = null;
        $primeiroItem     = true;

        foreach ($itens as $item) {
            $sku        = trim($item->sku);
            // itens_programacao_sopro.quantidade vem do Colemar em milheiros
            // (ex.: 7 = 7.000 frascos) — ×1000 pra comparar/calcular em unidade
            // (taxa_por_hora, produção real do CODI). Mesma conversão já usada em
            // GravarPrevistoHoje.php, TvStaticSoproController.php, etc.
            $quantidade = (float) $item->quantidade * 1000;

            $frasco = Frasco::where('sku', $sku)->first();

            if ($frasco === null) {
                $erros[] = "SKU '{$sku}' (item #{$item->sequencia}) não encontrado no cadastro de frascos.";
                continue;
            }

            if ((float) $frasco->taxa_por_hora <= 0) {
                $erros[] = "SKU '{$sku}' possui taxa por hora inválida ({$frasco->taxa_por_hora}). Cadastre a taxa antes de calcular.";
                continue;
            }

            $taxaEfetiva     = $this->calcularTaxaEfetiva((float) $frasco->taxa_por_hora, $eficiencia);
            $minutosProducao = $this->calcularMinutosProducao($quantidade, $taxaEfetiva);

            // ── Setup ────────────────────────────────────────────────────────
            if (! $primeiroItem && $skuAnterior !== null) {
                $minutosSetup = MatrizSetupSopro::buscarDuracao($skuAnterior, $sku, $maquinaId);
                $tipoSetup    = MatrizSetupSopro::buscarTipo($skuAnterior, $sku, $maquinaId);

                if ($minutosSetup > 0) {
                    $planoSetup = $this->calendario->distribuirMinutos(
                        $fimAnterior,
                        $minutosSetup,
                        $calendarioId,
                        $diasOverride
                    );

                    $totalSetupMin += $minutosSetup;

                    $tipoLabel = match($tipoSetup) {
                        'troca_cor'   => 'Troca de Cor',
                        'troca_molde' => 'Troca de Molde',
                        default       => 'Setup',
                    };

                    $resultados[] = [
                        'item_id'             => null,
                        'tipo'                => 'setup',
                        'sku'                 => $sku,
                        'descricao'           => "{$tipoLabel}: {$skuAnterior} → {$sku}",
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
                'descricao'           => $frasco->descricao,
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

    private function normalizarDiasOverride(array $rawDias): array
    {
        if (empty($rawDias)) {
            return [];
        }

        $firstKey = (string) array_key_first($rawDias);

        if (strlen($firstKey) === 10) {
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
                "Taxa efetiva inválida: {$taxaEfetiva}. Verifique a taxa do frasco e a eficiência."
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
