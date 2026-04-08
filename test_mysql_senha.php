<?php
echo "🔍 Testando MYSQL com senha fornecida\n\n";

$configs = [
    ['host' => '127.0.0.1', 'user' => 'root', 'pass' => 'K7m2y9u4@', 'name' => 'controlepcp'],
    ['host' => '127.0.0.1', 'user' => 'root', 'pass' => 'k7m2y9u4@', 'name' => 'controlepcp'],
    ['host' => 'localhost', 'user' => 'root', 'pass' => 'K7m2y9u4@', 'name' => 'controlepcp'],
];

foreach ($configs as $i => $config) {
    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $config['host'], '3306', $config['name']);
    
    try {
        $pdo = new PDO($dsn, $config['user'], $config['pass']);
        echo "✅ Conexão bem-sucedida!\n";
        echo "   Host: {$config['host']}\n";
        echo "   DB: {$config['name']}\n";
        echo "   User: {$config['user']}\n";
        
        // Listar tabelas existentes
        $result = $pdo->query("SHOW TABLES");
        $tables = $result->fetchAll(\PDO::FETCH_COLUMN, 0);
        echo "   Tabelas: " . count($tables) . "\n";
        
        // Verificar se tabelas CODI já existem
        $codiTables = array_filter($tables, fn($t) => str_starts_with($t, 'codi_'));
        echo "   Tabelas CODI: " . count($codiTables) . "\n";
        
        exit(0);
    } catch (\Exception $e) {
        echo "❌ Tentativa #" . ($i + 1) . " falhou\n";
    }
}

echo "\n❌ Todas as tentativas falharam\n";
