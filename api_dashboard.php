<?php
// API Backend para Dashboard CODI
// Este arquivo responde todos os requests AJAX do dashboard

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/src/Database/Connection.php';
use App\Database\Connection;

try {
    $action = $_GET['action'] ?? 'error';
    
    if ($action === 'error') {
        http_response_code(400);
        die(json_encode(['erro' => 'Nenhuma ação especificada', 'debug' => $_GET]));
    }

    $pdo = Connection::get();
    
    // Testa se a conexão está funcionando
    if ($action === 'test') {
        echo json_encode([
            'status' => 'ok',
            'message' => 'Conexão com banco funcionando',
            'db' => 'controlepcp_sandbox',
            'tempo' => date('Y-m-d H:i:s')
        ]);
        exit;
    }

    // Estatísticas
    if ($action === 'stats') {
        $recursos = (int)$pdo->query('SELECT COUNT(*) FROM codi_recursos')->fetchColumn();
        $calendario = (int)$pdo->query('SELECT COUNT(*) FROM codi_calendario')->fetchColumn();
        $performance = (int)$pdo->query('SELECT COUNT(*) FROM codi_performance')->fetchColumn();
        $data_count = (int)$pdo->query('SELECT COUNT(DISTINCT cal_data) FROM codi_calendario')->fetchColumn();

        echo json_encode([
            'recursos' => $recursos,
            'calendario' => $calendario,
            'performance' => $performance,
            'data_count' => $data_count
        ]);
        exit;
    }

    // Recursos
    if ($action === 'recursos') {
        $result = $pdo->query('
            SELECT 
                cod_id as id, 
                cod_codigo_codi as codigo_codi, 
                cod_nome_recurso as nome, 
                cod_ativo as ativo
            FROM codi_recursos 
            ORDER BY cod_nome_recurso
        ');
        $dados = $result->fetchAll(\PDO::FETCH_ASSOC);
        echo json_encode($dados);
        exit;
    }

    // Calendário com OP
    if ($action === 'calendario') {
        $limit = (int)($_GET['limit'] ?? 100);
        $recurso = $_GET['recurso'] ?? null;
        $data_inicio = $_GET['data_inicio'] ?? null;
        $data_fim = $_GET['data_fim'] ?? null;

        $where = [];
        $params = [];
        
        if ($recurso) {
            $where[] = 'cal_recurso_codi_id = ?';
            $params[] = $recurso;
        }
        if ($data_inicio) {
            $where[] = 'cal_data >= ?';
            $params[] = $data_inicio;
        }
        if ($data_fim) {
            $where[] = 'cal_data <= ?';
            $params[] = $data_fim;
        }

        $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        
        // Primeiro, pegar os períodos de calendário
        $sql = "
            SELECT 
                c.cal_codigo_codi,
                c.cal_data,
                c.cal_hora_inicio,
                c.cal_hora_fim,
                c.cal_recurso_codi_id,
                c.cal_turno_codi,
                c.cal_id,
                r.cod_nome_recurso as recurso_nome
            FROM codi_calendario c
            LEFT JOIN codi_recursos r ON c.cal_recurso_codi_id = r.cod_codigo_codi
            $whereClause
            ORDER BY c.cal_data DESC, c.cal_hora_inicio
            LIMIT $limit
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        $dados = [];
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            // Para cada período, buscar os items executados no mesmo recurso naquele dia
            $subQuery = "
                SELECT perf_dados_json, perf_item_codi
                FROM codi_performance
                WHERE perf_recurso_codi_id = ?
                LIMIT 5
            ";
            
            $subStmt = $pdo->prepare($subQuery);
            $subStmt->execute([$row['cal_recurso_codi_id']]);
            
            $ops = [];
            $items = [];
            
            foreach ($subStmt->fetchAll(\PDO::FETCH_ASSOC) as $perf) {
                if ($perf['perf_dados_json']) {
                    $json = json_decode($perf['perf_dados_json'], true);
                    if (isset($json['ordemProducao']) && $json['ordemProducao']) {
                        $ops[] = $json['ordemProducao'];
                    }
                    if ($perf['perf_item_codi']) {
                        $items[] = $perf['perf_item_codi'];
                    }
                }
            }
            
            $op_principal = !empty($ops) ? reset($ops) : null;
            $item_principal = !empty($items) ? reset($items) : 0;
            
            $dados[] = [
                'codigo' => (int)$row['cal_codigo_codi'],
                'data' => $row['cal_data'],
                'hora_inicio' => $row['cal_hora_inicio'],
                'hora_fim' => $row['cal_hora_fim'],
                'recurso_id' => (int)$row['cal_recurso_codi_id'],
                'recurso_nome' => $row['recurso_nome'] ?? 'N/A',
                'turno_id' => (int)($row['cal_turno_codi'] ?? 0),
                'item_id' => (int)$item_principal,
                'op' => $op_principal,
                'op_display' => $op_principal ?: '—',
                'total_items' => count(array_unique($items))
            ];
        }
        echo json_encode($dados);
        exit;
    }

    // Items com informações completas (para mapear com OP)
    if ($action === 'items_mapping') {
        $result = $pdo->query('
            SELECT DISTINCT
                perf_item_codi as item_id,
                JSON_EXTRACT(perf_dados_json, "$.item.nomeItem") as item_nome,
                JSON_EXTRACT(perf_dados_json, "$.item.codItem") as item_codigo,
                COUNT(*) as total_execucoes,
                COUNT(DISTINCT perf_recurso_codi_id) as recursos_unicos
            FROM codi_performance
            WHERE perf_item_codi IS NOT NULL
            GROUP BY perf_item_codi
            ORDER BY total_execucoes DESC
            LIMIT 50
        ');
        
        $dados = [];
        foreach ($result->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $dados[] = [
                'item_id' => (int)$row['item_id'],
                'item_nome' => $row['item_nome'] ? json_decode($row['item_nome'], true) : 'N/A',
                'item_codigo' => $row['item_codigo'] ? json_decode($row['item_codigo'], true) : null,
                'total_execucoes' => (int)$row['total_execucoes'],
                'recursos' => (int)$row['recursos_unicos']
            ];
        }
        echo json_encode($dados);
        exit;
    }

    // Performance
        $limit = (int)($_GET['limit'] ?? 100);
        $stmt = $pdo->prepare('
            SELECT 
                perf_codigo_codi,
                perf_recurso_codi_id,
                perf_item_codi,
                perf_dados_json,
                perf_ordem_producao
            FROM codi_performance 
            LIMIT ?
        ');
        $stmt->execute([$limit]);

        $dados = [];
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $json = json_decode($row['perf_dados_json'], true);
            $dados[] = [
                'codigo' => (int)$row['perf_codigo_codi'],
                'recurso_id' => (int)($row['perf_recurso_codi_id'] ?? 0),
                'item_id' => (int)($row['perf_item_codi'] ?? 0),
                'item_nome' => $json['item']['nomeItem'] ?? 'N/A',
                'ordem_producao' => $row['perf_ordem_producao'] ?? '—'
            ];
        }
        echo json_encode($dados);
        exit;
    }

    // Timeline
    if ($action === 'timeline') {
        $result = $pdo->query('
            SELECT 
                c.cal_data as data,
                r.cod_nome_recurso as recurso,
                CONCAT(c.cal_hora_inicio, " - ", c.cal_hora_fim) as periodo
            FROM codi_calendario c
            LEFT JOIN codi_recursos r ON c.cal_recurso_codi_id = r.cod_codigo_codi
            ORDER BY c.cal_data DESC
            LIMIT 50
        ');
        
        $dados = [];
        foreach ($result->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $dados[] = [
                'data' => $row['data'],
                'recurso' => $row['recurso'] ?? 'N/A',
                'periodo' => $row['periodo']
            ];
        }
        echo json_encode($dados);
        exit;
    }

    // Análises
    if ($action === 'analise') {
        // Distribuição por recurso
        $result = $pdo->query('
            SELECT 
                COALESCE(r.cod_nome_recurso, "SEM RECURSO") as recurso,
                COUNT(c.cal_id) as total_periodos,
                COUNT(DISTINCT c.cal_data) as dias_diferentes,
                MIN(c.cal_data) as primeira_data,
                MAX(c.cal_data) as ultima_data
            FROM codi_calendario c
            LEFT JOIN codi_recursos r ON c.cal_recurso_codi_id = r.cod_codigo_codi
            GROUP BY c.cal_recurso_codi_id
            ORDER BY total_periodos DESC
        ');
        $distribuicao = $result->fetchAll(\PDO::FETCH_ASSOC);

        // Items top
        $result = $pdo->query('
            SELECT 
                perf_item_codi as item_id,
                COUNT(*) as total_execucoes,
                COUNT(DISTINCT perf_recurso_codi_id) as recursos_diferentes
            FROM codi_performance
            WHERE perf_item_codi IS NOT NULL
            GROUP BY perf_item_codi
            ORDER BY total_execucoes DESC
            LIMIT 10
        ');
        $items_top = $result->fetchAll(\PDO::FETCH_ASSOC);

        // Temporal
        $result = $pdo->query('
            SELECT 
                DATE_FORMAT(cal_data, "%Y-%m") as mes,
                COUNT(*) as total,
                COUNT(DISTINCT cal_data) as dias,
                MIN(cal_hora_inicio) as primeiro_horario,
                MAX(cal_hora_fim) as ultimo_horario
            FROM codi_calendario
            GROUP BY DATE_FORMAT(cal_data, "%Y-%m")
            ORDER BY mes DESC
        ');
        $temporal = $result->fetchAll(\PDO::FETCH_ASSOC);

        echo json_encode([
            'distribuicao' => $distribuicao,
            'items_top' => $items_top,
            'temporal' => $temporal
        ]);
        exit;
    }

    // Se chegar aqui, ação não reconhecida
    http_response_code(404);
    echo json_encode(['erro' => 'Ação não reconhecida: ' . $action]);

} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode([
        'erro' => 'Erro no servidor: ' . $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
}
?>
