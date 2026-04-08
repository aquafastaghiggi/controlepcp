<?php
$pdo = new PDO('mysql:host=localhost;dbname=controlepcp_sandbox;charset=utf8mb4', 'root', 'k7m2y9u4');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

echo "=== REGISTRO CONTENDO '3734' ===\n\n";

$sql = "SELECT * FROM mat_matriz_setup WHERE mat_id LIKE '%3734%'";
$rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

foreach ($rows as $row) {
    echo "Tabela: mat_matriz_setup (Matriz de Setup)\n";
    echo "mat_id: " . $row['mat_id'] . "\n";
    echo "mat_linha_id: " . $row['mat_linha_id'] . "\n";
    echo "mat_sku_origem: " . $row['mat_sku_origem'] . "\n";
    echo "mat_sku_destino: " . $row['mat_sku_destino'] . "\n";
    echo "mat_duracao_minutos: " . $row['mat_duracao_minutos'] . "\n";
    echo "mat_criado_em: " . $row['mat_criado_em'] . "\n";
    echo "mat_atualizado_em: " . $row['mat_atualizado_em'] . "\n";
}

// Se houver referências em outras tabelas
echo "\n=== VERIFICANDO REFERÊNCIAS ===\n";

// Procura por op 201055 (se for o caso)
$sql = "SELECT prg_id, prg_numero_op, prg_status FROM prg_programas WHERE prg_numero_op = '201055' OR prg_numero_op LIKE '%055%'";
$rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
if (count($rows) > 0) {
    echo "\nOPs encontradas:\n";
    foreach ($rows as $row) {
        echo "  - OP: " . $row['prg_numero_op'] . ", ID: " . $row['prg_id'] . ", Status: " . $row['prg_status'] . "\n";
    }
}

// Resumo final
echo "\n=== RESUMO DE ESTRUTURAS ===\n\n";

echo "codi_calendario columns:\n";
$result = $pdo->query('DESCRIBE codi_calendario');
$cols = [];
foreach ($result as $col) {
    $cols[] = $col['Field'] . ' [' . $col['Type'] . ']';
}
echo implode(', ', $cols) . "\n\n";

echo "codi_performance columns:\n";
$result = $pdo->query('DESCRIBE codi_performance');
$cols = [];
foreach ($result as $col) {
    $cols[] = $col['Field'] . ' [' . $col['Type'] . ']';
}
echo implode(', ', $cols) . "\n";
