<?php
/**
 * Buscar OP 201055 na CODI API e extrair produção entre 27/03-28/03
 */

$codi_url = 'http://192.168.8.246:8080';
$codi_user = 'Aghiggi';
$codi_pass = '@Ag0351@';

echo "=== BUSCANDO OP 0201055 NA CODI E VERIFICANDO DETALHES ===\n\n";

// Endpoint para performance/calendário
$endpoints = [
    '/action/ger/webservice/rest/ordemProducao',
    '/action/ger/webservice/rest/performance',
    '/action/ger/webservice/rest/calendario',
    '/action/ger/webservice/rest/execucoes',
];

foreach ($endpoints as $endpoint) {
    echo "Testando endpoint: $endpoint\n";
    
    // Tentar buscar com filtro de data + OP
    $urls = [
        $codi_url . $endpoint . '?dataInicio=2026-03-27&dataFim=2026-03-28&page=1&pageSize=500',
        $codi_url . $endpoint . '?page=1&pageSize=500',
    ];
    
    foreach ($urls as $url) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_USERPWD, "$codi_user:$codi_pass");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code === 200 && !empty($response)) {
            $response_utf8 = iconv('ISO-8859-1', 'UTF-8', $response);
            $data = json_decode($response_utf8, true);
            
            if (isset($data['data']) && is_array($data['data'])) {
                foreach ($data['data'] as $item) {
                    $item_str = json_encode($item);
                    
                    if (strpos($item_str, '201055') !== false || 
                        strpos($item_str, '0201055') !== false ||
                        strpos($item_str, '3734') !== false) {
                        
                        echo "\n✅ ENCONTRADO em $endpoint\n";
                        echo json_encode($item, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
                        break 3;
                    }
                }
            }
        }
    }
    
    echo "  ❌ Não encontrou\n";
}

echo "\n=== BUSCAR PERFORMANCE ESPECÍFICO ===\n\n";

// Procurar em /performance com mais especificidade
for ($page = 1; $page <= 10; $page++) {
    $url = $codi_url . '/action/ger/webservice/rest/performance?page=' . $page . '&pageSize=500';
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_USERPWD, "$codi_user:$codi_pass");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code === 200 && !empty($response)) {
        $response_utf8 = iconv('ISO-8859-1', 'UTF-8', $response);
        $data = json_decode($response_utf8, true);
        
        if (isset($data['data'])) {
            $count = count($data['data']);
            echo "Performance página $page: $count registros\n";
            
            foreach ($data['data'] as $perf) {
                $perf_str = json_encode($perf);
                
                if ((strpos($perf_str, '201055') !== false || strpos($perf_str, '0201055') !== false) &&
                    strpos($perf_str, '3734') !== false) {
                    
                    echo "\n✅ ENCONTRADO PERFORMANCE COM 201055 E 3734!\n";
                    echo json_encode($perf, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
                    break 2;
                }
            }
        }
    }
}
?>
