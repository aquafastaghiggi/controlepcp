<?php
/**
 * Análise completa do endpoint sequenciamentoProducao
 */

$codi_url = 'http://192.168.8.246:8080';
$codi_user = 'Aghiggi';
$codi_pass = '@Ag0351@';

// Buscar OP 201055 especificamente
$url = $codi_url . '/action/ger/webservice/rest/sequenciamentoProducao?ordem=201055';

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_USERPWD, "$codi_user:$codi_pass");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($http_code == 200) {
    $data = json_decode($response, true);
    
    echo "🎯 ENDPOINT: /sequenciamentoProducao\n";
    echo "Parâmetro: ordem=201055\n";
    echo "Total de registros: " . $data['totalCount'] . "\n\n";
    
    foreach ($data['data'] as $i => $seq) {
        echo "─────────────────────────────────────────\n";
        echo "Sequenciamento #" . ($i + 1) . "\n";
        echo "─────────────────────────────────────────\n";
        
        // Mostrar todos os campos
        foreach ($seq as $campo => $valor) {
            if (is_array($valor)) {
                echo "$campo:\n";
                foreach ($valor as $k => $v) {
                    echo "  - $k: " . json_encode($v) . "\n";
                }
            } else {
                echo "$campo: " . json_encode($valor) . "\n";
            }
        }
        echo "\n";
    }
    
    // Análise especial
    echo "\n" . str_repeat("=", 60) . "\n";
    echo "📊 ANÁLISE PARA OP 201055\n";
    echo str_repeat("=", 60) . "\n";
    
    $soma_quantidade = 0;
    $soma_percentual = 0;
    
    foreach ($data['data'] as $seq) {
        if (isset($seq['quantidade'])) {
            $soma_quantidade += $seq['quantidade'];
            echo "Quantidade: " . $seq['quantidade'] . "\n";
        }
        if (isset($seq['percentual'])) {
            $soma_percentual += $seq['percentual'];  
            echo "Percentual: " . $seq['percentual'] . "%\n";
        }
    }
    
    echo "\n✓ TOTAL QUANTIDADE: $soma_quantidade\n";
    echo "✓ SOMA PERCENTUAIS: $soma_percentual%\n";
    
} else {
    echo "Erro: HTTP $http_code\n";
    echo $response;
}

?>
