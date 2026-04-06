<?php
/**
 * Teste de Sincronização - CodiSyncService
 * 
 * Interface web para executar e monitorar sincronização CODI
 */

require_once __DIR__ . '/../../bootstrap.php';
require_once __DIR__ . '/CodiClient.php';
require_once __DIR__ . '/CodiSyncService.php';

use Codi\CodiClient;
use Codi\CodiSyncService;

header('Content-Type: application/json');

$action = $_GET['action'] ?? $_POST['action'] ?? null;
$response = ['success' => false, 'message' => 'No action specified'];

try {
    global $pdo;
    if (!isset($pdo)) {
        throw new \Exception('Database not initialized');
    }
    
    // Inicializar cliente
    $client = new CodiClient(
        $_GET['base_url'] ?? $_POST['base_url'] ?? $_ENV['CODI_BASE_URL'] ?? 'http://192.168.8.123:8081',
        $_GET['username'] ?? $_POST['username'] ?? $_ENV['CODI_USERNAME'] ?? 'admin',
        $_GET['password'] ?? $_POST['password'] ?? $_ENV['CODI_PASSWORD'] ?? 'senha123',
        $_GET['company_code'] ?? $_POST['company_code'] ?? $_ENV['CODI_COMPANY_CODE'] ?? 'matriz'
    );
    
    $syncService = new CodiSyncService($client, $pdo);
    
    // ========= AÇÕES ==========
    
    if ($action === 'sync_all') {
        $result = $syncService->syncAll();
        $response = [
            'success' => $result['success'],
            'message' => $result['success'] ? 'Sync completed' : 'Sync failed',
            'data' => $result,
        ];
    }
    
    elseif ($action === 'sync_events') {
        $count = $syncService->syncEvents([
            'dataInicio' => $_GET['data_inicio'] ?? date('Y-m-d', strtotime('-1 day')),
            'dataFim' => $_GET['data_fim'] ?? date('Y-m-d'),
            'limit' => $_GET['limit'] ?? 1000,
        ]);
        
        $response = [
            'success' => true,
            'message' => "Synced $count events",
            'events_synced' => $count,
        ];
    }
    
    elseif ($action === 'sync_performance') {
        $count = $syncService->syncPerformance();
        
        $response = [
            'success' => true,
            'message' => "Synced performance",
            'performance_synced' => $count,
        ];
    }
    
    elseif ($action === 'get_status') {
        $status = $syncService->getStatus();
        
        $response = [
            'success' => $status['status'] === 'OK',
            'message' => $status['status'],
            'data' => $status,
        ];
    }
    
    elseif ($action === 'get_logs') {
        $logs = $syncService->getLogs();
        
        $response = [
            'success' => true,
            'message' => 'Logs retrieved',
            'total' => count($logs),
            'data' => array_slice($logs, -20),  // Últimos 20
        ];
    }
    
    elseif ($action === 'archive') {
        $archived = $syncService->archiveOldData();
        
        $response = [
            'success' => true,
            'message' => "Archived $archived old records",
            'archived' => $archived,
        ];
    }
    
    else {
        throw new \Exception("Unknown action: $action");
    }
    
} catch (\Exception $e) {
    $response = [
        'success' => false,
        'error' => $e->getMessage(),
        'exception' => class_basename($e),
    ];
}

echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
?>
