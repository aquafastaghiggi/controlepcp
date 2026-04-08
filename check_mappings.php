<?php
$pdo = new PDO(
    'mysql:host=127.0.0.1:3306;dbname=controlepcp_sandbox;charset=utf8mb4',
    'root',
    'k7m2y9u4'
);

echo "=== TABELAS DE MAPEAMENTO DISPONÍVEIS ===\n";
$result = $pdo->query('SHOW TABLES');
while ($row = $result->fetch(PDO::FETCH_NUM)) {
    $tableName = $row[0];
    if (stripos($tableName, 'mape') !== false || 
        stripos($tableName, 'sku') !== false || 
        stripos($tableName, 'item') !== false ||
        stripos($tableName, 'produto') !== false) {
        echo "- $tableName\n";
    }
}

echo "\n=== VERIFICAR TABELA codi_mapeamento ===\n";
$sql = "SELECT * FROM codi_mapeamento LIMIT 5";
try {
    $result = $pdo->query($sql);
    $rows = $result->fetchAll(PDO::FETCH_ASSOC);
    if (count($rows) > 0) {
        echo json_encode($rows, JSON_PRETTY_PRINT) . "\n";
    } else {
        echo "Tabela existe mas está vazia.\n";
    }
} catch (Exception $e) {
    echo "Erro: " . $e->getMessage() . "\n";
}

echo "\n=== VERIFICAR ESTRUTURA DE prg_itens ===\n";
$sql = "DESCRIBE prg_itens";
$result = $pdo->query($sql);
while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
    echo $row['Field'] . " (" . $row['Type'] . ")\n";
}

echo "\n=== DADOS ATUAIS DE prg_itens ===\n";
$sql = "SELECT * FROM prg_itens LIMIT 3";
$result = $pdo->query($sql);
while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
    echo json_encode($row) . "\n";
}
