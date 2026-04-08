<?php
/**
 * Debug detalhado das respostas CODI
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');

$baseUrl = 'http://192.168.8.246:8080';
$user = 'Aghiggi';
$pass = '@Ag0351@';

echo "🔬 DEBUG DETALHADO DAS RESPOSTAS CODI\n";
echo "=" . str_repeat("=", 80) . "\n\n";

function testEndpoint($name, $path, $user, $pass) {
    $url = "http://192.168.8.246:8080$path";
    
    echo "📍 $name\n";
    echo "   URL: $url\n";
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    
    curl_setopt($ch, CURLOPT_USERPWD, "$user:$pass");
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json'
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    echo "   Status: HTTP $httpCode\n";
    echo "   Content-Type: $contentType\n";
    
    if ($curlError) {
        echo "   Erro cURL: $curlError\n";
    }
    
    echo "   Tamanho resposta: " . strlen($response) . " bytes\n";
    echo "   Conteúdo (primeiras 500 chars):\n";
    echo "   " . str_replace("\n", "\n   ", substr($response, 0, 500)) . "\n";
    
    // Tentar parse JSON
    $json = json_decode($response, true);
    if ($json) {
        echo "\n   ✅ JSON válido\n";
        echo "   Estrutura: " . json_encode(array_keys($json)) . "\n";
        if (isset($json['data'])) {
            echo "   Data array: " . count($json['data']) . " itens\n";
        }
        if (isset($json['totalCount'])) {
            echo "   Total Count: " . $json['totalCount'] . "\n";
        }
    } else {
        echo "\n   ⚠️ Não é JSON válido\n";
    }
    
    echo "\n";
}

// Testar cada endpoint com parâmetros differently
testEndpoint(
    "Performance (sem parâmetros)",
    "/action/ger/webservice/rest/performance",
    $user,
    $pass
);

testEndpoint(
    "Performance (com paginação)",
    "/action/ger/webservice/rest/performance?pageNumber=0&pageSize=50",
    $user,
    $pass
);

testEndpoint(
    "Calendário (sem parâmetros)",
    "/action/ger/webservice/rest/calendarioFabril",
    $user,
    $pass
);

testEndpoint(
    "Recurso (sem parâmetros)",
    "/action/ger/webservice/rest/recurso",
    $user,
    $pass
);

testEndpoint(
    "Recurso (com paginação 0)",
    "/action/ger/webservice/rest/recurso?pageNumber=0&pageSize=50",
    $user,
    $pass
);

echo str_repeat("=", 80) . "\n";
