<?php
$pdo = new PDO(
    'mysql:host=127.0.0.1:3306;dbname=controlepcp_sandbox;charset=utf8mb4',
    'root',
    'k7m2y9u4'
);

echo "=== TABELA: codi_mapeamento ===\n";
$result = $pdo->query("DESCRIBE codi_mapeamento");
echo "Campos:\n";
while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
    echo "  {$row['Field']} ({$row['Type']})\n";
}

echo "\nDados (primeiros 10):\n";
$result = $pdo->query("SELECT * FROM codi_mapeamento LIMIT 10");
$rows = $result->fetchAll(PDO::FETCH_ASSOC);
if (count($rows) > 0) {
    foreach ($rows as $r) {
        echo json_encode($r) . "\n";
    }
    echo "\nTotal: " . count($rows) . " registros\n";
} else {
    echo "  (vazio!)\n";
}

echo "\n=== RELAÇÃO: prg_itens → sch_linhas ===\n";
$result = $pdo->query("
    SELECT p.prg_id_item, p.prg_sku, p.prg_quantidade, p.prg_itens_op,
           s.sch_id, s.sch_sku, s.sch_quantidade, s.sch_data_inicio
    FROM prg_itens p
    LEFT JOIN sch_linhas s ON s.sch_programa_id = p.prg_programa_id AND s.sch_sku = p.prg_sku
    LIMIT 5
");
$rows = $result->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $r) {
    echo "  Prog SKU {$r['prg_sku']} OP:{$r['prg_itens_op']} → Sch SKU {$r['sch_sku']} (ID:{$r['sch_id']})\n";
}

echo "\n=== RELAÇÃO: codi_calendario → Qual produto? ===\n";
$result = $pdo->query("SELECT cal_id, cal_dados_json FROM codi_calendario LIMIT 1");
if ($row = $result->fetch(PDO::FETCH_ASSOC)) {
    $json = json_decode($row['cal_dados_json'], true);
    echo "Dados JSON disponíveis no CODI:\n";
    foreach ($json as $k => $v) {
        if (is_array($v)) {
            echo "  $k: [array]\n";
        } else {
            echo "  $k: " . substr((string)$v, 0, 50) . "\n";
        }
    }
}
