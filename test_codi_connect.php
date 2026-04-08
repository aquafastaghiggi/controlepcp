<?php
/**
 * Teste CODI - Tentando acesso HTTP REST
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');

echo "🔍 Explorando conexão CODI\n";
echo "=" . str_repeat("=", 80) . "\n\n";

// Informações fornecidas
$postgres_host = '190.15.43.23';
$postgres_port = '5001';
$postgres_user = 'Postgres';
$postgres_pass = 'data#3789!';

echo "📌 Credenciais PostgreSQL:\n";
echo "  Host: $postgres_host:$postgres_port\n";
echo "  User: $postgres_user\n";
echo "  Pass: ****\n\n";

// Tentar conexão PostgreSQL via PDO
echo "1️⃣ Tentando PDO PostgreSQL...\n";
try {
    $dsn = "pgsql:host=$postgres_host;port=$postgres_port;user=$postgres_user;password=$postgres_pass;dbname=postgres";
    $pdo = new PDO($dsn);
    echo "   ✅ Conectado via PDO!\n\n";
    
    // Listar databases
    $stmt = $pdo->query("SELECT datname FROM pg_database WHERE datistemplate = false ORDER BY datname LIMIT 20;");
    echo "   📋 Databases disponíveis:\n";
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "      - {$row['datname']}\n";
    }
    
} catch (Exception $e) {
    echo "   ❌ PDO PostgreSQL falhou: " . $e->getMessage() . "\n\n";
}

// Tentar API REST CODI em diferentes portas/paths
echo "\n2️⃣ Procurando API REST CODI...\n";

$urls_to_try = [
    "http://190.15.43.23:8080",
    "http://190.15.43.23:8081",
    "http://190.15.43.23:5001",
    "http://190.15.43.23:8080/codi",
    "http://190.15.43.23:8080/codi/api",
    "http://190.15.43.23:8080/action/ger/webservice/rest",
];

foreach ($urls_to_try as $url) {
    echo "   Testando: $url ... ";
    try {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if ($httpCode && $httpCode != 0) {
            echo "HTTP $httpCode\n";
            if ($httpCode >= 200 && $httpCode < 500) {
                echo "      ✅ RESPONDENDO (resposta: " . strlen($response) . " bytes)\n";
            }
        } else {
            echo "❌\n";
        }
        curl_close($ch);
    } catch (Exception $e) {
        echo "❌\n";
    }
}

echo "\n" . str_repeat("=", 80) . "\n";
echo "\n💡 PRÓXIMAS AÇÕES:\n";
echo "   1. Confirmar qual database PostgreSQL usar\n";
echo "   2. Confirmar URL da API REST CODI (se houver)\n";
echo "   3. Confirmar qual endpoint consultar (eventos, performance, etc)\n";
