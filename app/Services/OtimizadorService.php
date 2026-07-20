<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\MatrizSetup;
use Carbon\Carbon;
use DateTimeImmutable;

/**
 * Otimizador de sequência de produção — diferencial da V2.
 *
 * Problema: dado um conjunto de ordens (SKU + quantidade + prazo),
 * encontrar a sequência que minimize uma função de custo ponderada:
 *
 *   Custo = (PESO_SETUP × setup_normalizado) + (PESO_PRAZO × atraso_normalizado)
 *
 * Onde:
 *   - setup_normalizado  = minutos_de_setup / max_setup_possivel  (0–1)
 *   - atraso_normalizado = urgência_do_prazo / max_urgência  (0–1)
 *
 * Algoritmos:
 *   1. Nearest Neighbor  — O(n²), rápido, gera solução inicial de qualidade
 *   2. Simulated Annealing — melhoria iterativa, escapa de mínimos locais
 *
 * Modos de uso:
 *   - SUGESTÃO (otimizar): retorna a sequência ótima para o usuário revisar
 *   - A CalcularSequenciaAction aplica diretamente quando otimizarSequencia=true
 */
class OtimizadorService
{
    /**
     * Pesos da função de custo.
     * PESO_SETUP + PESO_PRAZO devem somar 1.0.
     * Ajustar conforme a política da empresa: mais urgência de prazo → aumentar PESO_PRAZO.
     */
    private const PESO_SETUP = 0.4;
    private const PESO_PRAZO = 0.6;

    /**
     * Parâmetros do Simulated Annealing.
     *
     * TEMPERATURA_INICIAL alta → aceita movimentos ruins no início (exploração)
     * FATOR_RESFRIAMENTO próximo de 1 → esfria mais devagar (melhor qualidade, mais lento)
     * ITERACOES_POR_TEMP → quantas trocas tenta em cada temperatura
     */
    private const TEMPERATURA_INICIAL = 1000.0;
    private const TEMPERATURA_MINIMA  = 0.1;
    private const FATOR_RESFRIAMENTO  = 0.995;
    private const ITERACOES_POR_TEMP  = 100;

    /**
     * Cache de lookups na matriz de setup.
     * Evita N+1 queries durante os loops de otimização.
     *
     * @var array<string, int>  chave: "sku_a|sku_b"
     */
    private array $cacheSetup = [];

    // ─── Interface pública ───────────────────────────────────────────────────

    /**
     * Otimiza a sequência de itens minimizando setup + urgência de prazo.
     *
     * @param  array<int, array{sku: string, quantidade: float, prazo_entrega: Carbon|null}>  $itens
     * @param  DateTimeImmutable  $inicioDisponivel  quando a linha estará livre para produzir
     * @return array{
     *   sequencia_otimizada: array,
     *   setup_total_minutos: int,
     *   setup_economizado_minutos: int,
     *   score: float,
     *   detalhamento: array
     * }
     */
    public function otimizar(
        array $itens,
        DateTimeImmutable $inicioDisponivel
    ): array {
        $this->cacheSetup = []; // limpa cache a cada otimização

        if (count($itens) <= 1) {
            return [
                'sequencia_otimizada'        => $itens,
                'setup_total_minutos'        => 0,
                'setup_economizado_minutos'  => 0,
                'score'                      => 0.0,
                'detalhamento'               => [],
            ];
        }

        $setupOriginal = $this->calcularSetupTotalDaSequencia(
            array_column($itens, 'sku')
        );

        // Fase 1: Nearest Neighbor constrói uma boa solução inicial
        $sequenciaNN = $this->nearestNeighbor($itens, $inicioDisponivel);

        // Fase 2: Simulated Annealing melhora a solução do Nearest Neighbor
        $sequenciaOtima = $this->simulatedAnnealing($sequenciaNN, $inicioDisponivel);

        $setupOtimizado = $this->calcularSetupTotalDaSequencia(
            array_column($sequenciaOtima, 'sku')
        );

        $score        = $this->calcularCustoSequencia($sequenciaOtima, $inicioDisponivel);
        $detalhamento = $this->montarDetalhamento($sequenciaOtima, $inicioDisponivel);

        return [
            'sequencia_otimizada'       => array_values($sequenciaOtima),
            'setup_total_minutos'       => $setupOtimizado,
            'setup_economizado_minutos' => max(0, $setupOriginal - $setupOtimizado),
            'score'                     => round($score, 4),
            'detalhamento'              => $detalhamento,
        ];
    }

