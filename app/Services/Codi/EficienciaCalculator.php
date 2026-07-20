<?php

declare(strict_types=1);

namespace App\Services\Codi;

use App\Models\Codi\CodiEficiencia;
use App\Models\Codi\CodiEvento;
use App\Models\Programacao;
use Carbon\Carbon;

/**
 * Calcula KPIs de eficiência cruzando dados do PCP (programado)
 * com dados do CODI (realizado).
 *
 * Fórmulas:
 *   eficiencia_quantidade = (realizado / programado) × 100
 *   performance_tempo     = (tempo_padrao_nominal / tempo_real) × 100
 *                           onde tempo_padrao_nominal = CEIL((qtd / taxa_por_hora) × 60)
 *                           sem desconto de eficiência da programação (taxa nominal pura)
 *   disponibilidade       = (tempo_total - tempo_parado) / tempo_total × 100
 *   OEE                   = eficiencia × performance × disponibilidade / 10.000
 *
 * Nota: tempo_padrao_minutos (com desconto de eficiência) é mantido para
 * desvio_tempo_horas e dados planejados. Apenas performance_tempo usa
 * tempo_padrao_nominal para refletir a taxa nominal da máquina.
 *
 * Thresholds:
 *   OEE ≥ 75% + eficiencia ≥ 85% → ok
 *   OEE 50-75% ou eficiencia 70-85% → aviso
 *   OEE < 50% ou eficiencia < 70%   → critico
 */
