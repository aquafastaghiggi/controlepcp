<?php
/**
 * Testar endpoint de Sequenciamento de Produção
 * que pode conter dados de execução da OP
 */

$codi_url = 'http://192.168.8.246:8080';
$codi_user = 'Aghiggi';
$codi_pass = '@Ag0351@';

echo "=== TESTANDO: /sequenciamentoProducao ===\n";
echo "Este endpoint pode ter dados de execução por recurso/período\n\n";

$url = $codi_url . '/action/ger/webservice/rest/sequenciamentoProducao?ordem=201055';

echo "URL: $url\n";
echo "Conectando...\n\n";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_USERPWD, "$codi_user:$codi_pass");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

echo "HTTP Code: $http_code\n";

if ($response) {
    $data = json_decode($response, true);
    
    if (json_last_error() === JSON_ERROR_NONE && isset($data['data'])) {
        echo "✓ JSON válido\n";
        echo "Total de registros: " . count($data['data']) . "\n\n";
        
        if (count($data['data']) > 0) {
            $primeiro = $data['data'][0];
            echo "📊 Estrutura do primeiro sequenciamento:\n";
            echo json_encode($primeiro, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
            
            // Procurar campos de quantidade
            echo "\n🔍 CAMPOS DE QUANTIDADE:\n";
            foreach ($primeiro as $k => $v) {
                $k_lower = strtolower($k);
                if (strpos($k_lower, 'quant') !== false || strpos($k_lower, 'produz') !== false) {
                    echo "  ✓ $k: " . json_encode($v) . "\n";
                }
            }
        }
    } else {
        echo "Resposta: " . substr($response, 0, 500) . "\n";
    }
}

?>
