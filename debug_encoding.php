<?php
/**
 * Capturar e salvar resposta bruta
 */

$url = "http://192.168.8.246:8080/action/ger/webservice/rest/recurso";
$user = 'Aghiggi';
$pass = '@Ag0351@';

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_TIMEOUT, 15);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_USERPWD, "$user:$pass");

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "Status: HTTP $httpCode\n";
echo "Tamanho: " . strlen($response) . " bytes\n";

// Tentar convert encoding
$responseUTF8 = @iconv('ISO-8859-1', 'UTF-8//IGNORE', $response);
echo "Após iconv: " . strlen($responseUTF8) . " bytes\n";

// Parse original
$jsonOriginal = json_decode($response, true);
echo "JSON original: " . ($jsonOriginal ? "✅ VÁLIDO" : "❌ INVÁLIDO") . "\n";
if (!$jsonOriginal) {
    echo "JSON error: " . json_last_error_msg() . "\n";
}

// Parse UTF8
$jsonUTF8 = json_decode($responseUTF8, true);
echo "JSON UTF-8: " . ($jsonUTF8 ? "✅ VÁLIDO" : "❌ INVÁLIDO") . "\n";
if (!$jsonUTF8) {
    echo "JSON error UTF8: " . json_last_error_msg() . "\n";
}

// Se UTF8 passou, usar ele
if ($jsonUTF8) {
    echo "\n✅ Dados obtidos com sucesso!\n";
    echo "Total: " . $jsonUTF8['totalCount'] . " itens\n";
    echo "Páginas: " . $jsonUTF8['totalPages'] . "\n";
    echo "Registros nesta página: " . count($jsonUTF8['data']) . "\n";
    
    if (isset($jsonUTF8['data'][0])) {
        echo "\nPrimeiro registro:\n";
        print_r($jsonUTF8['data'][0]);
    }
}
