<?php
/**
 * Buscar último evento de 201055 pra ver quando terminou a produção
 */

$codi_url = 'http://192.168.8.246:8080';
$codi_user = 'Aghiggi';
$codi_pass = '@Ag0351@';

echo "=== BUSCANDO ÚLTIMO EVENTO DE 201055 ===\n\n";

// Buscar eventos com limite bem pequeno
$url = $codi_url . '/action/ger/webservice/rest/relatorioEvento?ordem=0201055&pageSize=1';

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_USERPWD, "$codi_user:$codi_pass");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($http_code == 200) {
    $data = json_decode($response, true);
    
    if ($data && isset($data['data'][0])) {
        $evento = $data['data'][0];
        
        echo "PRIMEIRO EVENTO:\n";
        echo json_encode($evento, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
        
        echo "==============================\n";
        echo "📅 DATAS:\n";
        echo "Início: " . ($evento['inicio'] ?? '?') . "\n";
        echo "Fim: " . ($evento['fim'] ?? '?') . "\n";
        echo "Estado: " . ($evento['estado'] ?? '?') . "\n";
        echo "Duração: " . ($evento['duracao'] ?? '?') . " minutos\n";
        
        if (isset($evento['ordens']) && is_array($evento['ordens'])) {
            echo "\n📋 ORDENS NO EVENTO:\n";
            foreach ($evento['ordens'] as $ord) {
                echo "  - Ordem: " . ($ord['ordemProducao']['ordem'] ?? '?') . "\n";
                echo "    Status: " . ($ord['statusOperacao'] ?? '?') . "\n";
            }
        }
    }
}

?>
