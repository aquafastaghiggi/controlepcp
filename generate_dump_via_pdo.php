<?php
require 'src/bootstrap.php';

use App\Database\Connection;

try {
    $pdo = Connection::get();
    
    // Obter todas as tabelas
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "Gerando dump SQL das tabelas: " . implode(", ", $tables) . "\n\n";
    
    $timestamp = date('Ymd_His');
    $dumpFile = __DIR__ . "/dump_controlepcp_{$timestamp}.sql";
    
    $sql = "-- Dump do banco de dados controlepcp\n";
    $sql .= "-- Gerado em: " . date('Y-m-d H:i:s') . "\n\n";
    $sql .= "SET NAMES utf8mb4;\n";
    $sql .= "SET FOREIGN_KEY_CHECKS=0;\n\n";
    
    foreach ($tables as $table) {
        // Obter CREATE TABLE
        $createStmt = $pdo->query("SHOW CREATE TABLE `$table`");
        $createRow = $createStmt->fetch(PDO::FETCH_ASSOC);
        $sql .= $createRow['Create Table'] . ";\n\n";
        
        // Obter dados
        $dataStmt = $pdo->query("SELECT * FROM `$table`");
        $rows = $dataStmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (count($rows) > 0) {
            $columns = array_keys($rows[0]);
            $columnList = implode("`, `", $columns);
            
            foreach ($rows as $row) {
                $values = [];
                foreach ($row as $value) {
                    if ($value === null) {
                        $values[] = "NULL";
                    } else {
                        $values[] = "'" . str_replace("'", "''", $value) . "'";
                    }
                }
                $sql .= "INSERT INTO `$table` (`$columnList`) VALUES (" . implode(", ", $values) . ");\n";
            }
            $sql .= "\n";
        }
    }
    
    $sql .= "SET FOREIGN_KEY_CHECKS=1;\n";
    
    // Salvar em arquivo
    file_put_contents($dumpFile, $sql);
    
    $size = filesize($dumpFile);
    echo "✅ Dump gerado com sucesso!\n";
    echo "Arquivo: $dumpFile\n";
    echo "Tamanho: " . number_format($size / 1024 / 1024, 2) . " MB\n";
    echo "Linhas de SQL: " . substr_count($sql, "\n") . "\n";
    
} catch (Exception $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
}
?>
