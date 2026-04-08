<?php
/**
 * API para sincronização CODI
 * 
 * Endpoint: /sync_codi_api.php?action=setup|sync
 * 
 * GET setup - Cria tabelas no banco de dados
 * GET sync - Sincroniza dados do CODI
 */

header('Content-Type: application/json; charset=utf-8');

error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once __DIR__ . '/src/Database/Connection.php';
require_once __DIR__ . '/src/Codi/CodiClient.php';

use App\Database\Connection;
use Codi\CodiClient;

$action = $_GET['action'] ?? 'info';

try {
    $pdo = Connection::get();
    
    if ($action === 'setup') {
        // 1️⃣ CriarТabelas
        echo json_encode([
            'status' => 'processando',
            'message' => 'Criando tabelas de sincronização CODI...'
        ]);
        
        // Ler migration SQL
        $sql = file_get_contents(__DIR__ . '/db/migration_codi_sync.sql');
        
        // Dividir statements
        $statements = array_filter(
            array_map('trim', explode(';', $sql)),
            fn($s) => !empty($s) && !str_starts_with($s, '--') && !str_starts_with($s, '/*')
        );
        
        $created = [];
        $errors = [];
        
        foreach ($statements as $statement) {
            $statement = preg_replace('/^--[^\n]*/m', '', $statement);
            $statement = trim($statement);
            
            if (empty($statement)) continue;
            
            preg_match('/CREATE TABLE IF NOT EXISTS (\w+)/i', $statement, $matches);
            $tableName = $matches[1] ?? 'unknown';
            
            try {
                $pdo->exec($statement);
                $created[] = $tableName;
            } catch (\Exception $e) {
                $errors[] = "$tableName: " . $e->getMessage();
            }
        }
        
        echo json_encode([
            'status' => 'sucesso',
            'action' => 'setup',
            'created_tables' => $created,
            'errors' => $errors,
            'total_created' => count($created),
            'timestamp' => date('Y-m-d H:i:s')
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        
    } elseif ($action === 'sync') {
        // 2️⃣ Sincronizar dados do CODI
        
        $codi = new CodiClient(
            'http://192.168.8.246:8080',
            'Aghiggi',
            '@Ag0351@'
        );
        
        $results = [
            'status' => 'sucesso',
            'action' => 'sync',
            'timestamp' => date('Y-m-d H:i:s'),
            'data' => []
        ];
        
        // Recursos
        $recursos = $codi->getRecursos(['pageNumber' => 0, 'pageSize' => 1000]);
        if ($recursos && isset($recursos['data'])) {
            $count = 0;
            foreach ($recursos['data'] as $r) {
                try {
                    $stmt = $pdo->prepare(
                        'INSERT INTO codi_recursos(cod_codigo_codi, cod_nome_recurso, cod_descricao, cod_ativo, cod_dados_json)
                         VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE cod_sincronizado_em = NOW()'
                    );
                    $stmt->execute([
                        $r['codigoRecurso'],
                        $r['nomeRecurso'],
                        $r['descricao'] ?? '',
                        $r['ativo'] ? 1 : 0,
                        json_encode($r, JSON_UNESCAPED_UNICODE)
                    ]);
                    $count++;
                } catch (\Exception $e) {
                    // Skip and continue
                }
            }
            $results['data']['recursos'] = $count;
        }
        
        // Calendário (primeiros 1000)
        $calendar = $codi->getCalendario(['pageNumber' => 0, 'pageSize' => 1000]);
        if ($calendar && isset($calendar['data'])) {
            $count = 0;
            foreach ($calendar['data'] as $c) {
                try {
                    $stmt = $pdo->prepare(
                        'INSERT INTO codi_calendario(cal_codigo_codi, cal_recurso_codi_id, cal_grandeza_codi, cal_turno_codi, cal_data, cal_hora_inicio, cal_hora_fim, cal_dados_json)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE cal_sincronizado_em = NOW()'
                    );
                    
                    // Extrair IDs (ou usar 0 se não existir)
                    $recursoId = $c['grandeza']['recurso']['codigoRecurso'] ?? null;
                    $grandezaId = $c['grandeza']['codigoGrandeza'] ?? null;
                    $turnoId = $c['turno']['codigoTurno'] ?? null;
                    
                    $stmt->execute([
                        $c['codigoCalendarioFabril'],
                        $recursoId,
                        $grandezaId,
                        $turnoId,
                        $c['data'],
                        substr($c['horaInicio'], 0, 8),
                        substr($c['horaFim'], 0, 8),
                        json_encode($c, JSON_UNESCAPED_UNICODE)
                    ]);
                    $count++;
                } catch (\Exception $e) {
                    // Skip
                }
            }
            $results['data']['calendario'] = $count;
        }
        
        // Performance (primeiros 1000)
        $perf = $codi->getPerformance(['pageNumber' => 0, 'pageSize' => 1000]);
        if ($perf && isset($perf['data'])) {
            $count = 0;
            foreach ($perf['data'] as $p) {
                try {
                    $stmt = $pdo->prepare(
                        'INSERT INTO codi_performance(perf_codigo_codi, perf_recurso_codi_id, perf_item_codi, perf_ordem_producao, perf_dados_json)
                         VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE perf_sincronizado_em = NOW()'
                    );
                    
                    $recursoId = $p['grandeza']['recurso']['codigoRecurso'] ?? null;
                    $itemId = $p['item']['codigoItem'] ?? null;
                    $ordem = $p['ordemProducao'] ?? null;
                    
                    $stmt->execute([
                        $p['codigoPerformance'],
                        $recursoId,
                        $itemId,
                        $ordem,
                        json_encode($p, JSON_UNESCAPED_UNICODE)
                    ]);
                    $count++;
                } catch (\Exception $e) {
                    // Skip
                }
            }
            $results['data']['performance'] = $count;
        }
        
        echo json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        
    } else {
        // Info
        echo json_encode([
            'status' => 'sucesso',
            'endpoint' => 'CODI Sync API',
            'actions' => [
                'setup' => 'Cria as tabelas de sincronização',
                'sync' => 'Sincroniza dados do CODI para o banco local'
            ],
            'usage' => [
                '?action=setup' => 'Executar primeira migração',
                '?action=sync' => 'Sincronizar dados'
            ]
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
    
} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'erro',
        'message' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    die(1);
}
