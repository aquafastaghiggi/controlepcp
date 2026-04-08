<?php
$base_url = 'http://192.168.8.246:8080';
$username = 'Aghiggi';
$password = '@Ag0351@';
$url = $base_url . '/action/ger/webservice/rest/ordemProducao?page=1&pageSize=500';

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_USERPWD, "$username:$password");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "Status: $http_code\n\n";

// Decode com charset correto
$response_utf8 = iconv('ISO-8859-1', 'UTF-8', $response);
$data = json_decode($response_utf8, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    echo "Erro JSON: " . json_last_error_msg() . "\n";
    echo "Raw response: " . substr($response, 0, 300) . "\n";
    exit(1);
}

echo "✓ Total OPs na CODI: {$data['totalCount']}\n";
echo "✓ Registros nesta página: " . count($data['data'] ?? []) . "\n\n";

// Buscar OP 201055
$found = false;
if (isset($data['data']) && is_array($data['data'])) {
    foreach ($data['data'] as $order) {
        if ($order['ordem'] == '201055') {
            echo "✅ OP 201055 ENCONTRADA!\n\n";
            echo json_encode($order, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
            $found = true;
            break;
        }
    }
}

if (!$found) {
    echo "❌ OP 201055 NÃO encontrada nesta página\n";
    echo "Buscando em mais páginas...\n\n";
    
    // Procurar em mais páginas
    for ($page = 2; $page <= min(5, $data['totalPages'] ?? 1); $page++) {
        echo "Procurando página $page...\n";
        
        $url_next = $base_url . '/action/ger/webservice/rest/ordemProducao?page=' . $page . '&pageSize=500';
        $ch = curl_init($url_next);
        curl_setopt($ch, CURLOPT_USERPWD, "$username:$password");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        
        $response_page = curl_exec($ch);
        $http_code_page = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code_page === 200) {
            $response_page_utf8 = iconv('ISO-8859-1', 'UTF-8', $response_page);
            $data_page = json_decode($response_page_utf8, true);
            
            if (isset($data_page['data']) && is_array($data_page['data'])) {
                foreach ($data_page['data'] as $order) {
                    if ($order['ordem'] == '201055') {
                        echo "\n✅ OP 201055 ENCONTRADA NA PÁGINA $page!\n\n";
                        echo json_encode($order, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
                        $found = true;
                        break 2;
                    }
                }
            }
        }
    }
    
    if (!$found) {
        echo "\nOP 201055 NÃO ENCONTRADA em nenhuma página!\n";
        echo "\nPrimeiras 10 OPs disponíveis:\n";
        if (isset($data['data'])) {
            for ($i = 0; $i < min(10, count($data['data'])); $i++) {
                $o = $data['data'][$i];
                echo ($i+1) . ". OP {$o['ordem']}: {$o['status']} ({$o['quantidade']} un)\n";
            }
        }
    }
}
?>
