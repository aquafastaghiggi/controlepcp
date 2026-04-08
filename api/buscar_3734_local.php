<?php
/**
 * Buscar total realizado para OP 201055 NO BANCO LOCAL (sem limite de data)
 */

$db = new PDO("mysql:host=127.0.0.1;dbname=controlepcp_sandbox;charset=utf8mb4", 'root', 'k7m2y9u4');

echo "=== BUSCANDO REALIZADO NO BANCO LOCAL (SEM LIMITE DE DATA) ===\n\n";

$op = '201055';

// Semelhantes à query do api_integrated.php, MAS sem límite de data
$sql = "
    SELECT 
        p.prg_sku,
        SUM(CAST(s.sch_quantidade AS DECIMAL(10,2))) as quantidade_realizada,
        COUNT(*) as total_schedules,
        MIN(DATE(s.sch_data_inicio)) as data_inicio,
        MAX(DATE(s.sch_data_inicio)) as data_fim
    FROM sch_linhas s
    INNER JOIN prg_itens p ON s.sch_programa_id = p.prg_programa_id
    WHERE p.prg_itens_op = :op
      AND s.sch_quantidade IS NOT NULL
      AND s.sch_quantidade != ''
      AND CAST(s.sch_quantidade AS DECIMAL) > 0
    GROUP BY p.prg_sku
";

$stmt = $db->prepare($sql);
$stmt->execute(['op' => $op]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Registros encontrados: " . count($rows) . "\n\n";

$total_realizado = 0;

if (count($rows) > 0) {
    echo "SKU | Quantidade Realizada | Total Schedules | Data Início | Data Fim\n";
    echo str_repeat("-", 100) . "\n";
    
    foreach ($rows as $row) {
        $qtde = floatval($row['quantidade_realizada']);
        $total_realizado += $qtde;
        
        printf("%-12s | %20s | %15s | %11s | %11s\n",
            $row['prg_sku'],
            $qtde,
            $row['total_schedules'],
            $row['data_inicio'],
            $row['data_fim']
        );
    }
    
    echo str_repeat("-", 100) . "\n";
    echo "\n✅ TOTAL REALIZADO: $total_realizado\n";
    
    if ($total_realizado == 3734) {
        echo "✅✅✅ ENCONTRADO EXATAMENTE 3734!\n";
    } else {
        echo "Diferença de " . (3734 - $total_realizado) . " unidades\n";
    }
} else {
    echo "❌ Nenhum registro encontrado para OP $op\n";
}

// Agora vamos procurar em TODAS as OPs para encontrar quem tem 3734
echo "\n\n=== PROCURANDO 3734 EM TODAS AS OPS ===\n\n";

$sql_todas = "
    SELECT 
        p.prg_itens_op as op,
        SUM(CAST(s.sch_quantidade AS DECIMAL(10,2))) as quantidade_realizada
    FROM sch_linhas s
    INNER JOIN prg_itens p ON s.sch_programa_id = p.prg_programa_id
    WHERE s.sch_quantidade IS NOT NULL
      AND s.sch_quantidade != ''
      AND CAST(s.sch_quantidade AS DECIMAL) > 0
    GROUP BY p.prg_itens_op
    ORDER BY quantidade_realizada DESC
    LIMIT 50
";

$stmt = $db->query($sql_todas);
$all_ops = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Top 50 OPs por quantidade realizada:\n";
echo "OP | Realizado\n";
echo str_repeat("-", 30) . "\n";

foreach ($all_ops as $row) {
    $op_num = $row['op'];
    $qtde = floatval($row['quantidade_realizada']);
    printf("%-10s | %10.2f\n", $op_num, $qtde);
    
    if ($qtde == 3734) {
        echo "    ^^^ ENCONTRADO 3734!\n";
    }
}

?>