    /**
     * Calcula o custo de uma sequência dada (sem otimizar).
     * Útil para comparar diferentes sequências entre si.
     */
    public function calcularCustoSequencia(array $sequencia, DateTimeImmutable $inicio): float
    {
        if (count($sequencia) <= 1) {
            return 0.0;
        }

        // Coleta todos os custos de transição para poder normalizar
        $custos = [];
        $momento = $inicio;

        for ($i = 1; $i < count($sequencia); $i++) {
            $custos[] = $this->calcularCustoTransicao(
                $sequencia[$i - 1],
                $sequencia[$i],
                $momento
            );
        }

        // Normaliza e soma ponderando pelo peso definido
        $maxCusto = max($custos) ?: 1.0;

        return array_sum(
            array_map(fn ($custo) => $this->normalizar($custo, $maxCusto), $custos)
        );
    }

    // ─── Algoritmos de otimização ────────────────────────────────────────────

    /**
     * Nearest Neighbor com custo ponderado (setup + urgência de prazo).
     *
     * A cada passo, escolhe o item não visitado com menor custo de transição
     * a partir do item atual. O custo considera tanto o setup quanto a urgência
     * do prazo de entrega do próximo item.
     *
     * Complexidade: O(n²) — eficiente para até ~500 itens.
     */
    private function nearestNeighbor(array $itens, DateTimeImmutable $inicioDisponivel): array
    {
        $pendentes = $itens;
        $sequencia = [];
        $momento   = $inicioDisponivel;

        // Começa pelo item com prazo mais urgente para garantir prioridade inicial
        usort($pendentes, fn ($a, $b) => $this->compararUrgencia($a, $b, $momento));
        $atual     = array_shift($pendentes);
        $sequencia[] = $atual;

        while (! empty($pendentes)) {
            $melhorCusto = PHP_FLOAT_MAX;
            $melhorIndice = 0;

            foreach ($pendentes as $indice => $candidato) {
                $custo = $this->calcularCustoTransicao($atual, $candidato, $momento);

                if ($custo < $melhorCusto) {
                    $melhorCusto  = $custo;
                    $melhorIndice = $indice;
                }
            }

            $proxItem  = $pendentes[$melhorIndice];
            $sequencia[] = $proxItem;
            unset($pendentes[$melhorIndice]);
            $pendentes = array_values($pendentes);
            $atual     = $proxItem;
        }

        return $sequencia;
    }

    /**
     * Simulated Annealing para refinamento da solução inicial.
     *
     * Começa quente (aceita pioras para escapar de mínimos locais)
     * e vai esfriando gradualmente até aceitar apenas melhorias.
     *
     * A cada temperatura: sorteia dois itens aleatórios, troca suas posições,
     * aceita a troca se melhora o custo ou com probabilidade e^(-delta/T).
     */
    private function simulatedAnnealing(array $sequenciaInicial, DateTimeImmutable $inicio): array
    {
        $melhorSequencia = $sequenciaInicial;
        $melhorCusto     = $this->calcularCustoSequencia($melhorSequencia, $inicio);
        $sequenciaAtual  = $sequenciaInicial;
        $custoAtual      = $melhorCusto;
        $temperatura     = self::TEMPERATURA_INICIAL;
        $n               = count($sequenciaInicial);

        if ($n <= 2) {
            return $sequenciaInicial;
        }

        while ($temperatura > self::TEMPERATURA_MINIMA) {
            for ($iter = 0; $iter < self::ITERACOES_POR_TEMP; $iter++) {
                // Sorteia dois índices distintos aleatórios
                $i = random_int(0, $n - 1);
                do {
                    $j = random_int(0, $n - 1);
                } while ($j === $i);

                // Aplica a troca de posição
                $novaSequencia        = $sequenciaAtual;
                [$novaSequencia[$i], $novaSequencia[$j]] = [$novaSequencia[$j], $novaSequencia[$i]];

                $novoCusto = $this->calcularCustoSequencia($novaSequencia, $inicio);
                $delta     = $novoCusto - $custoAtual;

                // Aceita melhoria sempre; piora com probabilidade e^(-delta/T)
                if ($delta < 0 || (mt_rand() / mt_getrandmax()) < exp(-$delta / $temperatura)) {
                    $sequenciaAtual = $novaSequencia;
                    $custoAtual     = $novoCusto;

                    // Guarda o melhor global encontrado
                    if ($custoAtual < $melhorCusto) {
                        $melhorSequencia = $sequenciaAtual;
                        $melhorCusto     = $custoAtual;
                    }
                }
            }

            $temperatura *= self::FATOR_RESFRIAMENTO;
        }

        return $melhorSequencia;
    }

    // ─── Funções de custo ────────────────────────────────────────────────────

