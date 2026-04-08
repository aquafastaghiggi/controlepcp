<?php
// Teste direto - procurar OP 201055 em quantas páginas?

$codi_url = 'http://192.168.8.246:8080';
$codi_user = 'Aghiggi';
$codi_pass = '@Ag0351@';

echo "Procurando OP 201055 ou 0201055...\n";
echo "Iterando por páginas...\n\n";

$page_count = 0;
$found = false;

for ($page = 1; $page <= 100; $page++) {
    $url = $codi_url . '/action/ger/webservice/rest/ordemProducao?page=' . $page . '&pageSize=500';
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_USERPWD, "$codi_user:$codi_pass");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    $page_count++;
    
    if ($http_code === 200 && !empty($response)) {
        $response_utf8 = iconv('ISO-8859-1', 'UTF-8', $response);
        $data = json_decode($response_utf8, true);
        
        if (isset($data['data']) && is_array($data['data'])) {
            // Procurar no total
            foreach ($data['data'] as $order) {
                if ($order['ordem'] == '201055' || $order['ordem'] == '0201055') {
                    echo "✅ ENCONTRADO NA PÁGINA $page!\n";
                    echo json_encode($order, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
                    $found = true;
                    break 2;
                }
            }
        }
        
        // Progress
        if ($page % 10 == 0) {
            echo "Página $page: OK (procurando...)\n";
        }
    } else {
        echo "Página $page: HTTP $http_code\n";
        if ($error) {
            echo "  Erro: $error\n";
        }
    }
}

if (!$found) {
    echo "\n❌ OP não encontrada após procurar $page_count páginas (cerca de " . ($page_count * 500) . " OPs)\n";
}
?>
