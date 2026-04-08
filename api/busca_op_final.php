<?php
/**
 * Salvar resposta em arquivo e processar com grep
 */

$codi_url = 'http://192.168.8.246:8080';
$codi_user = 'Aghiggi';
$codi_pass = '@Ag0351@';

$url = $codi_url . '/action/ger/webservice/rest/relatorioQtdeOrdemProducao?ordem=0201055&pageSize=50';

echo "URL: $url\n\n";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_USERPWD, "$codi_user:$codi_pass");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 60);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

if ($error) {
    echo "❌ curl error: $error\n";
}

echo "HTTP: $http_code\n";
echo "Response length: " . strlen($response) . " bytes\n\n";

if ($http_code == 200 && strlen($response) > 0) {
    // Procurar por "0201055" na resposta
    if (strpos($response, '"ordem":"0201055"') !== false) {
        echo "✅ ENCONTRADO: OP 0201055 está na resposta!\n\n";
        
        // Procurar todas as ocorrências e somar qtdeBons
        $pattern = '/"ordem":"0201055"[^}]*?"operacao":(\d+)[^}]*?"qtdeBons":([0-9.]+)/s';
        preg_match_all($pattern, $response, $matches, PREG_SET_ORDER);
        
        if (count($matches) > 0) {
            $total = 0;
            echo "Operações encontradas:\n";
            echo str_repeat("-", 60) . "\n";
            
            foreach ($matches as $match) {
                $op = $match[1];
                $qtde = floatval($match[2]);
                $total += $qtde;
                echo "Operação $op: $qtde boas\n";
            }
            
            echo str_repeat("-", 60) . "\n";
            echo "TOTAL: $total\n";
            
            if ($total == 3734) {
                echo "✅✅✅ ENCONTRADO EXATAMENTE 3734!\n";
            } else if ($total > 0) {
                echo "⚠️  Total não é 3734. Diferença: " . ($total - 3734) . "\n";
            }
        }
    } else {
        echo "❌ OP 0201055 não encontrada na resposta\n\n";
        echo "Procurando ordens na resposta...\n";
        
        // Contar quantas ordens diferentes há
        preg_match_all('/"ordem":"([^"]+)"/', $response, $orders);
        $unique_orders = array_unique($orders[1]);
        
        echo "\nOrdens encontradas (" . count($unique_orders) . "):\n";
        foreach (array_slice($unique_orders, 0, 20) as $order) {
            echo "  - $order\n";
        }
        if (count($unique_orders) > 20) {
            echo "  ... e " . (count($unique_orders) - 20) . " outras\n";
        }
        
        // Salvar arquivo para análise
        file_put_contents('C:\xampp\htdocs\controlepcp_sandbox\api\debug_response.json', $response);
        echo "\n✅ Resposta salva em debug_response.json para análise.\n";
    }
} else {
    echo "❌ Erro: HTTP $http_code ou resposta vazia\n";
    if (strlen($response) > 0) {
        echo "Primeiros 500 chars: " . substr($response, 0, 500) . "\n";
    }
}

?>
