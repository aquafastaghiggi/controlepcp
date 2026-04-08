<?php
/**
 * Salvar eventos de 201055 em arquivo local
 */

$codi_url = 'http://192.168.8.246:8080';
$codi_user = 'Aghiggi';
$codi_pass = '@Ag0351@';

echo "=== BUSCAR E SALVAR EVENTOS DE 201055 ===\n\n";

// Com código interno
$url = $codi_url . '/action/ger/webservice/rest/relatorioEvento?codOrdemProducao=23599&pageSize=10';

echo "URL: $url\n";
echo "Conectando...\n\n";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_USERPWD, "$codi_user:$codi_pass");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);
curl_close($ch);

if ($curl_error) {
    echo "❌ Erro curl: $curl_error\n";
    exit;
}

echo "HTTP: $http_code\n";
echo "Bytes recebidos: " . strlen($response) . "\n\n";

if ($http_code == 200 && strlen($response) > 50) {
    // Salvar no Windows
    $file = 'C:\\xampp\\htdocs\\controlepcp_sandbox\\api\\eventos_201055.json';
    $bytes = file_put_contents($file, $response);
    echo "✅ Salvo em: $file\n";
    echo "   Tamanho: $bytes bytes\n\n";
    
    // Decodificar e mostrar
    $data = json_decode($response, true);
    
    if ($data && isset($data['data'])) {
        echo "✅ Eventos encontrados: " . count($data['data']) . "\n\n";
        
        if (count($data['data']) > 0) {
            echo "PRIMEIRO EVENTO:\n";
            echo str_repeat("-", 80) . "\n";
            $primeiro = $data['data'][0];
            
            echo "Início: " . ($primeiro['inicio'] ?? '?') . "\n";
            echo "Fim: " . ($primeiro['fim'] ?? '?') . "\n";
            echo "Estado: " . ($primeiro['estado'] ?? '?') . "\n";
            
            if (isset($primeiro['ordens'])) {
                echo "\nOrdens:\n";
                foreach ($primeiro['ordens'] as $ordem) {
                    echo "  → " . $ordem['ordemProducao']['ordem'] . " (" . $ordem['statusOperacao'] . ")\n";
                }
            }
        }
    } else {
        echo "❌ Erro ao decodificar JSON\n";
        echo "Primeiros 200 chars:\n";
        echo substr($response, 0, 200) . "\n";
    }
}

?>
