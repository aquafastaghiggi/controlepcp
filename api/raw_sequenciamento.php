<?php
/**
 * Mostrar RAW JSON do endpoints sequenciamentoProducao
 */

$codi_url = 'http://192.168.8.246:8080';
$codi_user = 'Aghiggi';
$codi_pass = '@Ag0351@';

$url = $codi_url . '/action/ger/webservice/rest/sequenciamentoProducao?ordem=201055';

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_USERPWD, "$codi_user:$codi_pass");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: $http_code\n\n";
echo "RAW JSON:\n";
echo $response . "\n";

// Agora parsear com validação
echo "\n\n=== PARSED ===\n";
$data = json_decode($response, true);
if (json_last_error() === JSON_ERROR_NONE) {
    echo "✓ JSON válido\n";
    echo "Primeiro item:\n";
    if (isset($data['data']) && is_array($data['data']) && count($data['data']) > 0) {
        echo json_encode($data['data'][0], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
} else {
    echo "Erro: " . json_last_error_msg();
}

?>
