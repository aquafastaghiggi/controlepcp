<?php
/**
 * Teste CODI - URL: http://192.168.8.246:8080/
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');

$baseUrl = 'http://192.168.8.246:8080';
$user = 'Postgres';
$pass = 'data#3789!';

echo "🔍 Testando CODI em: $baseUrl\n";
echo "=" . str_repeat("=", 80) . "\n\n";

// Função auxiliar para fazer requisições
function testUrl($url, $method = 'GET', $auth = null) {
    echo "   → $method $url";
    
    try {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
        }
        
        if ($auth) {
            curl_setopt($ch, CURLOPT_USERPWD, $auth);
        }
        
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        
        curl_close($ch);
        
        if ($error) {
            echo " ... ❌ cURL: $error\n";
            return null;
        }
        
        echo " ... HTTP $httpCode";
        
        if ($httpCode >= 200 && $httpCode < 300) {
            echo " ✅\n";
            return $response;
        } elseif ($httpCode >= 300 && $httpCode < 400) {
            echo " (redirect)\n";
            return $response;
        } elseif ($httpCode >= 400 && $httpCode < 500) {
            echo " ⚠️ (cliente error)\n";
            return $response;
        } else {
            echo "\n";
            return $response;
        }
        
    } catch (Exception $e) {
        echo " ... ❌ Exception: " . $e->getMessage() . "\n";
        return null;
    }
}

// 1. Teste básico da URL raiz
echo "1️⃣ Teste da URL Raiz:\n";
$response = testUrl("$baseUrl/");

if ($response) {
    // Mostrar primeiras linhas
    $lines = array_slice(explode("\n", $response), 0, 5);
    echo "\n   Resposta (primeiras linhas):\n";
    foreach ($lines as $line) {
        if (trim($line)) {
            echo "   " . substr($line, 0, 100) . "\n";
        }
    }
}

// 2. Procurar por UI/login
echo "\n2️⃣ Procurando por pontos de entrada:\n";
$paths = [
    '/login',
    '/app',
    '/dashboard',
    '/admin',
    '/action',
    '/action/ger',
    '/action/ger/webservice',
    '/action/ger/webservice/rest',
];

foreach ($paths as $path) {
    testUrl("$baseUrl$path");
}

// 3. Tentar API endpoints típicos do CODI
echo "\n3️⃣ Testando Endpoints CODI (com autenticação):\n";
$auth = "$user:$pass";

$endpoints = [
    '/action/ger/webservice/rest/relatorioEvento',
    '/action/ger/webservice/rest/performance',
    '/action/ger/webservice/rest/calendarioFabril',
    '/action/ger/webservice/rest/recurso',
    '/action/ger/webservice/rest/operacao',
];

foreach ($endpoints as $endpoint) {
    testUrl("$baseUrl$endpoint", 'GET', $auth);
}

// 4. Tentar descobrir estrutura (se houver directory listing)
echo "\n4️⃣ Testando estrutura de diretórios:\n";
$dirs = [
    '/api',
    '/webservice',
    '/rest',
    '/services',
    '/app/api',
];

foreach ($dirs as $dir) {
    testUrl("$baseUrl$dir");
}

echo "\n" . str_repeat("=", 80) . "\n";
echo "\n✅ Verifique os resultados acima para identificar:\n";
echo "   - Qual path tem resposta 200-399\n";
echo "   - Se há UI de login\n";
echo "   - Quais endpoints retornam dados\n";
