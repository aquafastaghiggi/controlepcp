<?php
/**
 * API CRUZAMENTO AVANÇADO - Do JSON do CODI
 * Extrai OP do JSON de codi_performance
 * Cruza com planejamento por OP + SKU + Data
 */

header('Content-Type: application/json; charset=utf-8');

$pdo = new PDO(
    'mysql:host=127.0.0.1:3306;dbname=controlepcp_sandbox;charset=utf8mb4',
    'root',
    'k7m2y9u4'
);

try {
    $action = $_GET['action'] ?? 'detalhe_op';
    $op = $_GET['op'] ?? null;
    
    // ENDPOINT 1: Detalhe de uma OP específica
    if ($action === 'detalhe_op' && $op) {
        // PLANEJADO
        $sql = "
            SELECT 
                'Planejado' AS tipo,
                p.prg_itens_op AS op,
                s.sch_sku AS sku,
                s.sch_descricao AS descricao,
                s.sch_quantidade AS quantidade_planejada,
                s.sch_duracao_minutos AS duracao,
                s.sch_data_inicio AS data_planejada,
                s.sch_status AS status,
                NULL AS data_realizada,
                NULL AS maquina_realizada,
                NULL AS item_codi
            FROM sch_linhas s
            LEFT JOIN prg_itens p ON s.sch_programa_id = p.prg_programa_id AND s.sch_sku = p.prg_sku
            WHERE p.prg_itens_op = :op
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':op' => $op]);
        $planejados = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // REALIZADO - Mostrar todos, não há controle de OP aqui
        $realizados = [];
        $sql2 = "
            SELECT 
                'Realizado' AS tipo,
                NULL AS op,
                JSON_UNQUOTE(JSON_EXTRACT(cp.perf_dados_json, '$.item.codItem')) AS sku,
                JSON_UNQUOTE(JSON_EXTRACT(cp.perf_dados_json, '$.item.nomeItem')) AS descricao,
                NULL AS quantidade_planejada,
                NULL AS duracao,
                NULL AS data_planejada,
                NULL AS status,
                cc.cal_data AS data_realizada,
                cr.cod_nome_recurso AS maquina_realizada,
                cp.perf_item_codi AS item_codi
            FROM codi_performance cp
            LEFT JOIN codi_calendario cc ON cp.perf_item_codi = cc.cal_grandeza_codi
            LEFT JOIN codi_recursos cr ON cc.cal_recurso_codi_id = cr.cod_id
            ORDER BY cc.cal_data DESC
        ";
        
        $result2 = $pdo->query($sql2);
        $realizados = $result2->fetchAll(PDO::FETCH_ASSOC);
        
        $items = array_merge($planejados, $realizados);
        
        echo json_encode([
            'status' => 'ok',
            'op' => $op,
            'total' => count($items),
            'planejados' => count($planejados),
            'realizados' => count($realizados),
            'items' => $items
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
    
    // ENDPOINT 2: Lista todas as OPs com resumo
    else if ($action === 'lista_ops') {
        $sql = "
            SELECT DISTINCT
                p.prg_itens_op AS op,
                COUNT(DISTINCT s.sch_id) AS itens_planejados,
                SUM(s.sch_quantidade) AS qtd_planejada,
                GROUP_CONCAT(DISTINCT s.sch_sku) AS skus,
                MIN(s.sch_data_inicio) AS data_inicio_planejada,
                MAX(s.sch_data_inicio) AS data_fim_planejada
            FROM sch_linhas s
            LEFT JOIN prg_itens p ON s.sch_programa_id = p.prg_programa_id AND s.sch_sku = p.prg_sku
            WHERE p.prg_itens_op IS NOT NULL
            GROUP BY p.prg_itens_op
            ORDER BY p.prg_itens_op ASC
        ";
        
        $result = $pdo->query($sql);
        $ops = $result->fetchAll(PDO::FETCH_ASSOC);
        
        // Para cada OP, contar realizado
        $sql2 = "
            SELECT 
                JSON_EXTRACT(perf_dados_json, '$.ordemProducao') AS op,
                COUNT(*) AS execucoes_registradas
            FROM codi_performance
            WHERE JSON_EXTRACT(perf_dados_json, '$.ordemProducao') IS NOT NULL
            GROUP BY JSON_EXTRACT(perf_dados_json, '$.ordemProducao')
        ";
        
        $result2 = $pdo->query($sql2);
        $realizados = [];
        while ($row = $result2->fetch(PDO::FETCH_ASSOC)) {
            $realizados[$row['op']] = $row['execucoes_registradas'];
        }
        
        // Merge
        foreach ($ops as &$op) {
            $op['execucoes_registradas'] = $realizados[$op['op']] ?? 0;
        }
        
        echo json_encode([
            'status' => 'ok',
            'total_ops' => count($ops),
            'ops' => $ops
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
    
    // ENDPOINT 3: Todas as OPs com planejado vs realizado lado a lado
    else if ($action === 'todas_ops_completo') {
        $sql = "
            SELECT 
                p.prg_itens_op AS op_planejada,
                s.sch_sku AS sku_planejado,
                s.sch_descricao AS descricao_planejada,
                s.sch_quantidade AS quantidade_planejada,
                s.sch_duracao_minutos AS duracao_minutos,
                s.sch_data_inicio AS data_inicio_planejada,
                p.prg_sequencia AS seq_planejada
            FROM sch_linhas s
            LEFT JOIN prg_itens p ON s.sch_programa_id = p.prg_programa_id AND s.sch_sku = p.prg_sku
            WHERE s.sch_quantidade IS NOT NULL AND s.sch_quantidade > 0
            ORDER BY p.prg_itens_op, s.sch_data_inicio
        ";
        
        $result = $pdo->query($sql);
        $planejados = $result->fetchAll(PDO::FETCH_ASSOC);
        
        // Buscar realizados no CODI
        $sql2 = "
            SELECT 
                JSON_EXTRACT(cp.perf_dados_json, '$.ordemProducao') AS op_codi,
                JSON_EXTRACT(cp.perf_dados_json, '$.item.codItem') AS sku_codi,
                JSON_EXTRACT(cp.perf_dados_json, '$.item.nomeItem') AS descricao_codi,
                cc.cal_data AS data_execucao,
                cr.cod_nome_recurso AS maquina,
                COUNT(*) AS registros_execucao
            FROM codi_performance cp
            LEFT JOIN codi_calendario cc ON cp.perf_item_codi = cc.cal_grandeza_codi
            LEFT JOIN codi_recursos cr ON cc.cal_recurso_codi_id = cr.cod_id
            WHERE JSON_EXTRACT(cp.perf_dados_json, '$.ordemProducao') IS NOT NULL
            GROUP BY JSON_EXTRACT(cp.perf_dados_json, '$.ordemProducao'), cc.cal_data, cr.cod_nome_recurso
            ORDER BY JSON_EXTRACT(cp.perf_dados_json, '$.ordemProducao')
        ";
        
        $result2 = $pdo->query($sql2);
        $realizados = $result2->fetchAll(PDO::FETCH_ASSOC);
        
        // Organizar por OP
        $realizados_por_op = [];
        foreach ($realizados as $item) {
            $op = $item['op_codi'];
            if (!isset($realizados_por_op[$op])) {
                $realizados_por_op[$op] = [];
            }
            $realizados_por_op[$op][] = $item;
        }
        
        // Cruzar
        foreach ($planejados as &$item) {
            $op = $item['op_planejada'];
            $item['realizado'] = $realizados_por_op[$op] ?? [];
            $item['tem_execucao'] = !empty($item['realizado']);
        }
        
        echo json_encode([
            'status' => 'ok',
            'planejados' => count($planejados),
            'com_execucao' => count(array_filter($planejados, fn($p) => $p['tem_execucao'])),
            'items' => $planejados
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
    
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>
