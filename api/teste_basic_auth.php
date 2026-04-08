<?php
/**
 * Testar sem header manual - deixar curl fazer Basic Auth
 */

$codi_url = 'http://192.168.8.246:8080';
$codi_user = 'Aghiggi';
$codi_pass = '@Ag0351@';

echo "=== TESTANDO COM BASIC AUTH SIMPLES ===\n\n";

$endpoints = [
    'relatorioQtdeOrdemProducao' => '/action/ger/webservice/rest/relatorioQtdeOrdemProducao?ordem=0201055',
    'operacao' => '/action/ger/webservice/rest/operacao?ordem=0201055',
    'relatorioOperacao' => '/action/ger/webservice/rest/relatorioOperacao?ordem=0201055',
];

foreach ($endpoints as $nome => $endpoint) {
    echo "\nTestando: $nome\n";
    
    $url = $codi_url . $endpoint;
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_USERPWD, "$codi_user:$codi_pass");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    echo "HTTP: $http_code | ";
    
    if ($http_code == 200) {
        echo "✅ SUCESSO!\n\n";
        echo "URL CORRETA: $url\n\n";
        echo "RESPOSTA:\n";
        echo str_repeat("-", 70) . "\n";
        echo $response . "\n";
        echo str_repeat("-", 70) . "\n";
        
        if (strpos($response, '3734') !== false) {
            echo "\n✅✅✅ ENCONTRADO 3734!\n\n";
            echo "URL PARA USAR NO CÓDIGO:\n";
            echo $url . "\n";
            exit;
        }
    } else {
        echo "HTTP $http_code\n";
    }
}

?>
