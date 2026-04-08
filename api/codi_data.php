<?php
/**
 * API de Dados CODI para o Frontend
 * 
 * Endpoints:
 * GET /api/codi_data.php?endpoint=recursos
 * GET /api/codi_data.php?endpoint=calendario&recurso=1&limit=100
 * GET /api/codi_data.php?endpoint=performance&recurso=1
 * GET /api/codi_data.php?endpoint=timeline
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../src/Database/Connection.php';

use App\Database\Connection;

$pdo = Connection::get();
$endpoint = $_GET['endpoint'] ?? 'status';

try {
    
    if ($endpoint === 'recursos') {
        // Listar todos os recursos
        $result = $pdo->query(
            'SELECT cod_id, cod_codigo_codi, cod_nome_recurso, cod_ativo 
             FROM codi_recursos 
             ORDER BY cod_nome_recurso'
        );
        
        $dados = [];
        foreach ($result->fetchAll(\PDO::FETCH_ASSOC) as $r) {
            $dados[] = [
                'id' => (int)$r['cod_id'],
                'codigo' => (int)$r['cod_codigo_codi'],
                'nome' => $r['cod_nome_recurso'],
                'ativo' => (bool)$r['cod_ativo']
            ];
        }
        
        echo json_encode([
            'status' => 'sucesso',
            'endpoint' => 'recursos',
            'total' => count($dados),
            'dados' => $dados
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        
    } elseif ($endpoint === 'calendario') {
        // Calendário por recurso
        $recurso_id = (int)($_GET['recurso'] ?? 0);
        $limit = min((int)($_GET['limit'] ?? 100), 1000);
        $offset = (int)($_GET['offset'] ?? 0);
        
        $where = '';
        $params = [];
        
        if ($recurso_id > 0) {
            $where = 'WHERE cal_recurso_codi_id = ?';
            $params[] = $recurso_id;
        }
        
        $stmt = $pdo->prepare(
            "SELECT cal_codigo_codi, cal_data, cal_hora_inicio, cal_hora_fim, 
                    cal_recurso_codi_id, cal_grandeza_codi, cal_turno_codi
             FROM codi_calendario 
             $where
             ORDER BY cal_data DESC, cal_hora_inicio
             LIMIT $limit OFFSET $offset"
        );
        $stmt->execute($params);
        
        $dados = [];
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $cal) {
            $dados[] = [
                'codigo' => (int)$cal['cal_codigo_codi'],
                'data' => $cal['cal_data'],
                'hora_inicio' => $cal['cal_hora_inicio'],
                'hora_fim' => $cal['cal_hora_fim'],
                'recurso_id' => (int)$cal['cal_recurso_codi_id'],
                'grandeza_id' => $cal['cal_grandeza_codi'] ? (int)$cal['cal_grandeza_codi'] : null,
                'turno_id' => $cal['cal_turno_codi'] ? (int)$cal['cal_turno_codi'] : null
            ];
        }
        
        // Total count
        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM codi_calendario $where");
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();
        
        echo json_encode([
            'status' => 'sucesso',
            'endpoint' => 'calendario',
            'recurso_id' => $recurso_id ?: null,
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset,
            'dados' => $dados
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        
    } elseif ($endpoint === 'performance') {
        // Performance data
        $recurso_id = (int)($_GET['recurso'] ?? 0);
        $limit = min((int)($_GET['limit'] ?? 100), 500);
        $offset = (int)($_GET['offset'] ?? 0);
        
        $where = '';
        $params = [];
        
        if ($recurso_id > 0) {
            $where = 'WHERE perf_recurso_codi_id = ?';
            $params[] = $recurso_id;
        }
        
        $stmt = $pdo->prepare(
            "SELECT perf_codigo_codi, perf_recurso_codi_id, perf_item_codi, perf_ordem_producao
             FROM codi_performance 
             $where
             ORDER BY perf_codigo_codi DESC
             LIMIT $limit OFFSET $offset"
        );
        $stmt->execute($params);
        
        $dados = [];
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $perf) {
            $dados[] = [
                'codigo' => (int)$perf['perf_codigo_codi'],
                'recurso_id' => $perf['perf_recurso_codi_id'] ? (int)$perf['perf_recurso_codi_id'] : null,
                'item_id' => $perf['perf_item_codi'] ? (int)$perf['perf_item_codi'] : null,
                'ordem_producao' => $perf['perf_ordem_producao']
            ];
        }
        
        // Total count
        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM codi_performance $where");
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();
        
        echo json_encode([
            'status' => 'sucesso',
            'endpoint' => 'performance',
            'recurso_id' => $recurso_id ?: null,
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset,
            'dados' => $dados
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        
    } elseif ($endpoint === 'timeline') {
        // Timeline: Calendário + Performance agregados por data/recurso
        $data_inicio = $_GET['data_inicio'] ?? date('Y-m-01');
        $data_fim = $_GET['data_fim'] ?? date('Y-m-d');
        
        $stmt = $pdo->prepare(
            'SELECT 
                c.cal_data as data,
                r.cod_nome_recurso as recurso_nome,
                c.cal_hora_inicio as hora_inicio,
                c.cal_hora_fim as hora_fim,
                COUNT(p.perf_id) as execucoes
             FROM codi_calendario c
             LEFT JOIN codi_recursos r ON c.cal_recurso_codi_id = r.cod_codigo_codi
             LEFT JOIN codi_performance p ON p.perf_recurso_codi_id = c.cal_recurso_codi_id
             WHERE c.cal_data BETWEEN ? AND ?
             GROUP BY c.cal_id, r.cod_nome_recurso
             ORDER BY c.cal_data DESC, c.cal_hora_inicio DESC
             LIMIT 200'
        );
        
        $stmt->execute([$data_inicio, $data_fim]);
        
        $dados = [];
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $dados[] = [
                'data' => $row['data'],
                'recurso' => $row['recurso_nome'],
                'periodo' => $row['hora_inicio'] . ' - ' . $row['hora_fim'],
                'execucoes' => (int)$row['execucoes']
            ];
        }
        
        echo json_encode([
            'status' => 'sucesso',
            'endpoint' => 'timeline',
            'data_inicio' => $data_inicio,
            'data_fim' => $data_fim,
            'total' => count($dados),
            'dados' => $dados
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        
    } else {
        // Status geral e help
        $recursos = (int)$pdo->query('SELECT COUNT(*) FROM codi_recursos')->fetchColumn();
        $calendario = (int)$pdo->query('SELECT COUNT(*) FROM codi_calendario')->fetchColumn();
        $performance = (int)$pdo->query('SELECT COUNT(*) FROM codi_performance')->fetchColumn();
        
        echo json_encode([
            'status' => 'sucesso',
            'banco' => 'controlepcp_sandbox',
            'totais' => [
                'recursos' => $recursos,
                'calendario' => $calendario,
                'performance' => $performance
            ],
            'endpoints_disponiveis' => [
                '/api/codi_data.php?endpoint=recursos' => 'Listar todos os recursos',
                '/api/codi_data.php?endpoint=calendario' => 'Listar calendário (limite 100)',
                '/api/codi_data.php?endpoint=calendario&recurso=1&limit=50' => 'Calendário de um recurso',
                '/api/codi_data.php?endpoint=performance' => 'Listar performance data',
                '/api/codi_data.php?endpoint=timeline' => 'Timeline agregado'
            ]
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
    
} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'erro',
        'mensagem' => $e->getMessage()
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}
?>