    /**
     * Calcula o custo de transição entre dois itens consecutivos.
     *
     * Combina:
     *   - Custo de setup: minutos de troca entre os SKUs
     *   - Custo de prazo: urgência do próximo item (quanto mais perto o prazo, maior o custo de adiar)
     *
     * Ambos normalizados com base nos máximos históricos para comparação justa.
     */
    private function calcularCustoTransicao(
        array $itemAnterior,
        array $itemAtual,
        DateTimeImmutable $momentoAtual
    ): float {
        $minutosSetup = $this->setupEntre($itemAnterior['sku'], $itemAtual['sku']);

        // Custo de prazo: quanto mais urgente, mais caro é adiar este item
        $custoSetup = (float) $minutosSetup;
        $custoPrazo = $this->calcularCustoPrazo($itemAtual, $momentoAtual);

        // Normaliza individualmente antes de ponderar
        // Referência: setup de 120min e prazo de 1 dia como máximos padrão
        $setupNorm = $this->normalizar($custoSetup, 120.0);
        $prazoNorm = $this->normalizar($custoPrazo, 1440.0); // 1440min = 1 dia

        return (self::PESO_SETUP * $setupNorm) + (self::PESO_PRAZO * $prazoNorm);
    }

    /**
     * Calcula o custo de prazo para um item.
     *
     * Retorna o número de minutos restantes até o prazo (negativo = já atrasado).
     * Itens sem prazo recebem custo zero (não há urgência de prazo).
     * Itens atrasados recebem custo máximo (prioridade máxima).
     */
    private function calcularCustoPrazo(array $item, DateTimeImmutable $momentoAtual): float
    {
        if (empty($item['prazo_entrega'])) {
            return 0.0; // sem prazo = sem urgência
        }

        $prazo = $item['prazo_entrega'] instanceof Carbon
            ? DateTimeImmutable::createFromMutable($item['prazo_entrega']->toDateTime())
            : new DateTimeImmutable((string) $item['prazo_entrega']);

        $minutosAtePrazo = ($prazo->getTimestamp() - $momentoAtual->getTimestamp()) / 60;

        if ($minutosAtePrazo <= 0) {
            // Já atrasado — custo máximo para priorizar
            return 9999.0;
        }

        // Inversão: menos minutos até o prazo = maior urgência = maior custo de adiar
        return 10000.0 / ($minutosAtePrazo + 1);
    }

    /**
     * Compara dois itens por urgência de prazo.
     * Usada para escolher o ponto de partida do Nearest Neighbor.
     */
    private function compararUrgencia(array $a, array $b, DateTimeImmutable $momento): int
    {
        $custoA = $this->calcularCustoPrazo($a, $momento);
        $custoB = $this->calcularCustoPrazo($b, $momento);

        // Maior custo = mais urgente = vem primeiro (decrescente)
        return $custoB <=> $custoA;
    }

    // ─── Funções de apoio ────────────────────────────────────────────────────

    /**
     * Calcula o setup total de uma lista de SKUs em sequência.
     * Usado para comparar o antes e depois da otimização.
     */
    private function calcularSetupTotalDaSequencia(array $skus): int
    {
        $total = 0;

        for ($i = 1; $i < count($skus); $i++) {
            $total += $this->setupEntre($skus[$i - 1], $skus[$i]);
        }

        return $total;
    }

    /**
     * Busca o setup entre dois SKUs com cache interno.
     * Cache evita queries repetidas no banco durante os loops de otimização.
     */
    private function setupEntre(string $skuOrigem, string $skuDestino): int
    {
        $chave = "{$skuOrigem}|{$skuDestino}";

        if (! isset($this->cacheSetup[$chave])) {
            $this->cacheSetup[$chave] = MatrizSetup::buscarDuracao($skuOrigem, $skuDestino);
        }

        return $this->cacheSetup[$chave];
    }

    /**
     * Normaliza um valor para escala 0–1 dado o máximo de referência.
     * Evita divisão por zero retornando 0 quando o máximo é zero.
     */
    private function normalizar(float $valor, float $maximo): float
    {
        if ($maximo <= 0) {
            return 0.0;
        }

        return min(1.0, $valor / $maximo);
    }

    /**
     * Monta o array de detalhamento para o retorno da otimização.
     * Exibe para o usuário o porquê de cada transição na sequência escolhida.
     */
    private function montarDetalhamento(array $sequencia, DateTimeImmutable $inicio): array
    {
        $detalhamento = [];
        $momento      = $inicio;

        for ($i = 0; $i < count($sequencia); $i++) {
            $item          = $sequencia[$i];
            $skuAnterior   = $i > 0 ? $sequencia[$i - 1]['sku'] : null;
            $minutosSetup  = $skuAnterior ? $this->setupEntre($skuAnterior, $item['sku']) : 0;
            $custoPrazo    = $this->calcularCustoPrazo($item, $momento);

            $detalhamento[] = [
                'posicao'          => $i + 1,
                'sku'              => $item['sku'],
                'sku_anterior'     => $skuAnterior,
                'setup_minutos'    => $minutosSetup,
                'custo_prazo'      => round($custoPrazo, 2),
                'prazo_entrega'    => isset($item['prazo_entrega'])
                    ? (string) $item['prazo_entrega']
                    : null,
            ];
        }

        return $detalhamento;
    }
}
