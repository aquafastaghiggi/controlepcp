<?php
/**
 * Teste CODI - Autenticação
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');

$baseUrl = 'http://192.168.8.246:8080';
$user = 'Postgres';
$pass = 'data#3789!';

echo "🔐 Testando Autenticação CODI\n";
echo "=" . str_repeat("=", 80) . "\n\n";

// 1. Tentar Basic Auth com cookie jar
echo "1️⃣ Tentando Basic Auth + Session:\n\n";

$cookieFile = tempnam(sys_get_temp_dir(), 'codi_');

function requestWithSession($url, $user, $pass, $cookieFile, $method = 'GET', $data = null) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    
    // Session cookies
    curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
    curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
    
    // Basic Auth
    curl_setopt($ch, CURLOPT_USERPWD, "$user:$pass");
    
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json'
    ]);
    
    if ($method === 'POST' && $data) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    
    curl_close($ch);
    
    return [
        'code' => $httpCode,
        'response' => $response,
        'error' => $error
    ];
}

// Teste 1: Acesso direto com Basic Auth
$result = requestWithSession(
    "$baseUrl/action/ger/webservice/rest/relatorioEvento?pageNumber=1&pageSize=1",
    $user,
    $pass,
    $cookieFile
);

echo "   URL: /action/ger/webservice/rest/relatorioEvento\n";
echo "   Auth: Basic ($user:****)\n";
echo "   Status: HTTP {$result['code']}\n";

if ($result['error']) {
    echo "   Erro: {$result['error']}\n";
} else {
    if ($result['code'] >= 200 && $result['code'] < 300) {
        echo "   ✅ SUCESSO!\n";
        echo "\n   Resposta:\n";
        $json = json_decode($result['response'], true);
        if ($json) {
            echo json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
        } else {
            echo substr($result['response'], 0, 300) . "\n";
        }
    } else {
        echo "   Resposta: " . substr($result['response'], 0, 200) . "\n";
    }
}

// Teste 2: Tentar com Company Code
echo "\n2️⃣ Tentando com Company Code no path:\n\n";

$companyCode = 'matriz';  // Assumindo padrão
$result2 = requestWithSession(
    "$baseUrl/action/ger/webservice/rest/relatorioEvento?codigoCalendarioFabril=1&pageNumber=1&pageSize=1&codname=$companyCode",
    $user,
    $pass,
    $cookieFile
);

echo "   URL: /action/ger/webservice/rest/relatorioEvento (com codname=$companyCode)\n";
echo "   Status: HTTP {$result2['code']}\n";

if ($result2['code'] >= 200 && $result2['code'] < 300) {
    echo "   ✅ SUCESSO!\n";
} else {
    echo "   Status: {$result2['code']}\n";
}

// Teste 3: Verificar cookies gerados
echo "\n3️⃣ Cookies da Sessão:\n\n";
if (file_exists($cookieFile)) {
    $cookies = file_get_contents($cookieFile);
    echo "   " . str_replace("\n", "\n   ", trim($cookies)) . "\n";
    unlink($cookieFile);
} else {
    echo "   Nenhum cookie foi gerado\n";
}

// Teste 4: Tentar POST de login se existir endpoint
echo "\n4️⃣ Tentando encontrar endpoint de login:\n\n";

$loginUrls = [
    '/login',
    '/user/login',
    '/api/login',
    '/auth/login',
    '/action/ger/login',
];

foreach ($loginUrls as $loginUrl) {
    $fullUrl = $baseUrl . $loginUrl;
    $result = requestWithSession($fullUrl, $user, $pass, tempnam(sys_get_temp_dir(), 'codi_'), 'POST', [
        'username' => $user,
        'password' => $pass
    ]);
    
    if ($result['code'] && $result['code'] != 404) {
        echo "   → $loginUrl: HTTP {$result['code']}\n";
    }
}

echo "\n" . str_repeat("=", 80) . "\n";
