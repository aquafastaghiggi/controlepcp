<?php
/**
 * Testar variações de endpoints para encontrar o que retorna quantidade de boas
 * baseado no manual de integração CODI
 */

$codi_url = 'http://192.168.8.246:8080';
$codi_user = 'Aghiggi';
$codi_pass = '@Ag0351@';

// Variações possíveis de endpoints para quantidade/execution/produção realizada
$endpoints_teste = [
    // Variações óbvias
    'ordemProducaoQuantidade' => '/action/ger/webservice/rest/ordemProducaoQuantidade',
    'ordemProducaoExecucao' => '/action/ger/webservice/rest/ordemProducaoExecucao',
    'ordemProducaoProduzida' => '/action/ger/webservice/rest/ordemProducaoProduzida',
    
    // Dados de execução
    'apontamentosProducao_GET' => '/action/ger/webservice/rest/apontamentosProducao',
    'apontamentoProducaoConsolidado' => '/action/ger/webservice/rest/apontamentoProducaoConsolidado',
    
    // Relatórios
    'relatorioProducao' => '/action/ger/webservice/rest/relatorioProducao',
    'relatorioProduzido' => '/action/ger/webservice/rest/relatorioProduzido',
    'relatorioQuantidadeProduzida' => '/action/ger/webservice/rest/relatorioQuantidadeProduzida',
    
    // Performance/Execução
    'relatorioExecucao' => '/action/ger/webservice/rest/relatorioExecucao',
    'relatorioExecucaoOrdem' => '/action/ger/webservice/rest/relatorioExecucaoOrdem',
    
    // Consolidação
    'relatorioEventoConsolidadoProduzido' => '/action/ger/webservice/rest/relatorioEventoConsolidadoProduzido',
];

echo "=== TESTANDO ENDPOINTS PARA QUANTIDADE DE BOAS ===\n\n";

foreach ($endpoints_teste as $nome => $endpoint) {
    echo "Testando: $nome\n";
    echo "URL: $endpoint\n";
    
    $url_completa = $codi_url . $endpoint . '?page=1&pageSize=5';
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url_completa);
    curl_setopt($ch, CURLOPT_USERPWD, "$codi_user:$codi_pass");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $status = "";
    if ($http_code == 200) {
        $status = "✅ HTTP 200 - FUNCIONOU!";
    } elseif ($http_code == 400) {
        $status = "❌ HTTP 400 - Interface não configurada";
    } elseif ($http_code == 404) {
        $status = "❌ HTTP 404 - Endpoint não existe";
    } else {
        $status = "⚠️  HTTP $http_code";
    }
    
    echo "Status: $status\n";
    
    // Se funcionou, mostrar estrutura
    if ($http_code == 200) {
        $data = json_decode($response, true);
        if (isset($data['data']) && is_array($data['data']) && count($data['data']) > 0) {
            echo "📊 Campos encontrados:\n";
            foreach (array_keys($data['data'][0]) as $campo) {
                echo "   - $campo\n";
            }
        }
    }
    
    echo "\n";
}

echo "\n" . str_repeat("=", 70) . "\n";
echo "📝 RESULTADO:\n";
echo "Se nenhum endpoint funcionou, você precisa:\n";
echo "1. Acessar CODI → Integração → Configurações do Gerente de Integração\n";
echo "2. Verificar qual interface para 'quantidade' ou 'execução' está ativada\n";
echo "3. Usar exatamente aquele nome no endpoint\n";

?>
