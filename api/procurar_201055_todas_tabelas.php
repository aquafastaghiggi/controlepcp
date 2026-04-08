<?php
// Procurar 201055 em todas as tabelas

try {
    $pdo = new PDO('mysql:host=localhost;dbname=controlepcp_sandbox', 'root', 'k7m2y9u4');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "=== PROCURANDO OP 201055 EM TODAS AS TABELAS ===\n\n";
    
    // Listar todas as tabelas
    $stmt = $pdo->query("SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = 'controlepcp_sandbox'");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "Tabelas encontradas: " . count($tables) . "\n";
    echo "Tabelas: " . implode(', ', $tables) . "\n\n";
    
    $encontrado = false;
    
    foreach ($tables as $table) {
        // Procurar por colunas VARCHAR que contenham "201055"
        $stmt = $pdo->query("SHOW COLUMNS FROM " . $table);
        $cols = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($cols as $col) {
            if (stripos($col['Type'], 'varchar') !== false || stripos($col['Type'], 'char') !== false || stripos($col['Type'], 'text') !== false) {
                try {
                    $search_stmt = $pdo->prepare("SELECT * FROM " . $table . " WHERE `" . $col['Field'] . "` LIKE '%201055%' LIMIT 5");
                    $search_stmt->execute();
                    $results = $search_stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    if (count($results) > 0) {
                        echo "✓ ENCONTRADO em $table." . $col['Field'] . ":\n";
                        echo json_encode($results[0], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
                        $encontrado = true;
                    }
                } catch (Exception $e) {
                    // Silenciar erros de tabelas problemáticas
                }
            }
        }
    }
    
    if (!$encontrado) {
        echo "⚠ OP 201055 NÃO ENCONTRADA em nenhuma tabela!\n\n";
        echo "Procurando por código interno 23599...\n\n";
        
        foreach ($tables as $table) {
            $stmt = $pdo->query("SHOW COLUMNS FROM " . $table);
            $cols = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($cols as $col) {
                if (in_array($col['Type'], ['int(11)', 'bigint(20)', 'int', 'bigint'])) {
                    try {
                        $search_stmt = $pdo->prepare("SELECT * FROM " . $table . " WHERE `" . $col['Field'] . "` = 23599 LIMIT 3");
                        $search_stmt->execute();
                        $results = $search_stmt->fetchAll(PDO::FETCH_ASSOC);
                        
                        if (count($results) > 0) {
                            echo "✓ ENCONTRADO em $table." . $col['Field'] . " (valor=23599):\n";
                            foreach ($results as $res) {
                                echo json_encode($res, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
                            }
                            echo "\n";
                        }
                    } catch (Exception $e) {
                        
                    }
                }
            }
        }
    }
    
} catch (Exception $e) {
    echo "ERRO: " . $e->getMessage() . "\n";
}
?>
