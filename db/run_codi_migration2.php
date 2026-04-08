<?php
/**
 * Script para executar migrations CODI no banco de dados
 * Versão melhorada que lida melhor com comentários
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once __DIR__ . '/../src/Database/Connection.php';

echo "🔄 EXECUTANDO MIGRATIONS CODI\n";
echo str_repeat("=", 80) . "\n\n";

try {
    $pdo = \App\Database\Connection::get();
    echo "✅ Conectado ao banco de dados\n\n";
    
    // Ler arquivo de migration
    $migrationFile = __DIR__ . '/migration_codi_sync.sql';
    
    if (!file_exists($migrationFile)) {
        throw new \Exception("Arquivo de migration não encontrado: $migrationFile");
    }
    
    $sqlContent = file_get_contents($migrationFile);
    
    // Remover comentários -- quando estão no início ou após whitespace
    $sqlContent = preg_replace('/^--[^\n]*\n/m', '', $sqlContent);
    $sqlContent = preg_replace('/\/\*[\s\S]*?\*\//m', '', $sqlContent);
    
    // Dividir por ponto-e-vírgula
    $statements = array_filter(
        array_map('trim', explode(';', $sqlContent)),
        fn($s) => !empty($s)
    );
    
    if (empty($statements)) {
        throw new \Exception("Nenhum statement SQL válido encontrado no arquivo");
    }
    
    echo "📋 Encontrados " . count($statements) . " statements SQL\n\n";
    
    $successCount = 0;
    $failCount = 0;
    
    foreach ($statements as $index => $statement) {
        $statement = trim($statement);
        
        if (empty($statement)) {
            continue;
        }
        
        // Extrair nome da tabela
        preg_match('/CREATE TABLE IF NOT EXISTS (\w+)/i', $statement, $matches);
        $tableName = $matches[1] ?? ('Statement ' . ($index + 1));
        
        try {
            $pdo->exec($statement);
            echo "  ✅ $tableName\n";
            $successCount++;
        } catch (\Exception $e) {
            echo "  ❌ $tableName\n";
            echo "     → " . substr($e->getMessage(), 0, 80) . "\n";
            $failCount++;
        }
    }
    
    echo "\n" . str_repeat("=", 80) . "\n";
    echo "✅ Migrations concluídas!\n";
    echo "   ✓ Sucesso: $successCount\n";
    echo "   ✗ Falhas: $failCount\n\n";
    
    // Listar tabelas criadas
    echo "📊 Tabelas CODI no banco:\n";
    $result = $pdo->query("SHOW TABLES LIKE 'codi_%'");
    $tables = $result->fetchAll(\PDO::FETCH_COLUMN, 0);
    
    if (count($tables) > 0) {
        foreach ($tables as $table) {
            echo "   • $table\n";
        }
    } else {
        echo "   (Nenhuma tabela CODI encontrada)\n";
    }
    
} catch (\Exception $e) {
    echo "❌ ERRO: " . $e->getMessage() . "\n";
    if (isset($_GET['debug'])) {
        echo "\n" . $e->getTraceAsString();
    }
    die(1);
}
