<?php
/**
 * Tentar TODOS os endpoints possíveis para encontrar finalização de 201055
 */

$codi_url = 'http://192.168.8.246:8080';
$codi_user = 'Aghiggi';
$codi_pass = '@Ag0351@';

echo "=== PROCURANDO FINALIZAÇÃO EM TODOS OS ENDPOINTS ===\n\n";

$endpoints = [
    'ordemProducao' => '/action/ger/webservice/rest/ordemProducao?ordem=0201055',
    'performance' => '/action/ger/webservice/rest/performance?ordem=0201055',
    'sequenciamentoProducao' => '/action/ger/webservice/rest/sequenciamentoProducao?ordem=0201055',
    'execucao' => '/action/ger/webservice/rest/execucao?ordem=0201055',
    'execucoes' => '/action/ger/webservice/rest/execucoes?ordem=0201055',
    'apontamentosProducao' => '/action/ger/webservice/rest/apontamentosProducao?ordem=0201055',
    'relatorioExecucao' => '/action/ger/webservice/rest/relatorioExecucao?ordem=0201055',
    'relatorioOperacao' => '/action/ger/webservice/rest/relatorioOperacao?ordem=0201055',
    'operacao' => '/action/ger/webservice/rest/operacao?ordem=0201055',
    'operacoesProducao' => '/action/ger/webservice/rest/operacoesProducao?ordem=0201055',
    'ordemProducaoQuantidade' => '/action/ger/webservice/rest/ordemProducaoQuantidade?ordem=0201055',
];

foreach ($endpoints as $nome => $endpoint) {
    echo "📍 $nome\n";
    
    $url = $codi_url . $endpoint;
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_USERPWD, "$codi_user:$codi_pass");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 8);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code == 200) {
        $data = json_decode($response, true);
        
        if ($data && is_array($data) && count($data) > 0) {
            echo "   ✅ HTTP 200 - Dados encontrados\n";
            
            // Mostrar preview dos dados
            $preview = json_encode($data, JSON_UNESCAPED_UNICODE);
            if (strlen($preview) > 150) {
                $preview = substr($preview, 0, 150) . "...";
            }
            echo "   Preview: $preview\n";
            
            // Procurar por campos de status/finalização
            if (isset($data['status'])) {
                echo "   🔍 status: " . $data['status'] . "\n";
            }
            if (isset($data[0]['status'])) {
                echo "   🔍 status[0]: " . $data[0]['status'] . "\n";
            }
        } else {
            echo "   ⚠️  HTTP 200 - Sem dados\n";
        }
    } else {
        echo "   ❌ HTTP $http_code\n";
    }
    
    echo "\n";
}

?>
