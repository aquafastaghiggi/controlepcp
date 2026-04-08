<?php
/**
 * Procurar OPs que contenham "201" ou "055" ou variações
 */

$base_url = 'http://192.168.8.246:8080';
$username = 'Aghiggi';
$password = '@Ag0351@';

// Buscar todas as páginas procurando por OPs com "201" ou "055"
$ops_encontradas = [];
$total_processado = 0;
$pages_to_check = 20; // Aumentar para procurar em mais páginas

echo "=== PROCURANDO POR OPs COM '201' OU '055' ===\n";
echo "Verificando $pages_to_check páginas (500 registros cada)...\n\n";

for ($page = 1; $page <= $pages_to_check; $page++) {
    $url = $base_url . '/action/ger/webservice/rest/ordemProducao?page=' . $page . '&pageSize=500';
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_USERPWD, "$username:$password");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code === 200) {
        $response_utf8 = iconv('ISO-8859-1', 'UTF-8', $response);
        $data = json_decode($response_utf8, true);
        
        if (isset($data['data']) && is_array($data['data'])) {
            foreach ($data['data'] as $order) {
                $op = $order['ordem'];
                $total_processado++;
                
                // Procurar por padrões
                if (strpos($op, '201') !== false || strpos($op, '055') !== false) {
                    $ops_encontradas[] = [
                        'ordem' => $op,
                        'status' => $order['status'],
                        'quantidade' => $order['quantidade']
                    ];
                }
            }
        }
        
        echo "Página $page: OK ({$data['totalCount']} total OPs)\n";
    } else {
        echo "Página $page: Erro $http_code\n";
    }
}

echo "\n=== RESULTADOS ===\n";
echo "Total processado: $total_processado OPs\n";
echo "OPs com '201' ou '055': " . count($ops_encontradas) . "\n\n";

if (count($ops_encontradas) > 0) {
    foreach ($ops_encontradas as $op) {
        echo "OP {$op['ordem']}: {$op['status']} ({$op['quantidade']} un)\n";
    }
} else {
    echo "Nenhuma OP encontrada com '201' ou '055'\n";
}

// Procurar especificamente por números começando com 2
echo "\n=== PROCURANDO OPs QUE COMEÇAM COM '2' ===\n";
$ops_com_2 = [];

for ($page = 1; $page <= 10; $page++) {
    $url = $base_url . '/action/ger/webservice/rest/ordemProducao?page=' . $page . '&pageSize=500';
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_USERPWD, "$username:$password");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code === 200) {
        $response_utf8 = iconv('ISO-8859-1', 'UTF-8', $response);
        $data = json_decode($response_utf8, true);
        
        if (isset($data['data'])) {
            foreach ($data['data'] as $order) {
                if (substr($order['ordem'], 0, 1) === '2') {
                    $ops_com_2[] = $order['ordem'];
                }
            }
        }
    }
}

echo "OPs começando com '2': " . count($ops_com_2) . "\n";
if (count($ops_com_2) > 0) {
    foreach (array_slice($ops_com_2, 0, 20) as $op) {
        echo "- $op\n";
    }
    if (count($ops_com_2) > 20) {
        echo "... e " . (count($ops_com_2) - 20) . " mais\n";
    }
}
?>
