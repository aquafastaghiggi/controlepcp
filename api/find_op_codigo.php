<?php
/**
 * Encontrar o codigoOrdemProducao para OP 0201055
 * usando o endpoint ordemProducao (cadastro) que já funcionava
 */

$codi_url = 'http://192.168.8.246:8080';
$codi_user = 'Aghiggi';
$codi_pass = '@Ag0351@';

// Buscar a OP 0201055 com o endpoint de cadastro
$url = $codi_url . '/action/ger/webservice/rest/ordemProducao?ordem=0201055&pagina=1';

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_USERPWD, "$codi_user:$codi_pass");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "=== Buscando OP 0201055 ===\n";
echo "URL: $url\n";
echo "HTTP Code: $http_code\n\n";

$data = json_decode($response, true);

if (isset($data['data']) && is_array($data['data']) && count($data['data']) > 0) {
    $op = $data['data'][0];
    
    echo "✓ OP encontrada!\n";
    echo "codigoOrdemProducao: " . $op['codigoOrdemProducao'] . "\n";
    echo "ordem: " . $op['ordem'] . "\n";
    echo "status: " . $op['status'] . "\n";
    echo "quantidade: " . $op['quantidade'] . "\n\n";
    
    $codigo_op = $op['codigoOrdemProducao'];
    
    // Agora buscar no sequenciamento com este código
    echo "\n=== Subindo dados de Sequenciamento para codigoOrdemProducao=$codigo_op ===\n";
    
    $url_seq = $codi_url . '/action/ger/webservice/rest/sequenciamentoProducao?codigoOrdemProducao=' . $codigo_op;
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url_seq);
    curl_setopt($ch, CURLOPT_USERPWD, "$codi_user:$codi_pass");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response_seq = curl_exec($ch);
    $http_code_seq = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    echo "URL: $url_seq\n";
    echo "HTTP Code: $http_code_seq\n";
    
    if ($http_code_seq == 200) {
        $data_seq = json_decode($response_seq, true);
        echo "\nSequenciamentos encontrados: " . count($data_seq['data']) . "\n\n";
        
        if (count($data_seq['data']) > 0) {
            echo "📊 Primeiro sequenciamento:\n";
            print_r($data_seq['data'][0]);
        }
    }
    
} else {
    echo "❌ OP 0201055 não encontrada\n";
    echo $response;
}

?>
