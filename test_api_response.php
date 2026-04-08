
<?php
// Database connection
$pdo = new PDO(
    'mysql:host=127.0.0.1:3306;dbname=controlepcp_sandbox;charset=utf8mb4',
    'root',
    'k7m2y9u4'
);

try {
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
        'planejados' => count($items),
        'realizados' => count($items2),
        'sample' => count($allItems) > 0 ? $allItems[0] : null
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>

