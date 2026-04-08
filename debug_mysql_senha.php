<?php
echo "🔍 Testando MYSQL com validação de senhas\n\n";

// Tentar diferentes variações da senha
$senhas = [
    'K7m2y9u4@',
    'k7m2y9u4@',
    'K7m2y9u4',
    'k7m2y9u4',
    '@K7m2y9u4',
    '@k7m2y9u4'
];

foreach ($senhas as $idx => $pass) {
    $dsn = 'mysql:host=127.0.0.1;port=3306;dbname=controlepcp;charset=utf8mb4';
    
    try {
        $pdo = new PDO($dsn, 'root', $pass);
        echo "✅ SUCESSO COM SENHA: $pass\n\n";
        
        // Listar tabelas
        $result = $pdo->query("SHOW TABLES LIKE 'codi_%'");
        $tables = $result->fetchAll(\PDO::FETCH_COLUMN, 0);
        echo "   Tabelas CODI existentes: " . count($tables) . "\n";
        foreach ($tables as $t) {
            echo "     • $t\n";
        }
        
        // Contar registros
        if (count($tables) > 0) {
            foreach ($tables as $table) {
                $cnt = $pdo->query("SELECT COUNT(*) FROM $table")->fetchColumn();
                echo "     → $table: $cnt registros\n";
            }
        }
        
        exit(0);
    } catch (\PDOException $e) {
        echo "❌ Tentativa " . ($idx + 1) . ": $pass\n";
        echo "   Erro: Access denied\n";
    }
}

echo "\n❌ Nenhuma senha funcionou\n";
echo "Por favor, forneça a senha correta do MySQL root.\n";
