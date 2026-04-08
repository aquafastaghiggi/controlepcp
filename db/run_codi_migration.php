<?php
/**
 * Script para executar migrations CODI no banco de dados
 * 
 * FASE 6: Criar tabelas de sincronização CODI
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');

echo "🔄 EXECUTANDO MIGRATIONS CODI\n";
echo str_repeat("=", 80) . "\n\n";

try {
    // Conectar ao banco de dados
    require_once __DIR__ . '/../src/Database/Connection.php';
    
    $pdo = \App\Database\Connection::get();
    echo "✅ Conectado ao banco de dados\n\n";
    
    // Ler arquivo de migration
    $migrationFile = __DIR__ . '/migration_codi_sync.sql';
    
    if (!file_exists($migrationFile)) {
        throw new \Exception("Arquivo de migration não encontrado: $migrationFile");
    }
    
    $sql = file_get_contents($migrationFile);
    
    // Dividir em múltiplos statements
    $statements = array_filter(
        array_map('trim', explode(';', $sql)),
        fn($s) => !empty($s) && !str_starts_with($s, '--')
    );
    
    if (empty($statements)) {
        throw new \Exception("Nenhum statement SQL válido encontrado");
    }
    
    echo "📋 Executando " . count($statements) . " statements SQL...\n\n";
    
    // Executar cada statement
    $successCount = 0;
    $failCount = 0;
    
    foreach ($statements as $index => $statement) {
        // Ignorar comentários
        $statement = preg_replace('/^--[^\n]*/m', '', $statement);
        $statement = trim($statement);
        
        if (empty($statement)) {
            continue;
        }
        
        // Extrair nome da tabela para logs
        preg_match('/CREATE TABLE IF NOT EXISTS (\w+)/i', $statement, $matches);
        $tableName = $matches[1] ?? 'unknown';
        
        try {
            $pdo->exec($statement);
            echo "  ✅ $tableName\n";
            $successCount++;
        } catch (\Exception $e) {
            echo "  ❌ $tableName - " . $e->getMessage() . "\n";
            $failCount++;
        }
    }
    
    echo "\n" . str_repeat("=", 80) . "\n";
    echo "✅ Migrations executadas!\n";
    echo "   Sucesso: $successCount\n";
    echo "   Falhas: $failCount\n\n";
    
    // Listar tabelas criadas
    echo "📊 Tabelas CODI criadas:\n";
    $result = $pdo->query("SHOW TABLES LIKE 'codi_%'");
    
    if ($result) {
        $tables = $result->fetchAll(\PDO::FETCH_COLUMN, 0);
        if (count($tables) > 0) {
            foreach ($tables as $table) {
                echo "   • " . $table . "\n";
            }
        } else {
            echo "   (Nenhuma tabela CODI encontrada)\n";
        }
    } else {
        echo "   (Erro ao listar tabelas)\n";
    }
    
} catch (\Exception $e) {
    echo "❌ ERRO: " . $e->getMessage() . "\n";
    die(1);
}
