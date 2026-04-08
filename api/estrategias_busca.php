<?php
/**
 * Tentar encontrar OP 0201055 SEM filtro, ou com código 23599
 */

$codi_url = 'http://192.168.8.246:8080';
$codi_user = 'Aghiggi';
$codi_pass = '@Ag0351@';

// Estratégia 1: Sem filtro - obter TODAS as OPs
echo "=== ESTRATÉGIA 1: Buscar SEM filtro (todas as OPs) ===\n";
$url1 = $codi_url . '/action/ger/webservice/rest/relatorioQtdeOrdemProducao?pageSize=100';

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url1);
curl_setopt($ch, CURLOPT_USERPWD, "$codi_user:$codi_pass");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 60);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($http_code == 200 && strlen($response) > 0) {
    // Procurar por 0201055
    if (strpos($response, '0201055') !== false) {
        echo "✅ ENCONTRADO: 0201055 está na resposta!\n\n";
        
        // Extrair contexto ao redor de 0201055
        $pos = strpos($response, '0201055');
        $context = substr($response, max(0, $pos - 300), 600);
        echo "Contexto:\n";
        echo $context . "\n\n";
    } else {
        echo "❌ 0201055 não encontrado sem filtro\n";
        echo "O endpoint retorna muitos dados (166 páginas conforme vimos antes)\n\n";
    }
    
    // Tentar filtro por código
    echo "=== ESTRATÉGIA 2: Filtro codigoOrdemProducao=23599 ===\n";
    $url2 = $codi_url . '/action/ger/webservice/rest/relatorioQtdeOrdemProducao?codigoOrdemProducao=23599&pageSize=100';
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url2);
    curl_setopt($ch, CURLOPT_USERPWD, "$codi_user:$codi_pass");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response2 = curl_exec($ch);
    $http_code2 = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    echo "URL: $url2\n";
    echo "HTTP: $http_code2\n";
    echo "Tamanho: " . strlen($response2) . " bytes\n\n";
    
    if ($http_code2 == 200 && strlen($response2) > 10) {
        echo "✅ Resposta recebida. Conteúdo:\n";
        echo substr($response2, 0, 1000) . "\n";
    }
} else {
    echo "❌ Erro: HTTP $http_code\n";
}

?>
