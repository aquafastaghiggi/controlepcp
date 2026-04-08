<?php
/**
 * Buscar sequenciamento usando codigoOrdemProducao 23599
 */

$codi_url = 'http://192.168.8.246:8080';
$codi_user = 'Aghiggi';
$codi_pass = '@Ag0351@';

echo "=== SEQUENCIAMENTO COM codigoOrdemProducao=23599 ===\n\n";

$url = $codi_url . '/action/ger/webservice/rest/sequenciamentoProducao?codigoOrdemProducao=23599&pageSize=500';

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_USERPWD, "$codi_user:$codi_pass");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 15);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP: $http_code\n";
echo "Response:\n\n";
echo $response . "\n";

// Procurar por 3734
if (strpos($response, '3734') !== false) {
    echo "\n\n✅ ENCONTRADO 3734!\n";
}

?>
