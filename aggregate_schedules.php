<?php
$pdo = new PDO('mysql:host=127.0.0.1:3306;dbname=controlepcp_sandbox;charset=utf8mb4','root','k7m2y9u4');

echo "=== VERIFICANDO SCHEDULES DE OP 201055 (27/03-28/03) ===\n\n";

// 1. Buscar todos os schedules da OP 201055 entre 27/03-28/03
$sql = "
SELECT 
    p.prg_itens_op,
    p.prg_sku,
    p.prg_quantidade,
    s.sch_tipo,
    s.sch_data_inicio,
    SUM(CAST(s.sch_quantidade AS DECIMAL(10,2))) as sch_quantidade_total,
    COUNT(*) as sch_count
FROM prg_itens p
LEFT JOIN sch_linhas s ON p.prg_programa_id = s.sch_programa_id AND s.sch_sku = p.prg_sku
WHERE p.prg_itens_op = '201055'
  AND s.sch_data_inicio >= '2026-03-27'
  AND s.sch_data_inicio <= '2026-03-28'
GROUP BY p.prg_sku, s.sch_tipo
ORDER BY s.sch_data_inicio, s.sch_tipo
";

echo "Query:  Agregando schedules por tipo entre 27-28/03\n\n";
$result = $pdo->query($sql);
$rows = $result->fetchAll(PDO::FETCH_ASSOC);

echo "Resultados: " . count($rows) . "\n\n";

$total_por_tipo = [];
foreach ($rows as $row) {
    echo "Tipo: {$row['sch_tipo']} | Data: {$row['sch_data_inicio']} | Qtd: {$row['sch_quantidade_total']} | Count: {$row['sch_count']}\n";
    $tipo = $row['sch_tipo'];
    if (!isset($total_por_tipo[$tipo])) $total_por_tipo[$tipo] = 0;
    $total_por_tipo[$tipo] += $row['sch_quantidade_total'];
}

echo "\n=== TOTAIS POR TIPO ===\n";
foreach ($total_por_tipo as $tipo => $total) {
    echo "$tipo: $total\n";
}

echo "\n=== APENAS PRODUÇÃO (não setup) ===\n";
$sql = "
SELECT 
    SUM(CAST(s.sch_quantidade AS DECIMAL(10,2))) as total_producao,
    COUNT(*) as qtd_registros
FROM prg_itens p
LEFT JOIN sch_linhas s ON p.prg_programa_id = s.sch_programa_id AND s.sch_sku = p.prg_sku
WHERE p.prg_itens_op = '201055'
  AND s.sch_data_inicio >= '2026-03-27'
  AND s.sch_data_inicio <= '2026-03-28'
  AND s.sch_tipo = 'produção'
";

$result = $pdo->query($sql);
$row = $result->fetch(PDO::FETCH_ASSOC);
echo "Total produção (27-28/03): {$row['total_producao']} un ({$row['qtd_registros']} registros)\n";

echo "\n=== TODOS OS SCHEDULES DESSA OP NESSES DIAS ===\n";
$sql = "
SELECT 
    s.sch_id,
    s.sch_tipo,
    s.sch_data_inicio,
    s.sch_hora_inicio,
    s.sch_hora_fim,
    s.sch_quantidade,
    s.sch_duracao_minutos,
    s.sch_sku
FROM sch_linhas s
WHERE s.sch_programa_id IN (
    SELECT prg_programa_id FROM prg_itens WHERE prg_itens_op = '201055'
)
  AND s.sch_data_inicio >= '2026-03-27'
  AND s.sch_data_inicio <= '2026-03-28'
ORDER BY s.sch_data_inicio, s.sch_hora_inicio
";

$result = $pdo->query($sql);
$rows = $result->fetchAll(PDO::FETCH_ASSOC);
echo "Total de schedules: " . count($rows) . "\n\n";

$soma = 0;
foreach ($rows as $row) {
    echo "ID: {$row['sch_id']} | {$row['sch_tipo']} | {$row['sch_data_inicio']} {$row['sch_hora_inicio']}-{$row['sch_hora_fim']} | SKU: {$row['sch_sku']} | Qtd: {$row['sch_quantidade']} | Dur: {$row['sch_duracao_minutos']}min\n";
    if ($row['sch_tipo'] === 'produção') {
        $soma += (float)$row['sch_quantidade'];
    }
}

echo "\n=== SOMA MANUAL DE PRODUÇÃO ===\n";
echo "Total: $soma\n";
?>
