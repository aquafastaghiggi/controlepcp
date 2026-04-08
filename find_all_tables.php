<?php
$pdo = new PDO(
    'mysql:host=127.0.0.1:3306;dbname=controlepcp_sandbox;charset=utf8mb4',
    'root',
    'k7m2y9u4'
);

echo "=== TODAS AS TABELAS ===\n";
$result = $pdo->query('SHOW TABLES');
$tables = [];
while ($row = $result->fetch(PDO::FETCH_NUM)) {
    $tables[] = $row[0];
    echo $row[0] . "\n";
}

echo "\n=== PROCURAR TABELAS COM 'PRD' ===\n";
foreach ($tables as $table) {
    if (stripos($table, 'prd') !== false) {
        echo "Encontrada: $table\n";
    }
}

echo "\n=== VERIFICAR prd_itens SE EXISTIR ===\n";
if (in_array('prd_itens', $tables)) {
    echo "Estrutura de prd_itens:\n";
    $result = $pdo->query('DESCRIBE prd_itens');
    while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
        echo "- " . $row['Field'] . "\n";
    }
    
    echo "\nPrimeiros registros de prd_itens:\n";
    $result = $pdo->query('SELECT * FROM prd_itens LIMIT 3');
    while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
        echo json_encode($row) . "\n";
    }
}

echo "\n=== TENTAR ENCONTRAR MAPEAMENTO DE QUALQUER FORMA ===\n";
foreach ($tables as $table) {
    $result = $pdo->query("DESCRIBE $table");
    $columns = [];
    while ($col = $result->fetch(PDO::FETCH_ASSOC)) {
        $columns[] = $col['Field'];
    }
    
    if ((in_array('prd_sku', $columns) || in_array('sku', $columns)) && 
        (in_array('prd_codigo_codi', $columns) || in_array('codigo_codi', $columns))) {
        echo "ACHEI MAPEAMENTO: $table\n";
        print_r($columns);
    }
}
