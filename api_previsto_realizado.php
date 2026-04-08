<?php
/**
 * API Backend - Dashboard Previsto vs Realizado
 * Cruzamento de dados entre planejamento (sch_linhas) e realizado (codi_calendario)
 */

header('Content-Type: application/json; charset=utf-8');

$pdo = new PDO(
    'mysql:host=127.0.0.1:3306;dbname=controlepcp_sandbox;charset=utf8mb4',
    'root',
    'k7m2y9u4'
);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

try {
    $action = $_GET['action'] ?? 'error';
    
    if ($action === 'stats') {
        // Estatísticas gerais
        $previsto = (int)$pdo->query('SELECT COUNT(*) FROM sch_linhas WHERE sch_tipo = "producao"')->fetchColumn();
        $realizado = (int)$pdo->query('SELECT COUNT(*) FROM codi_calendario WHERE YEAR(cal_data) = 2026')->fetchColumn();
        
        $prev_duracao = $pdo->query('SELECT SUM(sch_duracao_minutos) as total FROM sch_linhas WHERE sch_tipo = "producao"')->fetch(PDO::FETCH_ASSOC);
        $real_duracao = $pdo->query('
            SELECT SUM(TIME_TO_SEC(TIMEDIFF(cal_hora_fim, cal_hora_inicio))/60) as total 
            FROM codi_calendario 
            WHERE YEAR(cal_data) = 2026
        ')->fetch(PDO::FETCH_ASSOC);
        
        $prev_quantidade = $pdo->query('SELECT SUM(sch_quantidade) as total FROM sch_linhas WHERE sch_tipo = "producao"')->fetch(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'previsto_count' => $previsto,
            'realizado_count' => $realizado,
            'previsto_duracao_minutos' => (int)($prev_duracao['total'] ?? 0),
            'realizado_duracao_minutos' => (int)($real_duracao['total'] ?? 0),
            'previsto_quantidade' => (float)($prev_quantidade['total'] ?? 0)
        ]);
        exit;
    }
    
    if ($action === 'recursos') {
        // Lista de recursos com comparação
        $result = $pdo->query('
            SELECT 
                COALESCE(r.cod_nome_recurso, "Sem recurso") as recurso,
                COALESCE(r.cod_codigo_codi, 0) as recurso_id,
                COUNT(DISTINCT CASE WHEN s.sch_id IS NOT NULL THEN s.sch_id END) as previsto_count,
                COUNT(DISTINCT CASE WHEN c.cal_id IS NOT NULL THEN c.cal_id END) as realizado_count,
                SUM(CASE WHEN s.sch_id IS NOT NULL THEN s.sch_duracao_minutos ELSE 0 END) as previsto_minutos,
                SUM(CASE WHEN c.cal_id IS NOT NULL THEN TIME_TO_SEC(TIMEDIFF(c.cal_hora_fim, c.cal_hora_inicio))/60 ELSE 0 END) as realizado_minutos,
                SUM(CASE WHEN s.sch_id IS NOT NULL THEN s.sch_quantidade ELSE 0 END) as previsto_quantidade
            FROM codi_recursos r
            LEFT JOIN sch_linhas s ON r.cod_nome_recurso LIKE CONCAT("%", REGEXP_SUBSTR(s.sch_descricao, "[A-Z0-9]+"), "%")
            LEFT JOIN codi_calendario c ON r.cod_codigo_codi = c.cal_recurso_codi_id AND YEAR(c.cal_data) = 2026
            GROUP BY r.cod_codigo_codi, r.cod_nome_recurso
            ORDER BY recurso
        ');
        
        $dados = [];
        foreach ($result->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $dados[] = [
                'recurso' => $row['recurso'],
                'recurso_id' => (int)$row['recurso_id'],
                'previsto_count' => (int)($row['previsto_count'] ?? 0),
                'realizado_count' => (int)($row['realizado_count'] ?? 0),
                'previsto_minutos' => (int)($row['previsto_minutos'] ?? 0),
                'realizado_minutos' => (int)($row['realizado_minutos'] ?? 0),
                'previsto_quantidade' => (float)($row['previsto_quantidade'] ?? 0),
                'variacao_percentual' => $row['previsto_minutos'] ? round((($row['realizado_minutos'] - $row['previsto_minutos']) / $row['previsto_minutos']) * 100, 2) : 0
            ];
        }
        
        echo json_encode($dados);
        exit;
    }
    
    if ($action === 'timeline') {
        // Dados por dia
        $result = $pdo->query('
            SELECT 
                DATE(c.cal_data) as data,
                COUNT(*) as realizado_count,
                SUM(TIME_TO_SEC(TIMEDIFF(c.cal_hora_fim, c.cal_hora_inicio))/3600) as realizado_horas,
                COUNT(DISTINCT c.cal_recurso_codi_id) as recursos_utilizados
            FROM codi_calendario c
            WHERE YEAR(c.cal_data) = 2026
            GROUP BY DATE(c.cal_data)
            ORDER BY data DESC
            LIMIT 60
        ');
        
        $dados = [];
        foreach ($result->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $dados[] = [
                'data' => $row['data'],
                'realizado_count' => (int)$row['realizado_count'],
                'realizado_horas' => round((float)$row['realizado_horas'], 2),
                'recursos_utilizados' => (int)$row['recursos_utilizados']
            ];
        }
        
        echo json_encode($dados);
        exit;
    }
    
    if ($action === 'comparativo_detalhado') {
        // Comparativo detalhado por período
        $recurso_filter = $_GET['recurso'] ?? null;
        
        $where = '';
        if ($recurso_filter) {
            $where = ' AND r.cod_codigo_codi = ' . (int)$recurso_filter;
        }
        
        // Previsão
        $result_prev = $pdo->query('
            SELECT 
                sch_data_inicio as data,
                COUNT(*) as count,
                SUM(sch_duracao_minutos) as minutos,
                SUM(sch_quantidade) as quantidade
            FROM sch_linhas
            WHERE sch_tipo = "producao"
            GROUP BY sch_data_inicio
            ORDER BY data DESC
        ');
        
        $previsto_por_dia = [];
        foreach ($result_prev->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $previsto_por_dia[$row['data']] = [
                'count' => (int)$row['count'],
                'minutos' => (int)($row['minutos'] ?? 0),
                'quantidade' => (float)($row['quantidade'] ?? 0)
            ];
        }
        
        // Realizado
        $result_real = $pdo->query('
            SELECT 
                DATE(c.cal_data) as data,
                COUNT(*) as count,
                SUM(TIME_TO_SEC(TIMEDIFF(c.cal_hora_fim, c.cal_hora_inicio))/60) as minutos,
                COUNT(DISTINCT c.cal_recurso_codi_id) as recursos
            FROM codi_calendario c
            WHERE YEAR(c.cal_data) = 2026
            GROUP BY DATE(c.cal_data)
            ORDER BY data DESC
        ');
        
        $realizado_por_dia = [];
        foreach ($result_real->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $realizado_por_dia[$row['data']] = [
                'count' => (int)$row['count'],
                'minutos' => (int)($row['minutos'] ?? 0),
                'recursos' => (int)$row['recursos']
            ];
        }
        
        echo json_encode([
            'previsto' => $previsto_por_dia,
            'realizado' => $realizado_por_dia
        ]);
        exit;
    }
    
    http_response_code(400);
    echo json_encode(['erro' => 'Ação não reconhecida']);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['erro' => $e->getMessage()]);
}
?>
