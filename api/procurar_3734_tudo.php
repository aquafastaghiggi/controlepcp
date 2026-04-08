<?php
/**
 * Procurar 3734 em todos os lugares do banco
 */

$db = new PDO("mysql:host=127.0.0.1;dbname=controlepcp_sandbox;charset=utf8mb4", 'root', 'k7m2y9u4');

echo "=== PROCURANDO 3734 EM TUDO ===\n\n";

// 1. Procurar em sch_linhas
echo "1. Procurando em sch_linhas...\n";
$sql = "SELECT * FROM sch_linhas WHERE sch_quantidade = 3734 OR sch_quantidade LIKE '%3734%' LIMIT 10";
$rows = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
echo "   Encontrados: " . count($rows) . " registros\n\n";

if (count($rows) > 0) {
    foreach ($rows as $row) {
        echo "   - sch_id=" . $row['sch_id'] . ", prg_id=" . $row['sch_programa_id'] . ", qtde=" . $row['sch_quantidade'] . "\n";
    }
}

// 2. Procurar em prg_itens
echo "\n2. Procurando em prg_itens...\n";
$sql = "SELECT * FROM prg_itens WHERE prg_quantidade = 3734 OR prg_quantidade LIKE '%3734%' LIMIT 10";
$rows = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
echo "   Encontrados: " . count($rows) . " registros\n\n";

if (count($rows) > 0) {
    foreach ($rows as $row) {
        echo "   - prg_id=" . $row['prg_id'] . ", op=" . $row['prg_itens_op'] . ", qtde=" . $row['prg_quantidade'] . "\n";
    }
}

// 3. Procurar em exec_linhas (se existir)
echo "\n3. Procurando em exec_linhas (se tabela existir)...\n";
try {
    $sql = "SELECT * FROM exec_linhas WHERE exc_quantidade = 3734 OR exc_quantidade LIKE '%3734%' LIMIT 10";
    $rows = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    echo "   Encontrados: " . count($rows) . " registros\n\n";
} catch (\Exception $e) {
    echo "   Tabela não existe\n\n";
}

// 4. Try mais genérico - procurar substring 3734
echo "4. Procurando '3734' em sch_linhas por substring...\n";
$sql = "SELECT sch_id, sch_programa_id, sch_quantidade FROM sch_linhas WHERE CAST(sch_quantidade AS CHAR) LIKE '%3734%' LIMIT 20";
try {
    $rows = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    echo "   Encontrados: " . count($rows) . " registros\n";
    
    if (count($rows) > 0) {
        foreach ($rows as $row) {
            echo "   - sch_programa_id=" . $row['sch_programa_id'] . ", qtde=" . $row['sch_quantidade'] . "\n";
        }
    }
} catch (\Exception $e) {
    echo "   Erro: " . $e->getMessage() . "\n";
}

// 5. Mostrar todas as tabelas do banco
echo "\n\n=== LISTANDO TODAS AS TABELAS ===\n";
$sql = "SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = 'controlepcp_sandbox'";
$tables = $db->query($sql)->fetchAll(PDO::FETCH_COLUMN);
echo "Tabelas encontradas: " . count($tables) . "\n";
foreach ($tables as $table) {
    echo "  - $table\n";
}

?>
