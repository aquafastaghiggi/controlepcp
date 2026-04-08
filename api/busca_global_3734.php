<?php
/**
 * Procurar 3734 em todas as páginas de sequenciamento
 */

$codi_url = 'http://192.168.8.246:8080';
$codi_user = 'Aghiggi';
$codi_pass = '@Ag0351@';

echo "=== PROCURANDO 3734 EM TODAS AS PÁGINAS DE SEQUENCIAMENTO ===\n\n";

for ($pagina = 1; $pagina <= 5; $pagina++) {
    echo "Página $pagina... ";
    
    $url = $codi_url . '/action/ger/webservice/rest/sequenciamentoProducao?page=' . $pagina . '&pageSize=500';
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_USERPWD, "$codi_user:$codi_pass");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code == 200) {
        // Procurar por 3734
        if (strpos($response, '3734') !== false) {
            echo "✅ ENCONTRADO 3734!\n\n";
            
            $data = json_decode($response, true);
            
            if ($data && isset($data['data'])) {
                foreach ($data['data'] as $i => $seq) {
                    $json_str = json_encode($seq);
                    
                    if (strpos($json_str, '3734') !== false) {
                        echo "📍 REGISTRO " . ($i+1) . ":\n";
                        echo str_repeat("-", 70) . "\n";
                        echo json_encode($seq, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
                        echo str_repeat("-", 70) . "\n\n";
                    }
                }
            }
            exit;
        } else {
            echo "❌ Não encontrado\n";
        }
    } else {
        echo "HTTP $http_code\n";
    }
}

echo "\n❌ 3734 não encontrado em nenhuma página de sequenciamento\n";

// Tentar com Performance
echo "\n\nTentando endpoint /performance...\n";

$url = $codi_url . '/action/ger/webservice/rest/performance?page=1&pageSize=500';

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
    if (strpos($response, '3734') !== false) {
        echo "✅ ENCONTRADO 3734 EM PERFORMANCE!\n";
        echo $response . "\n";
    } else {
        echo "Sem 3734\n";
    }
}

?>
