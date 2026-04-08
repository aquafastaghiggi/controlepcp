<?php
$pdo = new PDO('mysql:host=127.0.0.1:3306;dbname=controlepcp_sandbox;charset=utf8mb4','root','k7m2y9u4');

echo "=== PROCURANDO '3734' E '201055' EM TODAS AS TABELAS ===\n\n";

// Get all tables
$sql = "SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA='controlepcp_sandbox'";
$result = $pdo->query($sql);
$tables = $result->fetchAll(PDO::FETCH_COLUMN);

echo "Total de tabelas: " . count($tables) . "\n\n";

foreach ($tables as $table) {
    // Get columns
    $sql = "DESCRIBE $table";
    $result = $pdo->query($sql);
    $columns = $result->fetchAll(PDO::FETCH_ASSOC);
    
    // For each text/numeric column, search
    foreach ($columns as $col) {
        $col_name = $col['Field'];
        $col_type = $col['Type'];
        
        // Skip binary/blob columns
        if (strpos($col_type, 'blob') !== false || strpos($col_type, 'binary') !== false) {
            continue;
        }
        
        // Try to find 3734
        try {
            $sql = "SELECT COUNT(*) as cnt FROM `$table` WHERE CAST(`$col_name` AS CHAR) LIKE '%3734%' OR CAST(`$col_name` AS CHAR) LIKE '%201055%'";
            $result = $pdo->query($sql);
            $row = $result->fetch(PDO::FETCH_ASSOC);
            
            if ($row['cnt'] > 0) {
                echo "✅ ENCONTRADO em $table.$col_name ({$col_type}) - {$row['cnt']} resultados\n";
                
                // Show data
                $sql = "SELECT * FROM `$table` WHERE CAST(`$col_name` AS CHAR) LIKE '%3734%' OR CAST(`$col_name` AS CHAR) LIKE '%201055%' LIMIT 3";
                $result = $pdo->query($sql);
                $rows = $result->fetchAll(PDO::FETCH_ASSOC);
                
                foreach ($rows as $data_row) {
                    echo "  " . json_encode($data_row, JSON_UNESCAPED_UNICODE) . "\n";
                }
                echo "\n";
            }
        } catch (Exception $e) {
            // Ignore errors
        }
    }
}
?>
