<?php
echo "🔍 Testando conexões ao banco de dados\n\n";

// Tentativas de conexão
$configs = [
    ['host' => '127.0.0.1', 'user' => 'root', 'pass' => '', 'name' => 'mysql'],
    ['host' => '127.0.0.1', 'user' => 'root', 'pass' => 'root', 'name' => 'mysql'],
    ['host' => '127.0.0.1', 'user' => 'root', 'pass' => '12345', 'name' => 'mysql'],
    ['host' => 'localhost', 'user' => 'root', 'pass' => '', 'name' => 'mysql'],
    ['host' => 'localhost', 'user' => 'root', 'pass' => 'root', 'name' => 'mysql'],
];

foreach ($configs as $i => $config) {
    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        $config['host'],
        '3306',
        $config['name']
    );
    
    try {
        $pdo = new PDO($dsn, $config['user'], $config['pass']);
        echo "✅ Conexão #{$i} bem-sucedida!\n";
        echo "   Host: {$config['host']}\n";
        echo "   User: {$config['user']}\n";
        echo "   Pass: " . (empty($config['pass']) ? "(vazio)" : "***") . "\n";
        
        // Listar bancos de dados
        $result = $pdo->query("SHOW DATABASES");
        $dbs = $result->fetchAll(\PDO::FETCH_COLUMN, 0);
        echo "   Bancos: " . implode(", ", $dbs) . "\n\n";
        
        exit(0);
    } catch (\Exception $e) {
        echo "❌ Conexão #{$i} falhou: " . $e->getMessage() . "\n";
    }
}

echo "\n❌ Nenhuma conexão bem-sucedida encontrada\n";
