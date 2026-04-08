<?php
$base_url = 'http://192.168.8.246:8080';
$username = 'Aghiggi';
$password = '@Ag0351@';

echo "=== TESTANDO SEM PARÂMETROS ===\n\n";

$urls = [
    '/action/ger/webservice/rest/ordemProducao',
    '/action/ger/webservice/rest/performance',
];

foreach ($urls as $path) {
    echo "URL: $path\n";
    
    $ch = curl_init($base_url . $path);
    curl_setopt($ch, CURLOPT_USERPWD, "$username:$password");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_VERBOSE, false);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $content_type = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    
    echo "  Status: $http_code\n";
    echo "  Content-Type: $content_type\n";
    echo "  Response length: " . strlen($response) . " bytes\n";
    
    if ($http_code == 200 && !empty($response)) {
        $json = json_decode($response, true);
        if ($json) {
            if (is_array($json)) {
                if (isset($json['data'])) {
                    echo "  Data count: " . count($json['data']) . "\n";
                    if (count($json['data']) > 0) {
                        $first = $json['data'][0];
                        echo "  First keys: " . implode(', ', array_slice(array_keys($first), 0, 10)) . "\n";
                    }
                } else {
                    echo "  Top keys: " . implode(', ', array_slice(array_keys($json), 0, 10)) . "\n";
                }
            }
            // Mostrar estrutura (primeiros 1500 chars)
            $preview = json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            echo "  Preview:\n";
            echo substr($preview, 0, 1500) . "\n";
        }
    } else {
        echo "  Raw response:\n";
        echo substr($response, 0, 500) . "\n";
    }
    
    curl_close($ch);
    echo "\n\n";
}
