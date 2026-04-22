<?php
/**
 * CODI Synchronization Service
 * 
 * Responsabilidade: Orquestrar sincronização de dados CODI com o banco local
 * 
 * FASE 3 - Integração CODI
 * Criado: 2026-04-06
 */

namespace Codi;

require_once __DIR__ . '/../bootstrap.php';

class CodiSyncService
{
    private CodiClient $client;
    private \PDO $pdo;
    private array $config;
    private array $logs = [];
    private bool $enableLogging = true;
    
    /**
     * Construtor
     * 
     * @param CodiClient $client  Cliente CODI configurado
     * @param \PDO $pdo          Conexão com banco de dados
     * @param array $config      Configuração de sincronização
     */
    public function __construct(CodiClient $client, \PDO $pdo, array $config = [])
    {
        $this->client = $client;
        $this->pdo = $pdo;
        
        // Configuração padrão
        $this->config = array_merge([
            'batchSize' => 100,
            'archiveAfterDays' => 90,
            'validateData' => true,
            'deduplicateEvents' => true,
        ], $config);
        
        $this->log("Sync Service initialized", 'INFO');
    }
    
    /**
     * Sincronizar Tudo
     * 
     * Executa sincronização completa:
     * 1. Atualiza configuração CODI
     * 2. Busca eventos
     * 3. Busca performance
     * 4. Persiste no BD
     * 5. Registra log
     * 
     * @param array $options     Opções adicionais
     * 
     * @return array             Resultado da sincronização
     */
    public function syncAll(array $options = []): array
    {
        $startTime = microtime(true);
        $results = [
            'success' => false,
            'timestamp' => date('Y-m-d H:i:s'),
            'events_synced' => 0,
            'performance_synced' => 0,
            'errors' => [],
            'duration_seconds' => 0,
        ];
        
        try {
            $this->log("Starting full sync", 'INFO');
            
            // 1. Inicializar configuração
            $this->syncConfiguration();
            
            // 2. Sincronizar eventos
            $eventCount = $this->syncEvents($options);
            $results['events_synced'] = $eventCount;
            
            // 3. Sincronizar performance
            $perfCount = $this->syncPerformance($options);
            $results['performance_synced'] = $perfCount;
            
            // 4. Registrar resultado
            $results['duration_seconds'] = round(microtime(true) - $startTime, 2);
            $results['success'] = true;
            
            $this->persistSyncLog($results);
            $this->log("Sync completed successfully", 'SUCCESS');
            
        } catch (\Exception $e) {
            $results['errors'][] = $e->getMessage();
            $results['duration_seconds'] = round(microtime(true) - $startTime, 2);
            
            $this->log("Sync failed: {$e->getMessage()}", 'ERROR');
            $this->persistSyncLog($results);
        }
        
        return $results;
    }
    
    /**
     * Sincronizar Eventos de Produção
     * 
     * @param array $options     Opções (dataInicio, dataFim, limit)
     * 
     * @return int              Quantidade de eventos sincronizados
     */
    public function syncEvents(array $options = []): int
    {
        $this->log("Syncing events", 'INFO');
        
        $count = 0;
        $errors = [];
        
        try {
            // Parâmetros padrão
            $params = array_merge([
                'dataInicio' => date('Y-m-d', strtotime('-1 day')),
                'dataFim' => date('Y-m-d'),
                'limit' => 1000,
                'offset' => 0,
            ], $options);
            
            // Buscar eventos do CODI
            $eventos = $this->client->getEventos($params);
            
            if (!$eventos || !isset($eventos['data'])) {
                $this->log("No events returned from CODI", 'WARNING');
                return 0;
            }
            
            $eventosData = $eventos['data'];
            $this->log("Received " . count($eventosData) . " events from CODI", 'INFO');
            
            // Processar em batches
            $batch = [];
            foreach ($eventosData as $evento) {
                $batch[] = $this->transformEvento($evento);
                
                if (count($batch) >= $this->config['batchSize']) {
                    $count += $this->persistEventos($batch);
                    $batch = [];
                }
            }
            
            // Persistir batch final
            if (!empty($batch)) {
                $count += $this->persistEventos($batch);
            }
            
            $this->log("Events synced: $count", 'SUCCESS');
            
        } catch (\Exception $e) {
            $this->log("Error syncing events: {$e->getMessage()}", 'ERROR');
        }
        
        return $count;
    }
    
