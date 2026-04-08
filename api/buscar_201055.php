<?php
/**
 * Buscar ordem 201055 na CODI
 */

$codi_url = 'http://192.168.8.246:8080';
$codi_user = 'Aghiggi';
$codi_pass = '@Ag0351@';

// Buscar especificamente a ordem 201055
$url = $codi_url . '/action/ger/webservice/rest/relatorioEvento?ordem=201055&pageSize=10';

echo "=== BUSCANDO ORDEM 201055 ===\n\n";

echo "📍 URL: $url\n";
echo "👤 User: $codi_user\n\n";

echo str_repeat("=", 70) . "\n";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_USERPWD, "$codi_user:$codi_pass");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 15);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);
curl_close($ch);

if ($curl_error) {
    echo "❌ ERRO: $curl_error\n";
} else {
    echo "✅ Resposta HTTP: $http_code\n\n";
    
    if ($http_code == 200) {
        $data = json_decode($response, true);
        
        if ($data && isset($data['data'])) {
            echo "Total de eventos encontrados: " . count($data['data']) . "\n\n";
            
            if (count($data['data']) > 0) {
                echo str_repeat("-", 70) . "\n";
                echo "PRIMEIROS DADOS:\n";
                echo str_repeat("-", 70) . "\n";
                echo json_encode($data['data'][0], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
            }
        } else {
            echo "Resposta:\n";
            echo substr($response, 0, 500) . "\n";
        }
    }
}

?>
