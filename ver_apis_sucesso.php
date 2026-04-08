<?php
$base_url = 'http://192.168.8.246:8080';
$username = 'Aghiggi';
$password = '@Ag0351@';

echo "=== TESTANDO: /ordemProducao ===\n";
$url = $base_url . '/action/ger/webservice/rest/ordemProducao?page=1&size=3';

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_USERPWD, "$username:$password");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 5);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
echo "Status: $http_code\n";

if ($http_code == 200) {
    $json = json_decode($response, true);
    echo "JSON Response (pretty):\n";
    echo json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
}

curl_close($ch);

echo "\n\n=== TESTANDO: /performance ===\n";
$url = $base_url . '/action/ger/webservice/rest/performance?page=1&size=3';

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_USERPWD, "$username:$password");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 5);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
echo "Status: $http_code\n";

if ($http_code == 200) {
    $json = json_decode($response, true);
    echo "JSON Response (pretty):\n";
    echo json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
}

curl_close($ch);
