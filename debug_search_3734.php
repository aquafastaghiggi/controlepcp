<?php
$pdo = new PDO('mysql:host=localhost;dbname=controlepcp_sandbox;charset=utf8mb4', 'root', 'k7m2y9u4');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

echo "=== COLUNAS: codi_calendario ===\n";
$result = $pdo->query('DESCRIBE codi_calendario');
foreach ($result as $col) {
    echo $col['Field'] . ' [' . $col['Type'] . "]\n";
}

echo "\n=== COLUNAS: codi_performance ===\n";
$result = $pdo->query('DESCRIBE codi_performance');
foreach ($result as $col) {
    echo $col['Field'] . ' [' . $col['Type'] . "]\n";
}

echo "\n=== BUSCANDO REGISTROS COM '3734' ===\n";
$tables = ['codi_calendario', 'codi_performance', 'prg_programas', 'prg_itens', 'prd_produtos', 'sch_linhas'];

foreach ($tables as $table) {
    try {
        $columns = $pdo->query('DESCRIBE ' . $table)->fetchAll(PDO::FETCH_ASSOC);
        foreach ($columns as $col) {
            $result = $pdo->query('SELECT * FROM ' . $table . ' WHERE ' . $col['Field'] . ' LIKE "%3734%"');
            $rows = $result->fetchAll(PDO::FETCH_ASSOC);
            if (count($rows) > 0) {
                echo "\n✓ Encontrado em [$table.$col[Field]]: " . count($rows) . " registro(s)\n";
                echo json_encode($rows[0], JSON_UNESCAPED_UNICODE) . "\n";
            }
        }
    } catch (Exception $e) {
        // Tabela não existe, ignorar
    }
}
