<?php
/**
 * API para consultar dados CODI sincronizados
 * 
 * Endpoints:
 * GET /api/codi_status.php - Status da sincronização
 * GET /api/codi_status.php?recurso=1 - Detalhes de um recurso
 * GET /api/codi_status.php?calendario&limit=50 - Calendário
 * GET /api/codi_status.php?performance&limit=50 - Performance
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../src/Database/Connection.php';

use App\Database\Connection;

$pdo = Connection::get();

try {
    
    if (isset($_GET['recursos'])) {
        // Listar todos os recursos
        $result = $pdo->query('SELECT cod_id, cod_codigo_codi, cod_nome_recurso FROM codi_recursos ORDER BY cod_nome_recurso');
        $recursos = $result->fetchAll(\PDO::FETCH_ASSOC);
        
        echo json_encode([
            'status' => 'sucesso',
            'tipo' => 'recursos',
            'total' => count($recursos),
            'dados' => $recursos
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        
    } elseif (isset($_GET['calendario'])) {
        // Listar calendário
        $limit = (int)($_GET['limit'] ?? 100);
        $result = $pdo->query("SELECT cal_codigo_codi, cal_data, cal_hora_inicio, cal_hora_fim, cal_recurso_codi_id FROM codi_calendario LIMIT $limit");
        $dados = $result->fetchAll(\PDO::FETCH_ASSOC);
        
        echo json_encode([
            'status' => 'sucesso',
            'tipo' => 'calendario',
            'total' => count($dados),
            'limit' => $limit,
            'dados' => $dados
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        
    } elseif (isset($_GET['performance'])) {
        // Listar performance
        $limit = (int)($_GET['limit'] ?? 100);
        $result = $pdo->query("SELECT perf_codigo_codi, perf_recurso_codi_id, perf_item_codi, perf_ordem_producao FROM codi_performance LIMIT $limit");
        $dados = $result->fetchAll(\PDO::FETCH_ASSOC);
        
        echo json_encode([
            'status' => 'sucesso',
            'tipo' => 'performance',
            'total' => count($dados),
            'limit' => $limit,
            'dados' => $dados
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        
    } else {
        // Status geral
        $recursos = $pdo->query('SELECT COUNT(*) as total FROM codi_recursos')->fetch()['total'];
        $calendario = $pdo->query('SELECT COUNT(*) as total FROM codi_calendario')->fetch()['total'];
        $performance = $pdo->query('SELECT COUNT(*) as total FROM codi_performance')->fetch()['total'];
        $sincronizacao = $pdo->query('SELECT COUNT(*) as total FROM codi_sincronizacao')->fetch()['total'];
        
        echo json_encode([
            'status' => 'sucesso',
            'tipo' => 'status',
            'banco' => 'controlepcp_sandbox',
            'tabelas' => [
                'codi_recursos' => (int)$recursos,
                'codi_calendario' => (int)$calendario,
                'codi_performance' => (int)$performance,
                'codi_sincronizacao' => (int)$sincronizacao
            ],
            'total_registros' => (int)($recursos + $calendario + $performance)
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
    
} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'erro',
        'message' => $e->getMessage()
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}
