<?php
// Consultar status de finalização da OP 201055 diretamente no CODI

$url = "http://192.168.8.246:8080/action/ger/webservice/rest/ordemProducao/0201055";

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
curl_setopt($ch, CURLOPT_USERPWD, 'Aghiggi:@Ag0351@');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 15);
curl_setopt($ch, CURLOPT_VERBOSE, false);

echo "=== CONSULTANDO OP 201055 NO CODI ===\n";
echo "URL: $url\n\n";

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

echo "Status HTTP: $httpCode\n\n";

if ($error) {
    echo "ERRO CURL: $error\n";
} else {
    echo "RESPOSTA BRUTA:\n";
    echo substr($response, 0, 500) . "...\n\n";
    
    $data = json_decode($response, true);
    if ($data) {
        echo "DADOS DECODIFICADOS:\n";
        echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    } else {
        echo "Erro ao decodificar JSON\n";
    }
}
?>
