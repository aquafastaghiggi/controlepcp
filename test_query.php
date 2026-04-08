<?php
$pdo = new PDO(
    'mysql:host=127.0.0.1:3306;dbname=controlepcp_sandbox;charset=utf8mb4',
    'root',
    'k7m2y9u4'
);

$sql = "
    SELECT 
        'Planejado' AS tipo,
        s.sch_id,
        s.sch_sku AS sku,
        s.sch_descricao AS descricao,
        s.sch_quantidade AS quantidade_planejada,
        s.sch_duracao_minutos AS duracao_minutos,
        s.sch_data_inicio AS data_inicio,
        COALESCE(p.prg_itens_op, 'SEM OP') AS op_planejada
    FROM sch_linhas s
    LEFT JOIN prg_itens p ON s.sch_programa_id = p.prg_programa_id AND s.sch_sku = p.prg_sku
    WHERE s.sch_quantidade IS NOT NULL AND s.sch_quantidade > 0
    LIMIT 5
";

$result = $pdo->query($sql);
while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
    echo json_encode($row) . "\n";
}
