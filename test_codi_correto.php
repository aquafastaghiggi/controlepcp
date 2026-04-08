<?php
/**
 * Teste CODI - Com credenciais corretas
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');

$baseUrl = 'http://192.168.8.246:8080';
$user = 'Aghiggi';
$pass = '@Ag0351@';

echo "🔐 Autenticando no CODI\n";
echo "=" . str_repeat("=", 80) . "\n\n";
echo "URL: $baseUrl\n";
echo "Usuário: $user\n\n";

$cookieFile = tempnam(sys_get_temp_dir(), 'codi_');

function requestCodi($url, $user, $pass, $cookieFile, $method = 'GET', $data = null) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
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

// Teste 1: Eventos
echo "1️⃣ Testando endpoint: /relatorioEvento\n";
$result = requestCodi(
    "$baseUrl/action/ger/webservice/rest/relatorioEvento?pageNumber=1&pageSize=10",
    $user,
    $pass,
    $cookieFile
);

echo "   Status: HTTP {$result['code']}";
if ($result['code'] >= 200 && $result['code'] < 300) {
    echo " ✅\n";
    $json = json_decode($result['response'], true);
    if ($json) {
        echo "   Registros encontrados: " . (isset($json['totalCount']) ? $json['totalCount'] : count($json['data'] ?? [])) . "\n";
        if (isset($json['data']) && count($json['data']) > 0) {
            echo "   Campos disponíveis: " . implode(", ", array_keys($json['data'][0])) . "\n";
            echo "\n   Primeiro registro:\n";
            echo "   " . json_encode($json['data'][0], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
        }
    }
} else {
    echo " ❌\n";
    if ($result['response']) {
        echo "   Resposta: " . substr($result['response'], 0, 200) . "\n";
    }
}

// Teste 2: Performance
echo "\n2️⃣ Testando endpoint: /performance\n";
$result2 = requestCodi(
    "$baseUrl/action/ger/webservice/rest/performance?pageNumber=1&pageSize=10",
    $user,
    $pass,
    $cookieFile
);

echo "   Status: HTTP {$result2['code']}";
if ($result2['code'] >= 200 && $result2['code'] < 300) {
    echo " ✅\n";
} else {
    echo " ⚠️\n";
}

// Teste 3: Calendário
echo "\n3️⃣ Testando endpoint: /calendarioFabril\n";
$result3 = requestCodi(
    "$baseUrl/action/ger/webservice/rest/calendarioFabril?pageNumber=1&pageSize=10",
    $user,
    $pass,
    $cookieFile
);

echo "   Status: HTTP {$result3['code']}";
if ($result3['code'] >= 200 && $result3['code'] < 300) {
    echo " ✅\n";
} else {
    echo " ⚠️\n";
}

// Teste 4: Recursos
echo "\n4️⃣ Testando endpoint: /recurso\n";
$result4 = requestCodi(
    "$baseUrl/action/ger/webservice/rest/recurso?pageNumber=1&pageSize=10",
    $user,
    $pass,
    $cookieFile
);

echo "   Status: HTTP {$result4['code']}";
if ($result4['code'] >= 200 && $result4['code'] < 300) {
    echo " ✅\n";
} else {
    echo " ⚠️\n";
}

// Teste 5: Eventos Consolidado
echo "\n5️⃣ Testando endpoint: /relatorioEventoConsolidado\n";
$result5 = requestCodi(
    "$baseUrl/action/ger/webservice/rest/relatorioEventoConsolidado?pageNumber=1&pageSize=10",
    $user,
    $pass,
    $cookieFile
);

echo "   Status: HTTP {$result5['code']}";
if ($result5['code'] >= 200 && $result5['code'] < 300) {
    echo " ✅\n";
} else {
    echo " ⚠️\n";
}

// Limpeza
if (file_exists($cookieFile)) {
    unlink($cookieFile);
}

echo "\n" . str_repeat("=", 80) . "\n";
echo "\n✅ Teste concluído. Verifique os endpoints que retornam HTTP 200\n";
