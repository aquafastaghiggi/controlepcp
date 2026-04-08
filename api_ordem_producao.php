<?php
$base_url = 'http://192.168.8.246:8080';
$username = 'Aghiggi';
$password = '@Ag0351@';

echo "=== ENDPOINT: /ordemProducao ===\n\n";

$ch = curl_init($base_url . '/action/ger/webservice/rest/ordemProducao');
curl_setopt($ch, CURLOPT_USERPWD, "$username:$password");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 5);

$response = curl_exec($ch);
curl_close($ch);

// Converter de ISO-8859-1 para UTF-8
$response_utf8 = iconv('ISO-8859-1', 'UTF-8//IGNORE', $response);
$json = json_decode($response_utf8, true);

if ($json) {
    echo "Status: OK\n";
    
    if (isset($json['totalCount'])) {
        echo "Total Count: {$json['totalCount']}\n";
        echo "Total Pages: {$json['totalPages']}\n";
    }
    
    if (isset($json['data']) && is_array($json['data'])) {
        echo "Dados: " . count($json['data']) . " registros\n\n";
        
        if (count($json['data']) > 0) {
            $first = $json['data'][0];
            echo "Primeiro registro:\n";
            echo json_encode($first, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
        }
    }
} else {
    echo "Erro ao fazer parse JSON\n";
    echo "Primeiros 500 chars: " . substr($response, 0, 500) . "\n";
}
