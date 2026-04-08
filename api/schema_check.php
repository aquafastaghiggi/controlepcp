<?php
// Verificar estrutura das tabelas principais

try {
    $pdo = new PDO('mysql:host=localhost;dbname=controlepcp_sandbox', 'root', 'k7m2y9u4');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "=== ESTRUTURA DO BANCO DE DADOS ===\n\n";
    
    $tables = ['prg_programas', 'prg_itens', 'codi_sincronizacao', 'sch_linhas', 'exec_linhas'];
    
    foreach ($tables as $table) {
        echo "Tabela: $table\n";
        $stmt = $pdo->query("SHOW COLUMNS FROM " . $table);
        $cols = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($cols as $col) {
            echo "  - {$col['Field']} ({$col['Type']}) - {$col['Null']} - Key: {$col['Key']}\n";
        }
        echo "\n";
    }
    
} catch (Exception $e) {
    echo "ERRO: " . $e->getMessage() . "\n";
}
?>
