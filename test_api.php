<?php
require __DIR__ . '/../controlepcp/src/bootstrap.php';

$pdo = new PDO(
    'mysql:host=localhost;dbname=controlepcp_sandbox',
    'root',
    'k7m2y9u4'
);

// Teste: buscar programações
$stmt = $pdo->query("
    SELECT DISTINCT 
        pp.prg_id,
        pp.prg_linha_id,
        COUNT(pi.prg_id_item) as total_itens,
        pp.prg_numero_op,
        pp.prg_status
    FROM prg_programas pp
    LEFT JOIN prg_itens pi ON pp.prg_id = pi.prg_programa_id
    GROUP BY pp.prg_id, pp.prg_linha_id, pp.prg_numero_op, pp.prg_status
    ORDER BY pp.prg_id DESC
    LIMIT 10
");

$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "✅ Programações encontradas:\n";
echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";

// Teste: filtrar por primeira programação
if ($data) {
    $first_prg = $data[0]['prg_id'];
    echo "\n✅ Testando filtro para programa $first_prg:\n";
    
    $stmt = $pdo->prepare("
        SELECT 
            SUM(prg_quantidade) as total_planejado,
            COUNT(DISTINCT prg_itens_op) as ops_previsto
        FROM prg_itens
        WHERE prg_programa_id = ?
        AND prg_itens_op IS NOT NULL 
        AND prg_itens_op != ''
    ");
    $stmt->execute([$first_prg]);
    $prev = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "Previsto:\n";
    echo "  Total: " . $prev['total_planejado'] . "\n";
    echo "  OPs: " . $prev['ops_previsto'] . "\n";
}
