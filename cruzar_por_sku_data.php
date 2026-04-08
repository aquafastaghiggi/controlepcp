<?php
$pdo = new PDO(
    'mysql:host=127.0.0.1:3306;dbname=controlepcp_sandbox;charset=utf8mb4',
    'root',
    'k7m2y9u4'
);

// Planejado: OP 201055
$op_planejada = '201055';

echo "=== OP $op_planejada - PLANEJADO ===\n";
$sql = "
    SELECT 
        s.sch_sku,
        s.sch_descricao,
        s.sch_quantidade,
        s.sch_duracao_minutos,
        DATE(s.sch_data_inicio) AS data_planejada
    FROM sch_linhas s
    LEFT JOIN prg_itens p ON s.sch_programa_id = p.prg_programa_id AND s.sch_sku = p.prg_sku
    WHERE p.prg_itens_op = '$op_planejada'
    GROUP BY s.sch_sku, DATE(s.sch_data_inicio)
";

$result = $pdo->query($sql);
$planejados = $result->fetchAll(PDO::FETCH_ASSOC);

foreach ($planejados as $p) {
    echo "SKU: {$p['sch_sku']} | {$p['sch_descricao']} | Qtd: {$p['sch_quantidade']} | Data: {$p['data_planejada']}\n";
    
    // Procurar o mesmo SKU no realizado
    $sku = $p['sch_sku'];
    $data = $p['data_planejada'];
    
    echo "  → Procurando realizado para SKU $sku perto de $data...\n";
    
    $sql2 = "
        SELECT 
            JSON_EXTRACT(cp.perf_dados_json, '$.item.codItem') AS sku_codi,
            DATE(cc.cal_data) AS data_execucao,
            cr.cod_nome_recurso AS maquina,
            COUNT(*) AS exec_count
        FROM codi_performance cp
        LEFT JOIN codi_calendario cc ON cp.perf_item_codi = cc.cal_grandeza_codi
        LEFT JOIN codi_recursos cr ON cc.cal_recurso_codi_id = cr.cod_id
        WHERE JSON_EXTRACT(cp.perf_dados_json, '$.item.codItem') = CONCAT('\"', '$sku', '\"')
        GROUP BY JSON_EXTRACT(cp.perf_dados_json, '$.item.codItem'), DATE(cc.cal_data), cr.cod_nome_recurso
        ORDER BY DATE(cc.cal_data) DESC
    ";
    
    $result2 = $pdo->query($sql2);
    $realizados = $result2->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($realizados) > 0) {
        foreach ($realizados as $r) {
            echo "    ✓ ENCONTRADO: Data {$r['data_execucao']} | Máquina: {$r['maquina']} | Exec: {$r['exec_count']}\n";
        }
    } else {
        echo "    ✗ Nenhum realizado encontrado\n";
    }
    echo "\n";
}

// Teste: Todos os SKUs do codi_performance
echo "=== TODOS OS SKUs REALIZADOS NO CODI ===\n";
$sql3 = "
    SELECT DISTINCT
        JSON_EXTRACT(perf_dados_json, '$.item.codItem') AS sku
    FROM codi_performance
    ORDER BY JSON_EXTRACT(perf_dados_json, '$.item.codItem')
    LIMIT 20
";

$result3 = $pdo->query($sql3);
$skus_codi = [];
while ($row = $result3->fetch(PDO::FETCH_ASSOC)) {
    $sku = trim($row['sku'], '"');
    if (!empty($sku)) {
        $skus_codi[] = $sku;
        echo "SKU: $sku\n";
    }
}

echo "\n=== Cruzar SKU de 201055 com SKUs do CODI ===\n";
$sql4 = "
    SELECT DISTINCT s.sch_sku
    FROM sch_linhas s
    LEFT JOIN prg_itens p ON s.sch_programa_id = p.prg_programa_id AND s.sch_sku = p.prg_sku
    WHERE p.prg_itens_op = '201055'
";

$result4 = $pdo->query($sql4);
while ($row = $result4->fetch(PDO::FETCH_ASSOC)) {
    $sku_planejado = $row['sch_sku'];
    $encontrado = in_array($sku_planejado, $skus_codi) ? "✓ SIM" : "✗ NÃO";
    echo "SKU $sku_planejado no CODI? $encontrado\n";
}