    /**
     * Sincronizar Performance
     * 
     * @param array $options
     * 
     * @return int
     */
    public function syncPerformance(array $options = []): int
    {
        $this->log("Syncing performance", 'INFO');
        
        $inserted = 0;
        $startPage = max(0, (int)($options['pageNumber'] ?? 0));
        $pageSize = max(1, (int)($options['pageSize'] ?? 200));
        $maxPages = max(1, (int)($options['maxPages'] ?? 100));
        $currentPage = $startPage;
        $processedPages = 0;

        try {
            while (true) {
                $filters = $options;
                unset($filters['maxPages']);
                $filters['pageNumber'] = $currentPage;
                $filters['pageSize'] = $pageSize;

                $performance = $this->client->getPerformance($filters);

                if (!$performance || !isset($performance['data']) || !is_array($performance['data'])) {
                    if ($processedPages === 0) {
                        $this->log("No performance data returned from CODI", 'WARNING');
                    }
                    break;
                }

                $pageItems = $performance['data'];
                if (empty($pageItems)) {
                    $this->log("Performance page {$currentPage} returned no records", 'INFO');
                    break;
                }

                $batch = [];
                foreach ($pageItems as $item) {
                    if (!is_array($item)) {
                        continue;
                    }

                    $normalized = $this->transformPerformance($item);
                    if ($normalized !== null) {
                        $batch[] = $normalized;
                    }
                }

                if (!empty($batch)) {
                    $inserted += $this->persistPerformance($batch);
                }

                $processedPages++;
                $totalPages = isset($performance['totalPages']) ? (int)$performance['totalPages'] : null;
                $hasMore = $totalPages !== null
                    ? ($currentPage + 1 < $totalPages)
                    : (count($pageItems) >= $pageSize);

                if (!$hasMore || $processedPages >= $maxPages) {
                    break;
                }

                $currentPage++;
            }
            
            $this->log("Performance synced: $inserted records", 'SUCCESS');
            
            return $inserted;
            
        } catch (\Exception $e) {
            $this->log("Error syncing performance: {$e->getMessage()}", 'ERROR');
            return 0;
        }
    }
    
