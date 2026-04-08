<?php
/**
 * Buscar eventos da OP 0201055 (codigoOrdemProducao: 23599)
 */

$codi_url = 'http://192.168.8.246:8080';
$codi_user = 'Aghiggi';
$codi_pass = '@Ag0351@';

echo "=== BUSCANDO EVENTOS DA OP 0201055 ===\n";
echo "Usando: ordem=0201055 e codigoOrdemProducao=23599\n\n";

// Endpoints de eventos/apontamentos
$endpoints = [
    'relatorioEvento (ordem)' => '/action/ger/webservice/rest/relatorioEvento?ordem=0201055&pageSize=500',
    'relatorioEventoConsolidado (ordem)' => '/action/ger/webservice/rest/relatorioEventoConsolidado?ordem=0201055&pageSize=500',
    'sequenciamentoProducao (ordem)' => '/action/ger/webservice/rest/sequenciamentoProducao?ordem=0201055&pageSize=500',
];

foreach ($endpoints as $nome => $endpoint) {
    echo "Teste: $nome\n";
    echo "URL: $endpoint\n";
    
    $url = $codi_url . $endpoint;
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_USERPWD, "$codi_user:$codi_pass");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    echo "HTTP: $http_code\n";
    
    if ($http_code == 200) {
        $data = json_decode($response, true);
        
        if ($data) {
            if (isset($data['data'])) {
                echo "✅ Registros encontrados: " . count($data['data']) . "\n";
                
                if (count($data['data']) > 0) {
                    echo "\n📊 PRIMEIRO REGISTRO:\n";
                    echo str_repeat("-", 70) . "\n";
                    
                    $reg = $data['data'][0];
                    echo json_encode($reg, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
                    echo str_repeat("-", 70) . "\n";
                    
                    // Procurar por 3734
                    $json_str = json_encode($reg);
                    if (strpos($json_str, '3734') !== false) {
                        echo "\n✅✅✅ ENCONTRADO 3734 NESTE REGISTRO!\n";
                    }
                }
            } else {
                echo "Resposta: " . json_encode($data) . "\n";
            }
        } else {
            echo "Response: " . substr($response, 0, 300) . "\n";
        }
    } elseif ($http_code == 500) {
        echo "❌ HTTP 500 - Server error\n";
    } elseif ($http_code == 400) {
        echo "❌ HTTP 400 - Interface não configurada\n";
    } else {
        echo "❌ HTTP $http_code\n";
    }
    
    echo "\n";
}

?>
