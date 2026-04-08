<?php
$pdo = new PDO(
    'mysql:host=127.0.0.1:3306;dbname=controlepcp_sandbox;charset=utf8mb4',
    'root',
    'k7m2y9u4'
);

echo "=== TESTE API: OP 201055 ===\n\n";

// PLANEJADO
echo "PLANEJADO:\n";
$sql = "
    SELECT 
        p.prg_itens_op AS op,
        s.sch_sku AS sku,
        s.sch_descricao AS descricao,
        s.sch_quantidade AS quantidade_planejada,
        s.sch_duracao_minutos AS duracao,
        DATE(s.sch_data_inicio) AS data_planejada
    FROM sch_linhas s
    LEFT JOIN prg_itens p ON s.sch_programa_id = p.prg_programa_id AND s.sch_sku = p.prg_sku
    WHERE p.prg_itens_op = '201055'
";

$result = $pdo->query($sql);
while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
    echo json_encode($row, JSON_UNESCAPED_UNICODE) . "\n";
}

echo "\nREALIZADO (10 primeiros):\n";
$sql2 = "
    SELECT 
        JSON_UNQUOTE(JSON_EXTRACT(cp.perf_dados_json, '$.item.codItem')) AS sku,
        JSON_UNQUOTE(JSON_EXTRACT(cp.perf_dados_json, '$.item.nomeItem')) AS descricao,
        DATE(cc.cal_data) AS data_realizada,
        cr.cod_nome_recurso AS maquina
    FROM codi_performance cp
    LEFT JOIN codi_calendario cc ON cp.perf_item_codi = cc.cal_grandeza_codi
    LEFT JOIN codi_recursos cr ON cc.cal_recurso_codi_id = cr.cod_id
    ORDER BY cc.cal_data DESC
    LIMIT 10
";

$result2 = $pdo->query($sql2);
while ($row = $result2->fetch(PDO::FETCH_ASSOC)) {
    echo json_encode($row, JSON_UNESCAPED_UNICODE) . "\n";
}

// OPs com resumo
echo "\n\nOPs COM RESUMO:\n";
$sql3 = "
    SELECT 
        p.prg_itens_op AS op,
        COUNT(DISTINCT s.sch_id) AS itens_planejados,
        SUM(s.sch_quantidade) AS qtd_total,
        GROUP_CONCAT(DISTINCT s.sch_sku) AS skus,
        COUNT(DISTINCT cp.perf_id) AS registros_codi
    FROM prg_itens p
    LEFT JOIN sch_linhas s ON s.sch_programa_id = p.prg_programa_id AND s.sch_sku = p.prg_sku
    LEFT JOIN codi_performance cp ON 1=1
    WHERE p.prg_itens_op IS NOT NULL
    GROUP BY p.prg_itens_op
    ORDER BY p.prg_itens_op
    LIMIT 10
";

$result3 = $pdo->query($sql3);
while ($row = $result3->fetch(PDO::FETCH_ASSOC)) {
    echo "OP {$row['op']}: {$row['itens_planejados']} itens, Qtd {$row['qtd_total']}, CODI: {$row['registros_codi']} registros\n";
}