class EficienciaCalculator
{
    /**
     * Calcula e persiste eficiência para todas as OPs de uma programação.
     */
    public function calcularParaProgramacao(int $programacaoId): array
    {
        $programacao = Programacao::with(['itens.produto', 'resultados', 'linha'])
            ->findOrFail($programacaoId);

        $resultados = [];

        foreach ($programacao->itens as $item) {
            // P1: Usa item_id (chave estrangeira exata) em vez de SKU.
            // Quando o mesmo SKU aparece em múltiplos itens da programação,
            // ->where('sku')->first() sempre retornava o primeiro resultado,
            // atribuindo os dados de horário e duração errados aos itens seguintes.
            $resultadoPlano = $programacao->resultados
                ->where('tipo', 'producao')
                ->where('item_id', $item->id)
                ->first();

            if (!$resultadoPlano) continue;

            // Usar numero_op do item se disponível, senão usar sequencia
            $numeroOp = $item->numero_op ?? (string) $item->sequencia;

            // P2: Sem filtro de data — captura inícios antecipados e atrasos reais.
            // numero_op é único por OP no CODI, então não há risco de contaminação cruzada.
            // Sem filtro de data — numero_op já garante que são eventos desta OP específica.
            // O risco de contaminação por re-programações é baixo pois numero_op é único por OP.
            $eventos = CodiEvento::where('ordem_producao', $numeroOp)
                ->orderBy('inicio_evento')
                ->get();

            // Tempo padrão nominal: sem desconto de eficiência — reflete taxa nominal da máquina.
            // Usado exclusivamente em performance_tempo para evitar inflação artificial
            // quando a programação foi calculada com eficiência < 100%.
            $qtdProgramada      = (float) $item->quantidade;
            $taxaPorHora        = (float) ($item->produto?->taxa_por_hora ?? 0);
            $tempoPadraoNominal = ($taxaPorHora > 0)
                ? (int) ceil(($qtdProgramada / $taxaPorHora) * 60)
                : 0;

            if ($eventos->isEmpty()) {
                CodiEficiencia::updateOrCreate(
                    ['programacao_id' => $programacaoId, 'numero_op' => $numeroOp],
                    [
                        'sku'                    => $item->sku,
                        'quantidade_programada'  => $qtdProgramada,
                        'tempo_padrao_minutos'   => $resultadoPlano->duracao_minutos,
                        'tempo_padrao_nominal'   => $tempoPadraoNominal ?: null,
                        'inicio_previsto'        => $resultadoPlano->inicio,
                        'fim_previsto'           => $resultadoPlano->fim,
                        'status'                 => 'pendente',
                    ]
                );
                continue;
            }

            $qtdRealizada   = (float) $eventos->where('tipo_evento', 'PRODUCAO')->sum('quantidade');
            $tempoRealMin   = (int)   $eventos->sum('duracao_minutos');
            $tempoParadoMin = (int)   $eventos->where('tipo_evento', 'PARADA')->sum('duracao_minutos');
            $inicioReal     = $eventos
                ->where('tipo_evento', 'PRODUCAO')
                ->where('quantidade', '>', 0)
                ->min('inicio_evento');
            $fimReal        = $eventos->max('fim_evento');

            $tempoPadraoMin = (int) $resultadoPlano->duracao_minutos;
            $tempoTotalMin  = $tempoRealMin + $tempoParadoMin;

            $eficienciaQtd = $qtdProgramada > 0
                ? round(($qtdRealizada / $qtdProgramada) * 100, 2)
                : 0.0;

            // FÓRMULA CORRETA — ritmo real vs taxa nominal
            // Funciona para OPs em andamento e concluídas
            // Usa apenas tempo de PRODUCAO no denominador (exclui paradas)
            $tempoRealProducaoMin = $tempoRealMin - $tempoParadoMin;
            $performanceTempo = ($taxaPorHora > 0 && $tempoRealProducaoMin > 0)
                ? min(100, round(($qtdRealizada / ($taxaPorHora / 60)) / $tempoRealProducaoMin * 100, 2))
                : null;

            $disponibilidade = $tempoTotalMin > 0
                ? round((($tempoTotalMin - $tempoParadoMin) / $tempoTotalMin) * 100, 2)
                : 100.0;

            // OEE = Disponibilidade × Performance × Qualidade
            // Qualidade = 100% (refugo não rastreado no CODI)
            $oee = ($performanceTempo !== null && $disponibilidade !== null)
                ? round(($performanceTempo * $disponibilidade) / 100, 2)
                : null;

            $produtividade = $tempoRealMin > 0
                ? round($qtdRealizada / ($tempoRealMin / 60), 2)
                : 0.0;

            $desvioQtd    = $qtdRealizada - $qtdProgramada;
            $desvioQtdPct = $qtdProgramada > 0
                ? round(($desvioQtd / $qtdProgramada) * 100, 2)
                : 0.0;

            $desvioTempoH = round(($tempoRealMin - $tempoPadraoMin) / 60, 2);

            $desvioPrezo = $fimReal
                ? (int) Carbon::parse($resultadoPlano->fim)
                    ->diffInDays(Carbon::parse($fimReal), false)
                : null;

            $status = $this->classificarStatus($oee, $eficienciaQtd, $desvioPrezo ?? 0);

            CodiEficiencia::updateOrCreate(
                ['programacao_id' => $programacaoId, 'numero_op' => $numeroOp],
                [
                    'sku'                    => $item->sku,
                    'quantidade_programada'  => $qtdProgramada,
                    'quantidade_realizada'   => $qtdRealizada,
                    'tempo_padrao_minutos'   => $tempoPadraoMin,
                    'tempo_padrao_nominal'   => $tempoPadraoNominal ?: null,
                    'tempo_real_minutos'     => $tempoRealMin,
                    'tempo_parado_minutos'   => $tempoParadoMin,
                    'inicio_previsto'        => $resultadoPlano->inicio,
                    'fim_previsto'           => $resultadoPlano->fim,
                    'inicio_real'            => $inicioReal,
                    'fim_real'               => $fimReal,
                    'eficiencia_quantidade'  => $eficienciaQtd,
                    'performance_tempo'      => $performanceTempo,
                    'disponibilidade'        => $disponibilidade,
                    'oee'                    => $oee,
                    'produtividade'          => $produtividade,
                    'desvio_quantidade'      => $desvioQtd,
                    'desvio_quantidade_pct'  => $desvioQtdPct,
                    'desvio_tempo_horas'     => $desvioTempoH,
                    'desvio_prazo_dias'      => $desvioPrezo,
                    'status'                 => $status,
                    'calculado_em'           => now(),
                ]
            );

            $resultados[] = ['op' => $numeroOp, 'oee' => $oee, 'status' => $status];
        }

        return $resultados;
    }

    private function classificarStatus(float|null $oee, float $eficiencia, int $diasAtraso): string
    {
        // OEE null significa que taxa_por_hora não está cadastrada — não penaliza o status por isso.
        // A classificação cai para eficiencia e prazo apenas.
        $oeeEfetivo = $oee ?? 100.0;

        if ($oeeEfetivo < 50 || $eficiencia < 70 || $diasAtraso > 5) return 'critico';
        if ($oeeEfetivo < 75 || $eficiencia < 85 || $diasAtraso > 2) return 'aviso';
        return 'ok';
    }
}
