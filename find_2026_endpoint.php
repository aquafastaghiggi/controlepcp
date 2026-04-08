<?php
/**
 * Procurar endpoint de dados em tempo real/execução atual
 */

$auth = 'Aghiggi:@Ag0351@';
$base_url = 'http://192.168.8.246:8080';

echo "=== PROCURANDO ENDPOINT DE TEMPO REAL ===\n\n";

$endpoints = [
    '/action/ger/webservice/rest/calendarioFabril/atual',
    '/action/ger/webservice/rest/calendarioFabril/hoje',
    '/action/ger/webservice/rest/calendarioFabril/current',
    '/action/ger/webservice/rest/execucao',
    '/action/ger/webservice/rest/execucoes',
    '/action/ger/webservice/rest/execucaoAtual',
    '/action/ger/webservice/rest/execucaoAtualmente',
    '/action/ger/webservice/rest/realizado',
    '/action/ger/webservice/rest/performance/current',
    '/action/ger/webservice/rest/performance/hoje',
    '/action/ger/webservice/rest/performance/atual',
];

foreach ($endpoints as $endpoint) {
    $url = $base_url . $endpoint;
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_USERPWD, $auth);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    $response = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    
    if ($code === 200 || $code === 400) {
        echo "$endpoint | HTTP $code";
        
        if ($code === 200) {
            $jval = json_decode(@iconv('ISO-8859-1', 'UTF-8//IGNORE', $response), true);
            if ($jval) {
                if (is_array($jval) && isset($jval['data'])) {
                    echo " | Dados encontrados! Count: " . count($jval['data']) . "\n";
                } else if (is_array($jval)) {
                    echo " | Array com " . count($jval) . " items\n";
                } else {
                    echo " | Tipo: " . gettype($jval) . "\n";
                }
            }
        } else {
            echo "\n";
        }
    }
}

// Tentar com página alta (dados mais recentes podem estar em páginas altas)
echo "\n=== TESTANDO PÁGINAS ALTAS (DADOS RECENTES) ===\n\n";

$pages = [1, 10, 100, 1000, 3000, 3100, 3147];

foreach ($pages as $page) {
    echo "Page $page: ";
    $url = $base_url . "/action/ger/webservice/rest/calendarioFabril?page=$page&limit=10";
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_USERPWD, $auth);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    $response = curl_exec($ch);
    curl_close($ch);
    
    $json = json_decode(@iconv('ISO-8859-1', 'UTF-8//IGNORE', $response), true);
    
    if (isset($json['data']) && is_array($json['data']) && count($json['data']) > 0) {
        $items = $json['data'];
        
        // Procurar data mais recente
        $latest_date = null;
        foreach ($items as $item) {
            if (isset($item['data']) && (!$latest_date || $item['data'] > $latest_date)) {
                $latest_date = $item['data'];
            }
        }
        
        echo "Data mais recente: $latest_date";
        if ($latest_date && strpos($latest_date, '2026') !== false) {
            echo " ✓✓✓ ENCONTRADO 2026!";
        }
    } else {
        echo "Sem dados";
    }
    echo "\n";
}
?>
