<?php
/**
 * Testar múltiplos endpoints com credenciais corretas
 */

$codi_url = 'http://192.168.8.246:8080';
$codi_user = 'Aghiggi';  // Com A maiúscula
$codi_pass = '@Ag0351@';
$company_codename = 'aquafast';

echo "=== TESTANDO ENDPOINT DE QUANTIDADE COM COMPANYCODENAME ===\n\n";

// Endpoints a testar (conforme Postman)
$endpoints = [
    'relatorioQtdeOrdemProducao' => '/action/ger/webservice/rest/relatorioQtdeOrdemProducao?ordem=0201055&pageSize=500',
    'operacao' => '/action/ger/webservice/rest/operacao?ordem=0201055&pageSize=500',
    'relatorioOperacao' => '/action/ger/webservice/rest/relatorioOperacao?ordem=0201055&pageSize=500',
];

foreach ($endpoints as $nome => $endpoint) {
    echo "\n" . str_repeat("=", 70) . "\n";
    echo "TESTANDO: $nome\n";
    echo "URL: " . $codi_url . $endpoint . "\n";
    
    $url = $codi_url . $endpoint;
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_USERPWD, "$codi_user:$codi_pass");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    // Header com companyCodename
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'X-Authorization-companyCodename: ' . $company_codename,
        'Content-Type: application/json'
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    echo "HTTP: $http_code\n";
    
    if ($http_code == 200) {
        echo "✅ SUCESSO!\n";
        echo "Resposta:\n";
        echo $response . "\n";
        
        if (strpos($response, '3734') !== false) {
            echo "\n✅✅✅ ENCONTRADO 3734!\n";
            exit;
        }
    } else {
        echo "Resposta: " . substr($response, 0, 200) . "\n";
    }
}

echo "\n\n❌ Nenhum endpoint retornou 200\n";

?>
