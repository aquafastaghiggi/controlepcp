<?php
/**
 * Script para explorar dados de 2026 retornados pela API
 */

$auth = 'Aghiggi:@Ag0351@';
$base_url = 'http://192.168.8.246:8080';

echo "=== EXPLORANDO DADOS DE 2026 ===\n\n";

// Testar com filtro de data
$url = $base_url . '/action/ger/webservice/rest/calendarioFabril?dataInicio=2026-04-01&dataFim=2026-04-07&limit=100';

echo "URL: $url\n\n";

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $url,
    CURLOPT_USERPWD => $auth,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 10,
    CURLOPT_VERBOSE => false
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErr = curl_error($ch);
curl_close($ch);

echo "HTTP Code: $httpCode\n";
if (!empty($curlErr)) {
    echo "Curl Error: $curlErr\n";
}

echo "Response length: " . strlen($response) . " bytes\n\n";

// Converter encoding
$response_converted = @iconv('ISO-8859-1', 'UTF-8//IGNORE', $response);

// Tentar decodificar JSON
$data = json_decode($response_converted, true);

if ($data) {
    echo "✓ JSON válido!\n";
    echo "Tipo do resultado: " . gettype($data) . "\n";
    
    if (is_array($data)) {
        echo "Total de items: " . count($data) . "\n\n";
        
        // Analisar primeiro item
        if (count($data) > 0) {
            $first = reset($data);
            
            echo "=== ESTRUTURA DO PRIMEIRO ITEM ===\n";
            echo json_encode($first, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
            
            // Procurar por datas de 2026
            echo "=== BUSCA POR DATAS 2026 ===\n";
            $found_2026 = 0;
            foreach ($data as $idx => $item) {
                $item_json = json_encode($item, JSON_UNESCAPED_UNICODE);
                if (preg_match('/2026/', $item_json)) {
                    $found_2026++;
                    if ($found_2026 <= 3) {
                        echo "\nItem [$idx]:\n";
                        echo json_encode($item, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
                    }
                }
            }
            echo "\n✓ Total com 2026: $found_2026\n";
        }
    }
} else {
    echo "✗ JSON inválido!\n";
    echo "Primeiros 500 chars:\n";
    echo substr($response_converted, 0, 500) . "\n";
}

// Testar endpoint de Performance com data
echo "\n\n=== TESTANDO PERFORMANCE COM DATA ===\n\n";

$url_perf = $base_url . '/action/ger/webservice/rest/performance?dataInicio=2026-04-01&limit=10';

echo "URL: $url_perf\n\n";

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $url_perf,
    CURLOPT_USERPWD => $auth,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 10
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: $httpCode\n";

$response_converted = @iconv('ISO-8859-1', 'UTF-8//IGNORE', $response);
$data = json_decode($response_converted, true);

if ($data && is_array($data)) {
    echo "Items retornados: " . count($data) . "\n";
    if (count($data) > 0) {
        echo "\nPrimeiro item:\n";
        echo json_encode(reset($data), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    }
}
?>
