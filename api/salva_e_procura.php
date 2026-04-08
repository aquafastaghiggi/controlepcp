<?php
/**
 * Salvar resposta em arquivo e processar com grep
 */

$codi_url = 'http://192.168.8.246:8080';
$codi_user = 'Aghiggi';
$codi_pass = '@Ag0351@';

$url = $codi_url . '/action/ger/webservice/rest/relatorioQtdeOrdemProducao?ordem=0201055&pageSize=50';

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_USERPWD, "$codi_user:$codi_pass");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 60);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_VERBOSE, true);
curl_setopt($ch, CURLOPT_STDERR, STDOUT);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

if ($error) {
    echo "❌ curl error: $error\n";
}

echo "HTTP: $http_code\n";
echo "Response length: " . strlen($response) . "\n\n";
    echo "Tamanho: " . strlen($response) . " bytes\n\n";
    
    // Procurar por "0201055" na resposta
    if (strpos($response, '"ordem":"0201055"') !== false) {
        echo "✅ ENCONTRADO: OP 0201055 está na resposta!\n";
        
        // Procurar por 3734 perto de 0201055
        $pattern = '/"ordem":"0201055".*?"qtdeBons":([0-9.]+)/s';
        if (preg_match_all($pattern, $response, $matches)) {
            $total = 0;
            foreach ($matches[1] as $qtde) {
                $total += floatval($qtde);
            }
            echo "Total qtdeBons: $total\n";
            
            if ($total == 3734) {
                echo "✅✅✅ ENCONTRADO EXATAMENTE 3734!\n";
            }
        }
    } else {
        echo "❌ OP 0201055 não encontrada na resposta\n";
        echo "Procurando 'ordem' na resposta...\n";
        
        // Contar quantas ordens diferentes há
        preg_match_all('/"ordem":"([^"]+)"/', $response, $orders);
        $unique_orders = array_unique($orders[1]);
        
        echo "Ordens encontradas (" . count($unique_orders) . "):\n";
        foreach (array_slice($unique_orders, 0, 10) as $order) {
            echo "  - $order\n";
        }
        if (count($unique_orders) > 10) {
            echo "  ... e " . (count($unique_orders) - 10) . " outras\n";
        }
    }
} else {
    echo "❌ HTTP $http_code\n";
}

?>
