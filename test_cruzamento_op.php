<?php
$pdo = new PDO(
    'mysql:host=127.0.0.1:3306;dbname=controlepcp_sandbox;charset=utf8mb4',
    'root',
    'k7m2y9u4'
);

// Testar: Ver dados da OP 201055 no planejamento
echo "=== PLANEJADO (OP 201055) ===\n";
$sql = "
    SELECT 
        p.prg_itens_op AS op,
        s.sch_sku AS sku,
        s.sch_descricao AS descricao,
        s.sch_quantidade AS quantidade,
        s.sch_data_inicio AS data
    FROM sch_linhas s
    LEFT JOIN prg_itens p ON s.sch_programa_id = p.prg_programa_id AND s.sch_sku = p.prg_sku
    WHERE p.prg_itens_op = '201055'
";

$result = $pdo->query($sql);
while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
    echo json_encode($row, JSON_UNESCAPED_UNICODE) . "\n";
}

echo "\n=== REALIZADO (OP 201055 no JSON CODI) ===\n";
$sql2 = "
    SELECT 
        JSON_EXTRACT(perf_dados_json, '$.ordemProducao') AS op,
        JSON_EXTRACT(perf_dados_json, '$.item.codItem') AS sku,
        JSON_EXTRACT(perf_dados_json, '$.item.nomeItem') AS descricao,
        cc.cal_data AS data_execucao,
        cr.cod_nome_recurso AS maquina
    FROM codi_performance cp
    LEFT JOIN codi_calendario cc ON cp.perf_item_codi = cc.cal_grandeza_codi
    LEFT JOIN codi_recursos cr ON cc.cal_recurso_codi_id = cr.cod_id
    WHERE JSON_EXTRACT(perf_dados_json, '$.ordemProducao') = '201055'
    LIMIT 10
";

$result2 = $pdo->query($sql2);
$count = 0;
while ($row = $result2->fetch(PDO::FETCH_ASSOC)) {
    echo json_encode($row, JSON_UNESCAPED_UNICODE) . "\n";
    $count++;
}

if ($count === 0) {
    echo "Nenhum registro realizado encontrado para OP 201055\n";
}

echo "\n=== RESUMO: OPs Planejadas vs Realizado ===\n";
$sql3 = "
    SELECT 
        p.prg_itens_op AS op,
        COUNT(DISTINCT s.sch_id) AS itens_planejados,
        COUNT(DISTINCT cp.perf_id) AS registros_codi
    FROM prg_itens p
    LEFT JOIN sch_linhas s ON s.sch_programa_id = p.prg_programa_id AND s.sch_sku = p.prg_sku
    LEFT JOIN codi_performance cp ON JSON_EXTRACT(cp.perf_dados_json, '$.ordemProducao') = p.prg_itens_op
    WHERE p.prg_itens_op IS NOT NULL
    GROUP BY p.prg_itens_op
    ORDER BY p.prg_itens_op
    LIMIT 20
";

$result3 = $pdo->query($sql3);
while ($row = $result3->fetch(PDO::FETCH_ASSOC)) {
    echo json_encode($row) . "\n";
}
