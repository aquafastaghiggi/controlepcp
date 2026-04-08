<?php
/**
 * Explorar API do CODI buscando Operações em Tempo Real
 * com OP, SKU, Quantidade, etc
 */

// Credenciais do CODI
$base_url = 'http://192.168.8.246:8080';
$username = 'Aghiggi';
$password = '@Ag0351@';

// Possíveis endpoints para operações/execuções
$endpoints = [
    '/action/ger/webservice/rest/operacao',
    '/action/ger/webservice/rest/operacoes',
    '/action/ger/webservice/rest/ordensProducao',
    '/action/ger/webservice/rest/ordemProducao',
    '/action/ger/webservice/rest/execucoes',
    '/action/ger/webservice/rest/historico',
    '/action/ger/webservice/rest/performance',
    '/action/ger/webservice/rest/performanceOperacoes',
];

echo "=== TESTANDO ENDPOINTS DA CODI PARA OPERAÇÕES ===\n\n";

foreach ($endpoints as $endpoint) {
    // Tentar página 1
    $url = $base_url . $endpoint . '?page=1&size=5';
    
    echo "Testando: $endpoint\n";
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_USERPWD, "$username:$password");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    echo "  Status: $http_code\n";
    
    if ($http_code == 200) {
        $json = json_decode($response, true);
        if ($json) {
            echo "  ✓ Resposta JSON válida\n";
            echo "  Chaves: " . implode(', ', array_keys($json)) . "\n";
            
            // Se tem 'data', mostra primeiro item
            if (isset($json['data']) && is_array($json['data']) && count($json['data']) > 0) {
                $first = $json['data'][0];
                echo "  Primeiro item keys: " . implode(', ', array_slice(array_keys($first), 0, 8)) . "\n";
            }
        }
    }
    
    curl_close($ch);
    echo "\n";
}

echo "\n=== TESTANDO COM FILTRO DE DATA ===\n";
$url = $base_url . '/action/ger/webservice/rest/operacao?dataInicio=2026-05-29&dataFim=2026-05-31&page=1&size=5';
echo "URL: $url\n";

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_USERPWD, "$username:$password");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 5);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
echo "Status: $http_code\n";

if ($http_code == 200) {
    $json = json_decode($response, true);
    echo "Resposta: " . json_encode($json, JSON_PRETTY_PRINT) . "\n";
}

curl_close($ch);
