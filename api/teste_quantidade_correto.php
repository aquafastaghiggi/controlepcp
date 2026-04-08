<?php
/**
 * Testar endpoint "Ordem de Produção (Quantidade)" com companyCodename
 */

$codi_url = 'http://192.168.8.246:8080';
$codi_user = 'aghiggi';
$codi_pass = '@Ag0351@';
$company_codename = 'aquafast';

echo "=== TESTANDO ENDPOINT: Ordem de Produção (Quantidade) ===\n\n";

// URL do endpoint (conforme documentação no Postman)
$endpoint = '/action/ger/webservice/rest/relatorioQtdeOrdemProducao';
$url = $codi_url . $endpoint . '?ordem=0201055&pageSize=500';

echo "URL: $url\n";
echo "Header: X-Authorization-companyCodename: $company_codename\n\n";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_USERPWD, "$codi_user:$codi_pass");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 15);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

// IMPORTANTE: Adicionar header manualmente
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'X-Authorization-companyCodename: ' . $company_codename,
    'Content-Type: application/json'
]);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

echo "HTTP Code: $http_code\n";
echo "Erro cURL: " . ($error ? $error : "Nenhum") . "\n\n";

echo "RESPOSTA:\n";
echo str_repeat("-", 70) . "\n";
echo $response . "\n";
echo str_repeat("-", 70) . "\n";

// Procurar por 3734
if (strpos($response, '3734') !== false) {
    echo "\n✅✅✅ ENCONTRADO 3734!\n";
}

?>
