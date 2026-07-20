<?php

declare(strict_types=1);

namespace App\Services\Codi;

use App\Models\Codi\CodiEvento;
use App\Models\Codi\CodiPerformance;
use App\Models\Codi\CodiSincronizacaoLog;
use Illuminate\Support\Facades\Log;

/**
 * Orquestra a sincronização de dados do CODI para o banco local.
 * Todos os métodos são idempotentes (updateOrCreate).
 */
class CodiSyncService
{
    public function __construct(
        private readonly CodiClient $client
    ) {}

    /**
     * Sincroniza tabela de cadência de produção (performance de referência).
     * Endpoint /performance retorna ~422 registros — taxa padrão por item/linha.
     */
    public function sincronizarPerformance(): array
    {
        $inicio = microtime(true);
        $log    = ['novos' => 0, 'atualizados' => 0, 'erros' => 0];

        try {
            $dados = $this->client->getPerformance();

            foreach ($dados as $item) {
                try {
                    $codigoPerformance = $item['codigoPerformance'] ?? null;
                    if (!$codigoPerformance) continue;

                    $existia = CodiPerformance::where('codigo_performance', $codigoPerformance)->exists();

                    CodiPerformance::updateOrCreate(
                        ['codigo_performance' => $codigoPerformance],
                        [
                            'codigo_recurso'  => (string) ($item['grandeza']['recurso']['codigoRecurso'] ?? ''),
                            'nome_recurso'    => $item['grandeza']['recurso']['nomeRecurso']    ?? null,
                            'codigo_item'     => $item['item']['codItem']                        ?? null,
                            'performance'     => $item['performance']                            ?? null,
                            'dados_raw'       => $item,
                            'sincronizado_em' => now(),
                        ]
                    );

                    $existia ? $log['atualizados']++ : $log['novos']++;

                } catch (\Throwable $e) {
                    $log['erros']++;
                    Log::warning('CODI: erro ao salvar performance', ['erro' => $e->getMessage()]);
                }
            }

            $this->registrarLog('performance', 'sucesso', $log, microtime(true) - $inicio);

        } catch (\Throwable $e) {
            $this->registrarLog('performance', 'erro', $log, microtime(true) - $inicio, $e->getMessage());
        }

        return $log;
    }

    /**
     * Sincroniza eventos de produção de um período via relatorioEventoConsolidado.
     *
     * Itera dia a dia — 1 requisição por dia. Para março/2026 = 31 requisições (~15 s).
     * Muito mais eficiente que o endpoint /relatorioEvento que retorna 597k registros.
     */
    public function sincronizarEventos(string $dataInicio, string $dataFim): array
    {
        $inicio = microtime(true);
        $log    = ['novos' => 0, 'atualizados' => 0, 'erros' => 0];

        try {
            $dia    = new \DateTime($dataInicio);
            $limite = new \DateTime($dataFim);

            while ($dia <= $limite) {
                $dataStr = $dia->format('Y-m-d');
                $eventos = $this->client->getEventosDia($dataStr);

                foreach ($eventos as $item) {
                    try {
                        // relatorioEventoConsolidado não tem codigoEvento — gerar chave composta
                        $codigoEvento = $item['codigoEvento']
                            ?? ($item['inicio'] . '|' . ($item['grandeza']['recurso']['codigoRecurso'] ?? '0') . '|' . ($item['estado'] ?? 'X'));
                        if (!$codigoEvento) continue;

                        $existia = CodiEvento::where('codigo_evento', (string) $codigoEvento)->exists();

                        // Normalizar ordem_producao: remover zeros à esquerda para casar com PCP
                        $ordemRaw = $item['ordens'][0]['ordemProducao']['ordem'] ?? null;
                        $ordemNorm = $ordemRaw !== null ? ltrim($ordemRaw, '0') ?: '0' : null;

                        CodiEvento::updateOrCreate(
                            ['codigo_evento' => (string) $codigoEvento],
                            [
                                'codigo_recurso'  => (string) ($item['grandeza']['recurso']['codigoRecurso'] ?? ''),
                                'ordem_producao'  => $ordemNorm,
                                'codigo_item'     => $item['ordens'][0]['ordemProducao']['item']['codItem'] ?? null,
                                'tipo_evento'     => $this->mapearTipoEvento(strtoupper($item['estado'] ?? '')),
                                'quantidade'      => $item['ordens'][0]['quantidadeBoasItem']              ?? null,
                                'inicio_evento'   => $item['inicio']                                       ?? null,
                                'fim_evento'      => $item['fim']                                          ?? null,
                                'duracao_minutos' => isset($item['duracao'])
                                    ? (int) round((float) $item['duracao'])
                                    : null,
                                'dados_raw'       => $item,
                            ]
                        );

                        $existia ? $log['atualizados']++ : $log['novos']++;

                    } catch (\Throwable $e) {
                        $log['erros']++;
                        Log::warning('CODI: erro ao salvar evento', [
                            'erro'   => $e->getMessage(),
                            'evento' => $item['codigoEvento'] ?? null,
                        ]);
                    }
                }

                Log::info("CODI: {$dataStr} — " . count($eventos) . " eventos processados");
                $dia->modify('+1 day');
            }

            $this->registrarLog('eventos', 'sucesso', $log, microtime(true) - $inicio);

        } catch (\Throwable $e) {
            $this->registrarLog('eventos', 'erro', $log, microtime(true) - $inicio, $e->getMessage());
        }

        return $log;
    }

    /**
     * Mapeia o campo `estado` do CODI para o enum da tabela codi_eventos.
     */
    private function mapearTipoEvento(string $estado): string
    {
        return match ($estado) {
            'PRODUCAO'        => 'PRODUCAO',
            'SETUP'           => 'SETUP',
            'PARADA'          => 'PARADA',
            'REJEITO', 'RUIM' => 'REJEITO',
            default           => 'PARADA',
        };
    }

    private function registrarLog(
        string $tipo,
        string $status,
        array $log,
        float $duracao,
        ?string $erro = null
    ): void {
        CodiSincronizacaoLog::create([
            'tipo'                  => $tipo,
            'status'                => $status,
            'registros_processados' => $log['novos'] + $log['atualizados'],
            'registros_novos'       => $log['novos'],
            'registros_atualizados' => $log['atualizados'],
            'erro_mensagem'         => $erro,
            'duracao_segundos'      => (int) $duracao,
        ]);
    }
}
