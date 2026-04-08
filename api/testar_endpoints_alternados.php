<?php
/**
 * Testar relatorioEvento com diferentes parâmetros
 */

$codi_url = 'http://192.168.8.246:8080';
$codi_user = 'Aghiggi';
$codi_pass = '@Ag0351@';

$endpoints = [
    'relatorioEvento (codOrdemProducao)' => '/action/ger/webservice/rest/relatorioEvento?codOrdemProducao=0201055&pageSize=500',
    'relatorioEvento (ordem)' => '/action/ger/webservice/rest/relatorioEvento?ordem=0201055&pageSize=500',
    'relatorioEventoConsolidado (ordem)' => '/action/ger/webservice/rest/relatorioEventoConsolidado?ordem=0201055&pageSize=500',
    'apontamentosProducao' => '/action/ger/webservice/rest/apontamentosProducao?ordem=0201055&pageSize=500',
];

foreach ($endpoints as $nome => $url) {
    echo "\n" . str_repeat("=", 70) . "\n";
    echo "📌 $nome\n";
    echo str_repeat("=", 70) . "\n";
    
    $url_full = $codi_url . $url;
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url_full);
    curl_setopt($ch, CURLOPT_USERPWD, "$codi_user:$codi_pass");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    echo "HTTP: $http_code | Tamanho: " . strlen($response) . " bytes\n\n";
    
    if ($http_code == 200 && strlen($response) > 0) {
        // Mostrar primeiros 800 chars
        echo substr($response, 0, 800) . "\n\n";
        
        // Procurar por 0201055 ou 3734
        if (strpos($response, '0201055') !== false) {
            echo "✅ ENCONTRADO: 0201055\n";
        }
        if (strpos($response, '3734') !== false) {
            echo "✅ ENCONTRADO: 3734\n";
        }
    }
}

?>
