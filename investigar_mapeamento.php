<?php
$pdo = new PDO(
    'mysql:host=127.0.0.1:3306;dbname=controlepcp_sandbox;charset=utf8mb4',
    'root',
    'k7m2y9u4'
);

echo "=== TABELA: codi_mapeamento ===\n";
$result = $pdo->query("DESCRIBE codi_mapeamento");
echo "\nCampos:\n";
while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
    echo "  {$row['Field']} ({$row['Type']})\n";
}

echo "\nDados:\n";
$result = $pdo->query("SELECT * FROM codi_mapeamento LIMIT 5");
$rows = $result->fetchAll(PDO::FETCH_ASSOC);
if (count($rows) > 0) {
    echo json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
} else {
    echo "  (vazio)";
}

echo "\n\n=== TABELA: prd_produtos ===\n"; 
$result = $pdo->query("DESCRIBE prd_produtos");
echo "\nCampos:\n";
while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
    echo "  {$row['Field']}\n";
}

echo "\nAmostra:\n";
$result = $pdo->query("SELECT * FROM prd_produtos LIMIT 3");
$rows = $result->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $r) {
    echo "  ID: {$r['prd_id']} | Desc: " . substr($r['prd_descricao'] ?? '', 0, 40) . "\n";
}

echo "\n\n=== TABELA: prg_itens (itens das programações) ===\n";
$result = $pdo->query("DESCRIBE prg_itens");
echo "\nCampos:\n";
while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
    echo "  {$row['Field']}\n";
}

echo "\nAmostra (primeiro programa):\n";
$result = $pdo->query("SELECT * FROM prg_itens LIMIT 3");
$rows = $result->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $r) {
    echo "  Item: " . json_encode($r) . "\n";
}

echo "\n\n=== VERIFICAR: sch_linhas tem relação com prg_itens? ===\n";
$result = $pdo->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME='sch_linhas' AND COLUMN_NAME LIKE '%item%' OR COLUMN_NAME LIKE '%programa%'");
$cols = $result->fetchAll(PDO::FETCH_COLUMN);
if (count($cols) > 0) {
    echo "Colunas encontradas: " . implode(', ', $cols) . "\n";
    
    echo "\nAmostra com essas colunas:\n";
    $result = $pdo->query("SELECT sch_id, sch_sku, sch_descricao, sch_programa_id, " . implode(',', $cols) . " FROM sch_linhas LIMIT 2");
    while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
        echo json_encode($row, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    }
} else {
    echo "Nenhuma coluna de item/programa encontrada\n";
}
