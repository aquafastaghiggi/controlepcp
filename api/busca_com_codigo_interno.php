<?php
/**
 * Tentar buscar com código interno da ordem
 */

$codi_url = 'http://192.168.8.246:8080';
$codi_user = 'Aghiggi';
$codi_pass = '@Ag0351@';

echo "=== BUSCANDO COM CÓDIGO INTERNO (23599) ===\n\n";

$endpoints = [
    'Ordem 0201055' => '/action/ger/webservice/rest/ordemProducao?ordem=0201055',
    'Ordem 201055' => '/action/ger/webservice/rest/ordemProducao?ordem=201055',
    'Código 23599' => '/action/ger/webservice/rest/ordemProducao?codigoOrdemProducao=23599',
    'ID 23599' => '/action/ger/webservice/rest/ordemProducao/23599',
];

foreach ($endpoints as $nome => $endpoint) {
    echo "==============================\n";
    echo "📍 $nome\n";
    echo "==============================\n";
    
    $url = $codi_url . $endpoint;
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_USERPWD, "$codi_user:$codi_pass");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 8);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    echo "HTTP: $http_code\n";
    
    if ($http_code == 200 && strlen($response) > 20) {
        $data = json_decode($response, true);
        
        if ($data) {
            echo "\n✅ RESPOSTA COMPLETA:\n";
            echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
            
            // Procurar campos de finalização
            echo "\n🔍 PROCURANDO CAMPOS DE FINALIZAÇÃO:\n";
            if (isset($data['data'][0])) {
                $item = $data['data'][0];
                foreach ($item as $chave => $valor) {
                    if (stripos($chave, 'finaliz') !== false || 
                        stripos($valor, 'finaliz') !== false ||
                        stripos($chave, 'status') !== false) {
                        echo "   → $chave: $valor\n";
                    }
                }
            }
        }
    } else {
        echo "Sem dados ou erro\n";
    }
    
    echo "\n";
}

?>
