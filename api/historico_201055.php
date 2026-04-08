<?php
/**
 * Buscar histórico / eventos consolidados de 201055
 * Para ver quando foi finalizada
 */

$codi_url = 'http://192.168.8.246:8080';
$codi_user = 'Aghiggi';
$codi_pass = '@Ag0351@';

echo "=== BUSCANDO HISTÓRICO DE FINALIZAÇÃO 201055 ===\n\n";

// Tentar relatorioEventoConsolidado
$url = $codi_url . '/action/ger/webservice/rest/relatorioEventoConsolidado?ordem=0201055&pageSize=50';

echo "Buscando em: /relatorioEventoConsolidado\n";
echo "URL: $url\n\n";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_USERPWD, "$codi_user:$codi_pass");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 15);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($http_code == 200) {
    $data = json_decode($response, true);
    
    if ($data) {
        echo "HTTP: $http_code ✅\n\n";
        
        if (isset($data['data']) && is_array($data['data'])) {
            echo "Registros encontrados: " . count($data['data']) . "\n\n";
            
            if (count($data['data']) > 0) {
                // Mostrar primeiro registro completo
                echo "PRIMEIRO REGISTRO:\n";
                echo str_repeat("-", 80) . "\n";
                echo json_encode($data['data'][0], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
                echo str_repeat("-", 80) . "\n";
            }
        } else {
            echo "Resposta:\n";
            echo json_encode($data, JSON_PRETTY_PRINT) . "\n";
        }
    }
} else {
    echo "HTTP: $http_code\n";
    echo "Resposta: " . substr($response, 0, 300) . "\n";
}

?>
