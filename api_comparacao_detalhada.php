<?php
/**
 * API Detalhada - Previsto vs Realizado com SKU/Descrição
 * Retorna informações granulares de cada item planejado vs executado
 */

header('Content-Type: application/json; charset=utf-8');

$pdo = new PDO(
    'mysql:host=127.0.0.1:3306;dbname=controlepcp_sandbox;charset=utf8mb4',
    'root',
    'k7m2y9u4'
);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

try {
    $action = $_GET['action'] ?? 'items';
    $sku = $_GET['sku'] ?? null;
    $filter_recurso = $_GET['recurso'] ?? null;
    
    if ($action === 'items') {
        /**
         * Retorna todos os itens planejados com informações de execução realizada
         * Agrupa por SKU e período
         */
        
        $sql = "
            SELECT 
                s.sch_id,
                s.sch_sku AS sku,
                s.sch_descricao AS produto,
                s.sch_quantidade AS quantidade_planejada,
                s.sch_duracao_minutos AS duracao_planejada_minutos,
                s.sch_data_inicio AS data_planejada,
                s.sch_hora_inicio AS hora_inicio_planejada,
                s.sch_hora_fim AS hora_fim_planejada,
                s.sch_status AS status_planejamento,
                COUNT(DISTINCT c.cal_id) AS quantidade_execucoes_realizado,
                MIN(c.cal_data) AS data_primeira_execucao,
                MAX(c.cal_data) AS data_ultima_execucao,
                GROUP_CONCAT(DISTINCT CONCAT(c.cal_data, ' ', c.cal_hora_inicio) ORDER BY c.cal_data) AS datas_execucao,
                GROUP_CONCAT(DISTINCT c.cal_recurso_codi_id ORDER BY c.cal_recurso_codi_id) AS recursos_utilizados,
                CASE 
                    WHEN COUNT(DISTINCT c.cal_id) > 0 THEN 'Executado'
                    ELSE 'Não Executado'
                END AS status_execucao,
                DATEDIFF(MIN(c.cal_data), s.sch_data_inicio) AS dias_diferenca
            FROM sch_linhas s
            LEFT JOIN codi_calendario c ON 
                YEAR(c.cal_data) = YEAR(s.sch_data_inicio)
                AND MONTH(c.cal_data) = MONTH(s.sch_data_inicio)
            WHERE s.sch_quantidade IS NOT NULL 
            AND s.sch_quantidade > 0
        ";
        
        $params = [];
        
        if ($sku) {
            $sql .= " AND s.sch_sku = ?";
            $params[] = $sku;
        }
        
        $sql .= " GROUP BY s.sch_id 
                  ORDER BY s.sch_data_inicio DESC, s.sch_id DESC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Transformar para valores numéricos
        foreach ($items as &$item) {
            $item['sch_id'] = (int)$item['sch_id'];
            $item['quantidade_planejada'] = (float)$item['quantidade_planejada'];
            $item['duracao_planejada_minutos'] = (int)$item['duracao_planejada_minutos'];
            $item['quantidade_execucoes_realizado'] = (int)$item['quantidade_execucoes_realizado'];
            $item['dias_diferenca'] = $item['dias_diferenca'] ? (int)$item['dias_diferenca'] : null;
        }
        
        echo json_encode([
            'status' => 'ok',
            'total' => count($items),
            'items' => $items
        ]);
        
    } elseif ($action === 'detalhado') {
        /**
         * Retorna comparativo detalhado por item, mostrando:
         * - O que foi planejado
         * - O que foi realizado no CODI
         * - Diferenças de quantidade e datas
         */
        
        $sql = "
            SELECT 
                'Planejado' AS tipo,
                s.sch_id AS id_item,
                s.sch_sku AS sku,
                s.sch_descricao AS produto,
                s.sch_quantidade AS quantidade,
                s.sch_duracao_minutos AS duracao_minutos,
                s.sch_data_inicio AS data,
                s.sch_hora_inicio AS hora_inicio,
                s.sch_hora_fim AS hora_fim,
                s.sch_status AS status,
                NULL AS recurso_id,
                NULL AS execucao_id
            FROM sch_linhas s
            WHERE s.sch_quantidade IS NOT NULL 
            AND s.sch_quantidade > 0
            
            UNION ALL
            
            SELECT 
                'Realizado' AS tipo,
                NULL AS id_item,
                NULL AS sku,
                NULL AS produto,
                1 AS quantidade,
                TIMESTAMPDIFF(MINUTE, c.cal_hora_inicio, c.cal_hora_fim) AS duracao_minutos,
                c.cal_data AS data,
                c.cal_hora_inicio AS hora_inicio,
                c.cal_hora_fim AS hora_fim,
                'Executado' AS status,
                c.cal_recurso_codi_id AS recurso_id,
                c.cal_id AS execucao_id
            FROM codi_calendario c
            WHERE c.cal_data IS NOT NULL
            
            ORDER BY data DESC, tipo ASC
            LIMIT 500
        ";
        
        $result = $pdo->query($sql);
        $items = $result->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'status' => 'ok',
            'total' => count($items),
            'items' => $items
        ]);
        
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Action not found']);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
