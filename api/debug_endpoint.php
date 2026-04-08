<?php
/**
 * Debug: mostrar resposta bruta do endpoint
 */

$codi_url = 'http://192.168.8.246:8080';
$codi_user = 'Aghiggi';
$codi_pass = '@Ag0351@';

$url = $codi_url . '/action/ger/webservice/rest/relatorioQtdeOrdemProducao?ordem=0201055&pageSize=500';

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_USERPWD, "$codi_user:$codi_pass");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 15);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP CODE: " . $http_code . "\n";
echo "RESPONSE LENGTH: " . strlen($response) . " bytes\n";
echo "RESPONSE:\n";
echo substr($response, 0, 2000);
echo "\n\n--- TRUNCATED ---\n";

?>
