<?php
/**
 * Teste correto - acessando a chave "data"
 */

$auth = 'Aghiggi:@Ag0351@';
$base_url = 'http://192.168.8.246:8080';

echo "=== TESTE CORRETO - CHAVE 'data' ===\n\n";

$url = $base_url . '/action/ger/webservice/rest/calendarioFabril?page=1&dataInicio=2026-04-01&dataFim=2026-04-07&limit=100';

echo "URL: $url\n\n";

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_USERPWD, $auth);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 15);
$response = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "Status: $code\n";

$response = @iconv('ISO-8859-1', 'UTF-8//IGNORE', $response);
$json = json_decode($response, true);

echo "Estrutura top-level:\n";
if (is_array($json)) {
    foreach (array_keys($json) as $key) {
        $val = $json[$key];
        if (is_scalar($val)) {
            echo "  $key: $val\n";
        } else {
            echo "  $key: " . gettype($val) . "\n";
        }
    }
}

echo "\n";

// Acessar data
if (isset($json['data']) && is_array($json['data'])) {
    $data = $json['data'];
    echo "✓ Chave 'data' encontrada com " . count($data) . " items\n\n";
    
    // Analisar items
    foreach (array_slice($data, 0, 3) as $idx => $item) {
        echo "Item [$idx]:\n";
        if (is_array($item)) {
            echo json_encode($item, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
        } else {
            echo "  Tipo: " . gettype($item) . " | Value: $item\n";
        }
        echo "\n";
    }
    
    // Contar 2026
    echo "Buscando 2026 em todos os items...\n";
    $found_2026 = 0;
    foreach ($data as $item) {
        $json_str = json_encode($item);
        if (strpos($json_str, '2026') !== false) {
            $found_2026++;
        }
    }
    echo "Items com 2026: $found_2026 de " . count($data) . "\n";
    
} else {
    echo "✗ Chave 'data' não encontrada ou não é array\n";
}
?>
