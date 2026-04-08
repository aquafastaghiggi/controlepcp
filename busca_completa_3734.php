<?php
$pdo = new PDO('mysql:host=localhost;dbname=controlepcp_sandbox;charset=utf8mb4', 'root', 'k7m2y9u4');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

echo "=== BUSCA COMPLETA POR '3734' EM TODAS AS TABELAS ===\n\n";

// Obter todas as tabelas
$tables = $pdo->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA='controlepcp_sandbox'")->fetchAll(PDO::FETCH_COLUMN);

$encontrados = 0;

foreach ($tables as $table) {
    // Obter colunas da tabela
    $columns = $pdo->query("DESCRIBE {$table}")->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($columns as $col) {
        $colName = $col['Field'];
        $colType = $col['Type'];
        
        // Verificar se é TEXT, VARCHAR, INT, etc.
        try {
            $sql = "SELECT COUNT(*) FROM {$table} WHERE {$colName} LIKE '%3734%' OR {$colName} = 3734";
            $count = (int)$pdo->query($sql)->fetchColumn();
            
            if ($count > 0) {
                $encontrados += $count;
                echo "✓ [{$table}.{$colName}] ({$colType}): $count registro(s)\n";
                
                // Recuperar dados
                $sql = "SELECT * FROM {$table} WHERE {$colName} LIKE '%3734%' OR {$colName} = 3734 LIMIT 3";
                $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
                
                foreach ($rows as $row) {
                    $preview = substr(json_encode($row), 0, 120);
                    echo "  └─ " . str_replace("\n", "", $preview) . "...\n";
                }
            }
        } catch (Exception $e) {
            // Pode falhar para tipos de dados incompatíveis, ignorar
        }
    }
}

if ($encontrados === 0) {
    echo "Nenhum registro encontrado contendo '3734' no banco de dados.\n\n";
    
    // Fazer busca parcial: valores próximos
    echo "=== BUSCAS ALTERNATIVAS ===\n";
    echo "\nProc urbando valores numéricos próximos (3730-3740)...\n";
    
    $tabelas_importantes = ['codi_calendario', 'codi_performance', 'prg_programas', 'prg_itens', 'sch_linhas'];
    
    foreach ($tabelas_importantes as $table) {
        try {
            $columns = $pdo->query("DESCRIBE {$table}")->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($columns as $col) {
                if (strpos($col['Type'], 'int') !== false || strpos($col['Type'], 'decimal') !== false) {
                    try {
                        $sql = "SELECT {$col['Field']}, COUNT(*) as qty FROM {$table} 
                                WHERE {$col['Field']} >= 3730 AND {$col['Field']} <= 3740
                                GROUP BY {$col['Field']}";
                        $results = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
                        
                        foreach ($results as $r) {
                            if ($r['qty'] > 0) {
                                echo "  [{$table}.{$col['Field']}]: valor " . $r[$col['Field']] . " (" . $r['qty'] . " regs)\n";
                            }
                        }
                    } catch (Exception $e) {}
                }
            }
        } catch (Exception $e) {}
    }
} else {
    echo "\n✓ TOTAL ENCONTRADO: $encontrados registro(s) contendo '3734'\n";
}
