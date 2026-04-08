<?php
// Buscar informações de finalização da OP 201055 no banco local

try {
    $pdo = new PDO('mysql:host=localhost;dbname=controlepcp_sandbox', 'root', 'k7m2y9u4');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "=== PROCURANDO DADOS DE FINALIZAÇÃO OP 201055 ===\n\n";
    
    // 1. Buscar na tabela prg_programas
    echo "1. Dados em prg_programas (programa_codigo=23599):\n";
    $stmt = $pdo->prepare("SELECT * FROM prg_programas WHERE programa_codigo = 23599");
    $stmt->execute();
    $prog = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($prog, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
    
    // 2. Campos de status/data_fim em prg_itens
    echo "2. Itens associados (prg_codigo=23599):\n";
    $stmt = $pdo->prepare("SELECT * FROM prg_itens WHERE prg_codigo = 23599");
    $stmt->execute();
    $itens = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "Total: " . count($itens) . " itens\n";
    if (count($itens) > 0) {
        echo json_encode(array_slice($itens, 0, 1), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    }
    echo "\n";
    
    // 3. Buscar em codi_sincronizacao por codigo_ordem_codi = 23599
    echo "3. Dados em codi_sincronizacao:\n";
    $stmt = $pdo->prepare("SELECT * FROM codi_sincronizacao WHERE codigo_ordem_codi = 23599 LIMIT 10");
    $stmt->execute();
    $codi = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "Total encontrado: " . count($codi) . "\n";
    if (count($codi) > 0) {
        echo json_encode(array_slice($codi, 0, 2), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    }
    echo "\n";
    
    // 4. Buscar em exec_* tables
    echo "4. Execuções registradas:\n";
    $tables = ['exec_linhas', 'exec_operacoes', 'exec_produtos'];
    foreach ($tables as $table) {
        $stmt = $pdo->query("SHOW COLUMNS FROM " . $table);
        $cols = $stmt->fetchAll(PDO::FETCH_COLUMN);
        echo "Tabela $table, colunas: " . implode(', ', array_slice($cols, 0, 8)) . "...\n";
        
        // Tentar buscar por programa
        if (in_array('programa_codigo', $cols)) {
            $stmt = $pdo->prepare("SELECT * FROM " . $table . " WHERE programa_codigo = 23599 LIMIT 5");
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo "  Registros encontrados: " . count($rows) . "\n";
            if (count($rows) > 0) {
                echo "  Primeiros campos: " . json_encode(array_slice((array)$rows[0], 0, 5)) . "\n";
            }
        }
    }
    echo "\n";
    
    // 5. Procurar por colunas de status em todas as tabelas principais
    echo "5. Procurando por colunas de DATA/STATUS:\n";
    $hovedTables = ['prg_programas', 'prg_itens', 'codi_sincronizacao', 'exec_linhas', 'exec_operacoes'];
    foreach ($hovedTables as $table) {
        $stmt = $pdo->query("SHOW COLUMNS FROM " . $table);
        $cols = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $statusCols = [];
        foreach ($cols as $col) {
            $colName = strtolower($col['Field']);
            if (strpos($colName, 'data') !== false || 
                strpos($colName, 'status') !== false ||
                strpos($colName, 'final') !== false ||
                strpos($colName, 'fim') !== false ||
                strpos($colName, 'conclus') !== false) {
                $statusCols[] = $col['Field'];
            }
        }
        if (count($statusCols) > 0) {
            echo "  $table: " . implode(', ', $statusCols) . "\n";
        }
    }
    
} catch (Exception $e) {
    echo "ERRO: " . $e->getMessage() . "\n";
}
?>
