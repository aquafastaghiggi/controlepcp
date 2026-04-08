<?php
/**
 * Buscar 3734 APENAS no CODI
 */

$codi_url = 'http://192.168.8.246:8080';
$codi_user = 'Aghiggi';
$codi_pass = '@Ag0351@';

echo "=== BUSCA: 3734 NO CODI ===\n\n";

// Endpoints que sabemos que funcionam
$endpoints = [
    'ordemProducao' => '?ordem=201055&pageSize=100',
    'relatorioEvento' => '?ordem=201055&pageSize=500',
    'relatorioEventoConsolidado' => '?ordem=201055&pageSize=500',
    'sequenciamentoProducao' => '?ordem=201055&pageSize=500',
];

foreach ($endpoints as $interface => $params) {
    echo "Testando: /action/ger/webservice/rest/$interface$params\n";
    
    $url = $codi_url . '/action/ger/webservice/rest/' . $interface . $params;
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_USERPWD, "$codi_user:$codi_pass");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    echo "HTTP: $http_code | ";
    
    if ($http_code == 200) {
        // Procurar por 3734
        if (strpos($response, '3734') !== false) {
            echo "✅ ENCONTRADO 3734!\n";
            echo "\n📊 RESPOSTA COMPLETA:\n";
            echo str_repeat("-", 70) . "\n";
            
            // Tentar parsear JSON
            $data = json_decode($response, true);
            if ($data) {
                echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
            } else {
                // Se não for JSON válido, mostrar raw
                echo $response . "\n";
            }
            echo str_repeat("-", 70) . "\n";
        } else {
            echo "❌ 3734 não encontrado\n";
            
            // Mas mostrar o que retornou
            $data = json_decode($response, true);
            if ($data && isset($data['data'])) {
                echo "Retornou " . count($data['data']) . " registros\n";
                if (count($data['data']) > 0) {
                    echo "Primeiro registro:\n";
                    echo json_encode($data['data'][0], JSON_PRETTY_PRINT) . "\n";
                }
            }
        }
    } else {
        echo "❌ HTTP $http_code\n";
    }
    
    echo "\n";
}

?>
