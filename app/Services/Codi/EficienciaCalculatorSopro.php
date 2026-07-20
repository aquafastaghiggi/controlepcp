<?php

declare(strict_types=1);

namespace App\Services\Codi;

use App\Models\Codi\CodiEficienciaSopro;
use App\Models\Codi\CodiEvento;
use App\Models\ProgramacaoSopro;
use Carbon\Carbon;

/**
 * Calcula KPIs de eficiência para o módulo Sopro.
 * Equivalente ao EficienciaCalculator do Envase, adaptado para:
 *   - ProgramacaoSopro em vez de Programacao
 *   - Frasco (taxa_por_hora) em vez de Produto
 *   - CodiEficienciaSopro em vez de CodiEficiencia
 *   - programacao_sopro_id como chave
 */
class EficienciaCalculatorSopro
{
    public function calcularParaProgramacao(int $programacaoId): array
    {
        $programacao = ProgramacaoSopro::with(['itens.frasco', 'resultados', 'maquina'])
            ->findOrFail($programacaoId);

        $resultados = [];

        foreach ($programacao->itens as $item) {
            $resultadoPlano = $programacao->resultados
                ->where('tipo', 'producao')
                ->where('item_id', $item->id)
                ->first();

            if (!$resultadoPlano) continue;

            $numeroOp = $item->numero_op ?? (string) $item->sequencia;

            $eventos = CodiEvento::where('ordem_producao', $numeroOp)
                ->orderBy('inicio_evento')
                ->get();

            // itens_programacao_sopro.quantidade vem do Colemar em milheiros (ex.: 40 =
            // 40.000 frascos) — ×1000 pra comparar com a produção real do CODI (unidade).
            $qtdProgramada      = (float) $item->quantidade * 1000;
            $taxaPorHora        = (float) ($item->frasco?->taxa_por_hora ?? 0);
            $tempoPadraoNominal = ($taxaPorHora > 0)
                ? (int) ceil(($qtdProgramada / $taxaPorHora) * 60)
                : 0;

            if ($eventos->isEmpty()) {
                CodiEficienciaSopro::updateOrCreate(
                    ['programacao_sopro_id' => $programacaoId, 'numero_op' => $numeroOp],
                    [
                        'sku'                   => $item->sku,
                        'quantidade_programada' => $qtdProgramada,
                        'tempo_padrao_minutos'  => $resultadoPlano->duracao_minutos,
                        'tempo_padrao_nominal'  => $tempoPadraoNominal ?: null,
                        'inicio_previsto'       => $resultadoPlano->inicio,
                        'fim_previsto'          => $resultadoPlano->fim,
                        'status'                => 'pendente',
                    ]
                );
                continue;
            }

            $qtdRealizada   = (float) $eventos->where('tipo_evento', 'PRODUCAO')->sum('quantidade');
            $tempoRealMin   = (int)   $eventos->sum('duracao_minutos');
            $tempoParadoMin = (int)   $eventos->where('tipo_evento', 'PARADA')->sum('duracao_minutos');
            $inicioReal     = $eventos->where('tipo_evento', 'PRODUCAO')->where('quantidade', '>', 0)->min('inicio_evento');
            $fimReal        = $eventos->max('fim_evento');

            $tempoPadraoMin = (int) $resultadoPlano->duracao_minutos;
            $tempoTotalMin  = $tempoRealMin + $tempoParadoMin;

            $eficienciaQtd = $qtdProgramada > 0
                ? round(($qtdRealizada / $qtdProgramada) * 100, 2)
                : 0.0;

            $tempoRealProducaoMin = $tempoRealMin - $tempoParadoMin;
            $performanceTempo = ($taxaPorHora > 0 && $tempoRealProducaoMin > 0)
                ? min(100, round(($qtdRealizada / ($taxaPorHora / 60)) / $tempoRealProducaoMin * 100, 2))
                : null;

            $disponibilidade = $tempoTotalMin > 0
                ? round((($tempoTotalMin - $tempoParadoMin) / $tempoTotalMin) * 100, 2)
                : 100.0;

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

            $desvioPrazo = $fimReal
                ? (int) Carbon::parse($resultadoPlano->fim)->diffInDays(Carbon::parse($fimReal), false)
                : null;

            $status = $this->classificarStatus($oee, $eficienciaQtd, $desvioPrazo ?? 0);

            CodiEficienciaSopro::updateOrCreate(
                ['programacao_sopro_id' => $programacaoId, 'numero_op' => $numeroOp],
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
                    'desvio_prazo_dias'      => $desvioPrazo,
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
        $oeeEfetivo = $oee ?? 100.0;
        if ($oeeEfetivo < 50 || $eficiencia < 70 || $diasAtraso > 5) return 'critico';
        if ($oeeEfetivo < 75 || $eficiencia < 85 || $diasAtraso > 2) return 'aviso';
        return 'ok';
    }
}