    /**
     * Sincronizar Configuração CODI
     * 
     * Atualiza credenciais e status de conexão
     */
    private function syncConfiguration(): void
    {
        try {
            $config = $this->client->getConfig();
            
            $sql = "INSERT INTO cdi_configuracao (
                cdi_servidor_url,
                cdi_usuario,
                cdi_codename_empresa,
                cdi_ativo,
                cdi_ultimo_sync
            ) VALUES (?, ?, ?, 1, NOW())
            ON DUPLICATE KEY UPDATE
                cdi_ativo = 1,
                cdi_ultimo_sync = NOW()";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                $config['baseUrl'],
                $config['username'],
                $config['companyCode'],
            ]);
            
            $this->log("Configuration updated", 'INFO');
            
        } catch (\Exception $e) {
            $this->log("Error syncing configuration: {$e->getMessage()}", 'WARNING');
        }
    }
    
    /**
     * Transformar Evento CODI para formato BD
     * 
     * @param array $evento       Evento do CODI
     * 
     * @return array
     */
    private function transformEvento(array $evento): array
    {
        return [
            'cdi_evento_id_externo' => $evento['id'] ?? null,
            'cdi_data_evento' => substr($evento['timestamp'] ?? '', 0, 10),
            'cdi_hora_evento' => substr($evento['timestamp'] ?? '', 11, 8),
            'cdi_timestamp_evento' => $evento['timestamp'] ?? date('Y-m-d H:i:s'),
            'cdi_quantidade_evento' => (float)($evento['quantity'] ?? 0),
            'cdi_sku_codi' => $evento['skuCodi'] ?? null,
            'cdi_recurso_id' => $evento['recursoId'] ?? null,
            'cdi_operacao_id' => $evento['operacaoId'] ?? null,
            'cdi_tipo_evento' => $evento['tipoEvento'] ?? 'PRODUCAO',
            'cdi_status_evento' => $evento['status'] ?? 'REGISTRADO',
            'cdi_data_sincronizacao' => date('Y-m-d H:i:s'),
        ];
    }
    
    /**
     * Transformar Performance CODI para formato BD
     * 
     * @param array $performance
     * 
     * @return array
     */
    private function transformPerformance(array $performance): ?array
    {
        $codigoPerformance = $this->extractPerformanceValue($performance, [
            'codigoPerformance',
            'codigo_performance',
            'codigo',
            'id',
        ]);

        if ($codigoPerformance === null || $codigoPerformance === '') {
            return null;
        }

        $timestamp = $this->extractPerformanceValue($performance, [
            'ultimaAlteracao',
            'timestamp',
            'dataColeta',
            'data_coleta',
            'data',
        ]) ?? date('Y-m-d H:i:s');

        $recursoId = $this->extractPerformanceValue($performance, [
            'recursoItem.codigoRecurso',
            'recursoItem.codigo',
            'grandeza.recurso.codigoRecurso',
            'grandeza.recurso.codigo',
            'recurso.codigoRecurso',
            'recurso.codigo',
            'codigoRecurso',
            'recurso_id',
            'recursoId',
        ]);

        $itemId = $this->extractPerformanceValue($performance, [
            'item.codigoItem',
            'item.codigo',
            'item.id',
            'codigoItem',
            'item_id',
            'itemId',
        ]);

        $ordemProducao = $this->extractPerformanceValue($performance, [
            'ordemProducao',
            'ordem_producao',
            'ordem',
            'op',
        ]);
        
        return [
            'perf_codigo_codi' => (int)$codigoPerformance,
            'perf_recurso_codi_id' => $recursoId !== null && $recursoId !== '' ? (int)$recursoId : null,
            'perf_item_codi' => $itemId !== null && $itemId !== '' ? (int)$itemId : null,
            'perf_ordem_producao' => $ordemProducao !== null ? (string)$ordemProducao : null,
            'perf_dados_json' => json_encode($performance, JSON_UNESCAPED_UNICODE),
            'perf_sincronizado_em' => date('Y-m-d H:i:s'),
        ];
    }
    
    /**
     * Persistir Eventos no Banco
     * 
     * @param array $eventos     Array de eventos transformados
     * 
     * @return int              Quantidade inserida
     */
    private function persistEventos(array $eventos): int
    {
        if (empty($eventos)) {
            return 0;
        }
        
        try {
            // Se deduplicar, remover duplicatas por evento_id_externo
            if ($this->config['deduplicateEvents']) {
                $eventos = $this->deduplicateEventos($eventos);
            }
            
            $sql = "INSERT INTO cdi_eventos (
                cdi_evento_id_externo,
                cdi_data_evento,
                cdi_hora_evento,
                cdi_timestamp_evento,
                cdi_quantidade_evento,
                cdi_sku_codi,
                cdi_recurso_id,
                cdi_operacao_id,
                cdi_tipo_evento,
                cdi_status_evento,
                cdi_data_sincronizacao
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                cdi_timestamp_evento = VALUES(cdi_timestamp_evento),
                cdi_quantidade_evento = VALUES(cdi_quantidade_evento),
                cdi_status_evento = VALUES(cdi_status_evento),
                cdi_data_sincronizacao = VALUES(cdi_data_sincronizacao)";
            
            $stmt = $this->pdo->prepare($sql);
            $inserted = 0;
            
            foreach ($eventos as $evento) {
                $result = $stmt->execute([
                    $evento['cdi_evento_id_externo'],
                    $evento['cdi_data_evento'],
                    $evento['cdi_hora_evento'],
                    $evento['cdi_timestamp_evento'],
                    $evento['cdi_quantidade_evento'],
                    $evento['cdi_sku_codi'],
                    $evento['cdi_recurso_id'],
                    $evento['cdi_operacao_id'],
                    $evento['cdi_tipo_evento'],
                    $evento['cdi_status_evento'],
                    $evento['cdi_data_sincronizacao'],
                ]);
                
                if ($result) {
                    $inserted++;
                }
            }
            
            return $inserted;
            
        } catch (\Exception $e) {
            $this->log("Error persisting events: {$e->getMessage()}", 'ERROR');
            return 0;
        }
    }
    
    /**
     * Persistir Performance no Banco
     * 
     * @param array $perfData
     * 
     * @return int
     */
    private function persistPerformance(array $perfData): int
    {
        if (empty($perfData)) {
            return 0;
        }
        
        try {
            $sql = "INSERT INTO codi_performance (
                perf_codigo_codi,
                perf_recurso_codi_id,
                perf_item_codi,
                perf_ordem_producao,
                perf_dados_json,
                perf_sincronizado_em
            ) VALUES (?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                perf_recurso_codi_id = VALUES(perf_recurso_codi_id),
                perf_item_codi = VALUES(perf_item_codi),
                perf_ordem_producao = VALUES(perf_ordem_producao),
                perf_dados_json = VALUES(perf_dados_json),
                perf_sincronizado_em = VALUES(perf_sincronizado_em)";
            
            $stmt = $this->pdo->prepare($sql);
            $inserted = 0;

            $this->pdo->beginTransaction();

            foreach ($perfData as $perf) {
                if (!is_array($perf) || !isset($perf['perf_codigo_codi']) || $perf['perf_codigo_codi'] === null || $perf['perf_codigo_codi'] === '') {
                    continue;
                }

                $result = $stmt->execute([
                    $perf['perf_codigo_codi'],
                    $perf['perf_recurso_codi_id'],
                    $perf['perf_item_codi'],
                    $perf['perf_ordem_producao'],
                    $perf['perf_dados_json'],
                    $perf['perf_sincronizado_em'],
                ]);

                if ($result) {
                    $inserted++;
                }
            }

            $this->pdo->commit();
            return $inserted;
            
        } catch (\Exception $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            $this->log("Error persisting performance: {$e->getMessage()}", 'ERROR');
            return 0;
        }
    }

    /**
     * Extrair valor de um array, suportando notação com ponto
     *
     * @param array $source
     * @param array $paths
     * @return mixed|null
     */
    private function extractPerformanceValue(array $source, array $paths)
    {
        foreach ($paths as $path) {
            $value = $this->arrayGetByPath($source, $path);
            if ($value !== null && $value !== '') {
                return $value;
            }
        }

        return null;
    }

    /**
     * Buscar valor por caminho com notação "a.b.c"
     *
     * @param array $source
     * @param string $path
     * @return mixed|null
     */
    private function arrayGetByPath(array $source, string $path)
    {
        $segments = explode('.', $path);
        $current = $source;

        foreach ($segments as $segment) {
            if (!is_array($current) || !array_key_exists($segment, $current)) {
                return null;
            }

            $current = $current[$segment];
        }

        return $current;
    }
    
    /**
     * Remover Eventos Duplicados
     * 
     * @param array $eventos
     * 
     * @return array
     */
    private function deduplicateEventos(array $eventos): array
    {
        $seen = [];
        $unique = [];
        
        foreach ($eventos as $evento) {
            $key = $evento['cdi_evento_id_externo'] ?? $evento['cdi_timestamp_evento'];
            
            if (!isset($seen[$key])) {
                $seen[$key] = true;
                $unique[] = $evento;
            }
        }
        
        if (count($unique) < count($eventos)) {
            $removed = count($eventos) - count($unique);
            $this->log("Removed $removed duplicate events", 'INFO');
        }
        
        return $unique;
    }
    
    /**
     * Persistir Log de Sincronização
     * 
     * @param array $result
     */
    private function persistSyncLog(array $result): void
    {
        try {
            $sql = "INSERT INTO cdi_sincronizacao_log (
                cdi_data_sincronizacao,
                cdi_hora_sincronizacao,
                cdi_status_sincronizacao,
                cdi_quantidade_eventos,
                cdi_quantidade_performance,
                cdi_duracao_segundos,
                cdi_mensagem_erro
            ) VALUES (?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                substr($result['timestamp'], 0, 10),
                substr($result['timestamp'], 11, 8),
                $result['success'] ? 'SUCESSO' : 'FALHA',
                $result['events_synced'],
                $result['performance_synced'],
                $result['duration_seconds'],
                implode('; ', $result['errors']) ?: null,
            ]);
            
        } catch (\Exception $e) {
            $this->log("Error persisting sync log: {$e->getMessage()}", 'ERROR');
        }
    }
    
    /**
     * Obter Status Geral
     * 
     * @return array
     */
    public function getStatus(): array
    {
        try {
            $sql = "SELECT 
                COUNT(*) as total_events,
                MAX(cdi_timestamp_evento) as ultimo_evento
            FROM cdi_eventos";
            
            $eventos = $this->pdo->query($sql)->fetch(\PDO::FETCH_ASSOC);
            
            $sql = "SELECT 
                COUNT(*) as total_syncs,
                MAX(cdi_data_sincronizacao) as ultimo_sync
            FROM cdi_sincronizacao_log
            WHERE cdi_status_sincronizacao = 'SUCESSO'";
            
            $syncs = $this->pdo->query($sql)->fetch(\PDO::FETCH_ASSOC);
            
            return [
                'eventos' => $eventos,
                'sincronizacoes' => $syncs,
                'status' => 'OK',
            ];
            
        } catch (\Exception $e) {
            return ['status' => 'ERROR', 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Limpar Dados Antigos
     * 
     * @return int  Quantidade de registros deletados
     */
    public function archiveOldData(): int
    {
        $days = $this->config['archiveAfterDays'];
        $deleted = 0;
        
        try {
            $sql = "DELETE FROM cdi_eventos 
                WHERE cdi_data_evento < DATE_SUB(NOW(), INTERVAL ? DAY)";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$days]);
            $deleted = $stmt->rowCount();
            
            $this->log("Archived $deleted old events", 'INFO');
            
        } catch (\Exception $e) {
            $this->log("Error archiving data: {$e->getMessage()}", 'ERROR');
        }
        
        return $deleted;
    }
    
    /**
     * Logging
     * 
     * @param string $message
     * @param string $level
     */
    private function log(string $message, string $level = 'INFO'): void
    {
        if (!$this->enableLogging) {
            return;
        }
        
        $this->logs[] = [
            'timestamp' => date('Y-m-d H:i:s'),
            'level' => $level,
            'message' => $message,
        ];
    }
    
    /**
     * Obter Logs
     * 
     * @param string|null $level
     * 
     * @return array
     */
    public function getLogs(?string $level = null): array
    {
        if ($level === null) {
            return $this->logs;
        }
        
        return array_filter($this->logs, fn($log) => $log['level'] === $level);
    }
    
    /**
     * Habilitar/Desabilitar Logging
     * 
     * @param bool $enabled
     */
    public function setLogging(bool $enabled): self
    {
        $this->enableLogging = $enabled;
        return $this;
    }
}
?>
