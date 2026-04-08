<?php
/**
 * Buscar OP 201055 na API CODI com credenciais corretas
 */

$base_url = 'http://192.168.8.246:8080';
$username = 'Aghiggi';
$password = '@Ag0351@';
$endpoint = '/action/ger/webservice/rest/ordemProducao';

$url = $base_url . $endpoint . '?page=1&pageSize=500';

echo "=== BUSCANDO OP 201055 NA CODI ===\n";
echo "URL: $url\n";
echo "User: $username\n\n";

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_USERPWD, "$username:$password");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

echo "Status HTTP: $http_code\n";

if ($http_code === 200 && !empty($response)) {
    $data = json_decode($response, true);
    
    echo "Total de OPs na CODI: {$data['totalCount']}\n";
    echo "Página atual: {$data['currentPage']}\n\n";
    
    // Procurar OP 201055
    $found = false;
    
    if (isset($data['data']) && is_array($data['data'])) {
        foreach ($data['data'] as $order) {
            if (isset($order['ordem']) && $order['ordem'] == '201055') {
                echo "✅ OP 201055 ENCONTRADA!\n\n";
                echo json_encode($order, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
                
                // Extrair dados importantes
                echo "\n=== INFORMAÇÕES IMPORTANTES ===\n";
                echo "Código da OP: " . ($order['codigoOrdemProducao'] ?? 'N/A') . "\n";
                echo "Número da OP: " . ($order['ordem'] ?? 'N/A') . "\n";
                echo "Status: " . ($order['status'] ?? 'N/A') . "\n";
                echo "Quantidade: " . ($order['quantidade'] ?? 'N/A') . "\n";
                echo "Última Alteração: " . ($order['ultimaAlteracao'] ?? 'N/A') . "\n";
                
                $found = true;
                break;
            }
        }
    }
    
    if (!$found) {
        echo "❌ OP 201055 NÃO encontrada nesta página\n\n";
        
        // Mostrar estrutura dos dados
        echo "=== ESTRUTURA DOS DADOS ===\n";
        if (isset($data['data']) && count($data['data']) > 0) {
            $first = $data['data'][0];
            echo "Chaves disponíveis:\n";
            echo "- " . implode("\n- ", array_keys($first)) . "\n\n";
            
            echo "=== PRIMEIRAS 10 OPs ===\n";
            foreach (array_slice($data['data'], 0, 10) as $order) {
                $op = $order['ordem'] ?? 'N/A';
                $status = $order['status'] ?? 'N/A';
                $qtd = $order['quantidade'] ?? 'N/A';
                echo "OP: $op | Status: $status | Qtd: $qtd\n";
            }
        }
    }
} else {
    echo "❌ Erro ao conectar. Status: $http_code\n";
    echo "Response: " . substr($response, 0, 500) . "\n";
}

curl_close($ch);
?>
