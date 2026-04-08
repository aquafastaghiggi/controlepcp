<?php
/**
 * API CRUZAMENTO - Previsto vs Realizado + OP e SKU
 * 
 * Integra:
 * - sch_linhas (planejamento local)
 * - prg_itens (programa com OP)
 * - codi_performance (configuração de itens)
 * - codi_calendario (datas de execução)
 * - API CODI /ordemProducao (OP e quantidade da ordem)
 */

header('Content-Type: application/json; charset=utf-8');

$pdo = new PDO(
    'mysql:host=127.0.0.1:3306;dbname=controlepcp_sandbox;charset=utf8mb4',
    'root',
    'k7m2y9u4'
);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

try {
    $action = $_GET['action'] ?? 'lista_completa';
    $sku = $_GET['sku'] ?? null;
    
    if ($action === 'lista_completa') {
        /**
         * Retorna lista completa com:
         * - Planejado: sch_linhas + prg_itens (com OP)
         * - Realizado: codi_calendario + codi_performance
         * - API: ordemProducao (quantidade total da OP)
         */
        
        $sql = "
            SELECT 
                'Planejado' AS tipo,
                s.sch_id,
                s.sch_sku AS sku,
                s.sch_descricao AS descricao,
                s.sch_quantidade AS quantidade_planejada,
                s.sch_duracao_minutos AS duracao_minutos,
                s.sch_data_inicio AS data_inicio,
                COALESCE(p.prg_itens_op, 'SEM OP') AS op_planejada,
                s.sch_status AS status,
                NULL AS data_execucao,
                NULL AS recurso_executado,
                NULL AS quantidade_produzida,
                NULL AS status_execucao
            FROM sch_linhas s
            LEFT JOIN prg_itens p ON s.sch_programa_id = p.prg_programa_id AND s.sch_sku = p.prg_sku
            WHERE s.sch_quantidade IS NOT NULL AND s.sch_quantidade > 0
            LIMIT 260
        ";
        
        $result = $pdo->query($sql);
        $items = $result->fetchAll(PDO::FETCH_ASSOC);
        
        // Adicionar dados do CODI
        $sql2 = "
            SELECT 
                'Realizado' AS tipo,
                NULL AS sch_id,
                NULL AS sku,
                NULL AS descricao,
                NULL AS quantidade_planejada,
                NULL AS duracao_minutos,
                NULL AS data_inicio,
                NULL AS op_planejada,
                NULL AS status,
                c.cal_data AS data_execucao,
                cr.cod_nome_recurso AS recurso_executado,
                NULL AS quantidade_produzida,
                'Executado' AS status_execucao
            FROM codi_calendario c
            LEFT JOIN codi_recursos cr ON c.cal_recurso_codi_id = cr.cod_id
            LIMIT 260
        ";
        
        $result2 = $pdo->query($sql2);
        $items2 = $result2->fetchAll(PDO::FETCH_ASSOC);
        
        $allItems = array_merge($items, $items2);
        
        echo json_encode([
            'status' => 'ok',
            'total' => count($allItems),
            'items' => $allItems
        ]);
        exit;
        
    } elseif ($action === 'por_op') {
        /**
         * Agrupa por OP mostrando toda a jornada:
         * Planejado → Realizado → API Status
         */
        
        $sql = "
            SELECT DISTINCT
                COALESCE(p.prg_itens_op, 'DESCONHECIDA') AS op,
                s.sch_sku AS sku,
                s.sch_descricao AS descricao,
                s.sch_quantidade AS qtd_planejada,
                COUNT(DISTINCT c.cal_id) AS qtd_execucoes,
                COUNT(DISTINCT c.cal_recurso_codi_id) AS recursos_utilizados,
                MIN(c.cal_data) AS primeira_execucao,
                MAX(c.cal_data) AS ultima_execucao,
                s.sch_data_inicio AS data_planejada,
                GROUP_CONCAT(DISTINCT cr.cres_nome) AS maquinas
            FROM sch_linhas s
            LEFT JOIN prg_itens p ON s.sch_programa_id = p.prg_programa_id AND s.sch_sku = p.prg_sku
            LEFT JOIN codi_calendario c ON YEAR(c.cal_data) = YEAR(s.sch_data_inicio) AND MONTH(c.cal_data) = MONTH(s.sch_data_inicio)
            LEFT JOIN codi_recursos cr ON c.cal_recurso_codi_id = cr.cres_id_codi
            WHERE s.sch_quantidade IS NOT NULL AND s.sch_quantidade > 0
            GROUP BY op, sku
            ORDER BY op DESC
        ";
        
        $result = $pdo->query($sql);
        $items = $result->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'status' => 'ok',
            'total' => count($items),
            'items' => $items
        ]);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
