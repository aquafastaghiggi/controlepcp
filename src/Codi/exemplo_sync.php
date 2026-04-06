<?php
/**
 * Exemplo de Uso - CODI Sync Service
 * 
 * Demonstra como usar o CodiSyncService para sincronizar dados CODI com o BD
 * 
 * FASE 3 - Integração CODI
 */

namespace Codi;

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/CodiClient.php';
require_once __DIR__ . '/CodiSyncService.php';

echo "=== FASE 3: CODI Sync Service - Exemplos ===\n\n";

try {
    // ========== EXEMPLO 1: Inicializar Cliente e Serviço ==========
    echo "EXEMPLO 1: Inicializar Cliente e Serviço\n";
    echo str_repeat("-", 50) . "\n";
    
    // Conexão com BD (via bootstrap.php)
    global $pdo;
    if (!isset($pdo)) {
        throw new \Exception('PDO não inicializado. Verifique bootstrap.php');
    }
    
    // Cliente CODI
    $client = new CodiClient(
        $_ENV['CODI_BASE_URL'] ?? 'http://192.168.8.123:8081',
        $_ENV['CODI_USERNAME'] ?? 'admin',
        $_ENV['CODI_PASSWORD'] ?? 'senha123',
        $_ENV['CODI_COMPANY_CODE'] ?? 'matriz'
    );
    
    // Serviço de Sincronização
    $syncService = new CodiSyncService($client, $pdo, [
        'batchSize' => 100,
        'archiveAfterDays' => 90,
        'deduplicateEvents' => true,
    ]);
    
    echo "✓ Cliente CODI inicializado\n";
    echo "✓ Sync Service criado\n\n";
    
    // ========== EXEMPLO 2: Sincronização Simples ==========
    echo "EXEMPLO 2: Sincronização Completa (Simples)\n";
    echo str_repeat("-", 50) . "\n";
    
    $result = $syncService->syncAll();
    
    echo "Status: " . ($result['success'] ? "✓ SUCESSO" : "✗ FALHA") . "\n";
    echo "Eventos Sincronizados: " . $result['events_synced'] . "\n";
    echo "Performance Sincronizado: " . $result['performance_synced'] . "\n";
    echo "Duração: " . $result['duration_seconds'] . "s\n";
    
    if (!empty($result['errors'])) {
        echo "Erros:\n";
        foreach ($result['errors'] as $error) {
            echo "- $error\n";
        }
    }
    echo "\n";
    
    // ========== EXEMPLO 3: Sincronizar Apenas Eventos ==========
    echo "EXEMPLO 3: Sincronizar Apenas Eventos\n";
    echo str_repeat("-", 50) . "\n";
    
    $eventCount = $syncService->syncEvents([
        'dataInicio' => date('Y-m-d', strtotime('-7 days')),
        'dataFim' => date('Y-m-d'),
        'limit' => 500,
    ]);
    
    echo "Eventos sincronizados: $eventCount\n\n";
    
    // ========== EXEMPLO 4: Sincronizar Apenas Performance ==========
    echo "EXEMPLO 4: Sincronizar Apenas Performance\n";
    echo str_repeat("-", 50) . "\n";
    
    $perfCount = $syncService->syncPerformance();
    
    echo "Performance registros: $perfCount\n\n";
    
    // ========== EXEMPLO 5: Ver Logs de Sincronização ==========
    echo "EXEMPLO 5: Logs de Sincronização\n";
    echo str_repeat("-", 50) . "\n";
    
    $logs = $syncService->getLogs();
    echo "Total de operações registradas: " . count($logs) . "\n\n";
    
    echo "Últimas 5 operações:\n";
    $lastLogs = array_slice($logs, -5);
    foreach ($lastLogs as $log) {
        $level = $log['level'];
        $icon = match($level) {
            'ERROR' => '✗',
            'WARNING' => '⚠',
            'SUCCESS' => '✓',
            default => '•'
        };
        echo "[$log[timestamp]] $icon [$level] {$log['message']}\n";
    }
    echo "\n";
    
    // ========== EXEMPLO 6: Logs Filtrados ==========
    echo "EXEMPLO 6: Filtrar Logs por Nível\n";
    echo str_repeat("-", 50) . "\n";
    
    $errorLogs = $syncService->getLogs('ERROR');
    $warningLogs = $syncService->getLogs('WARNING');
    $successLogs = $syncService->getLogs('SUCCESS');
    
    echo "Erros: " . count($errorLogs) . "\n";
    echo "Avisos: " . count($warningLogs) . "\n";
    echo "Sucessos: " . count($successLogs) . "\n\n";
    
    if (!empty($errorLogs)) {
        echo "Erros encontrados:\n";
        foreach ($errorLogs as $log) {
            echo "- [{$log['timestamp']}] {$log['message']}\n";
        }
        echo "\n";
    }
    
    // ========== EXEMPLO 7: Status Geral ==========
    echo "EXEMPLO 7: Status Geral do Sistema\n";
    echo str_repeat("-", 50) . "\n";
    
    $status = $syncService->getStatus();
    
    if ($status['status'] === 'OK') {
        echo "Status do BD: ✓ OK\n";
        echo "Total de eventos: " . $status['eventos']['total_events'] . "\n";
        echo "Último evento: " . ($status['eventos']['ultimo_evento'] ?? 'N/A') . "\n";
        echo "Total de sincronizações: " . $status['sincronizacoes']['total_syncs'] . "\n";
        echo "Última sincronização: " . ($status['sincronizacoes']['ultimo_sync'] ?? 'N/A') . "\n";
    } else {
        echo "Status: ✗ ERRO\n";
        echo "Erro: " . $status['error'] . "\n";
    }
    echo "\n";
    
    // ========== EXEMPLO 8: Limpeza de Dados Antigos ==========
    echo "EXEMPLO 8: Limpar Dados com Mais de 90 Dias\n";
    echo str_repeat("-", 50) . "\n";
    
    $archived = $syncService->archiveOldData();
    echo "Registros removidos: $archived\n\n";
    
    // ========== EXEMPLO 9: Sincronização com Validação ==========
    echo "EXEMPLO 9: Sincronização com Opções Customizadas\n";
    echo str_repeat("-", 50) . "\n";
    
    $dynamicSyncService = new CodiSyncService($client, $pdo, [
        'batchSize' => 50,              // Processar em lotes de 50
        'archiveAfterDays' => 120,      // Arquivar após 120 dias
        'validateData' => true,         // Validar dados
        'deduplicateEvents' => true,    // Deduplica eventos
    ]);
    
    $customResult = $dynamicSyncService->syncAll([
        'limit' => 200,  // Buscar apenas 200 eventos
    ]);
    
    echo "Resultado da sincronização customizada:\n";
    echo "- Sucesso: " . ($customResult['success'] ? "Sim" : "Não") . "\n";
    echo "- Eventos: " . $customResult['events_synced'] . "\n";
    echo "- Tempo: " . $customResult['duration_seconds'] . "s\n\n";
    
    // ========== EXEMPLO 10: Controle de Logging ==========
    echo "EXEMPLO 10: Controle de Logging\n";
    echo str_repeat("-", 50) . "\n";
    
    // Desabilitar logging para operações silenciosas
    $silentService = new CodiSyncService($client, $pdo);
    $silentService->setLogging(false);
    
    echo "✓ Logging desabilitado para operações silenciosas\n";
    echo "✓ Use setLogging(true) para habilitar novamente\n\n";
    
    // ========== EXEMPLO 11: Fluxo Completo Realista ==========
    echo "EXEMPLO 11: Fluxo Completo e Realista\n";
    echo str_repeat("-", 50) . "\n";
    
    // Criar serviço para produção
    $prodService = new CodiSyncService($client, $pdo, [
        'batchSize' => 500,
        'archiveAfterDays' => 90,
    ]);
    
    echo "Iniciando sincronização em produção...\n";
    
    // Sincronizar
    $syncResult = $prodService->syncAll();
    
    // Verificar resultado
    if ($syncResult['success']) {
        echo "✓ Sincronização bem-sucedida!\n";
        echo "  - Eventos: " . $syncResult['events_synced'] . "\n";
        echo "  - Performance: " . $syncResult['performance_synced'] . "\n";
        echo "  - Tempo: " . $syncResult['duration_seconds'] . "s\n";
        
        // Verificar status
        $currentStatus = $prodService->getStatus();
        echo "  - Total acumulado: " . $currentStatus['eventos']['total_events'] . " eventos\n";
    } else {
        echo "✗ Sincronização falhou!\n";
        foreach ($syncResult['errors'] as $error) {
            echo "  - Erro: $error\n";
        }
    }
    echo "\n";
    
    // ========== RESUMO ==========
    echo "=== RESUMO DOS EXEMPLOS ===\n";
    echo "✓ Todos os 11 exemplos completados com sucesso!\n";
    echo "\nRecursos do CodiSyncService:\n";
    echo "- syncAll(): Sincronização completa\n";
    echo "- syncEvents(): Apenas eventos\n";
    echo "- syncPerformance(): Apenas performance\n";
    echo "- getStatus(): Status do sistema\n";
    echo "- archiveOldData(): Limpar dados antigos\n";
    echo "- getLogs(): Obter logs\n";
    echo "- setLogging(): Controlar logging\n";
    echo "\nPróxima Fase: FASE 4 - EficienciaCalculator.php\n";
    
} catch (\Exception $e) {
    echo "✗ ERRO: " . $e->getMessage() . "\n";
    echo "Stack trace:\n";
    echo $e->getTraceAsString() . "\n";
}
?>
