<?php
/**
 * Buscar OP 0201055 fazendo iteração nas páginas
 */

$codi_url = 'http://192.168.8.246:8080';
$codi_user = 'Aghiggi';
$codi_pass = '@Ag0351@';

echo "Buscando OP 0201055 no sistema CODI...\n";
echo "Este código já funcionou em session anterior (encontrou em página 47)\n\n";

$encontrada = false;
$codigo_op = null;

// Procurar até página 50
for ($pagina = 1; $pagina <= 50; $pagina++) {
    $url = $codi_url . '/action/ger/webservice/rest/ordemProducao?pagina=' . $pagina;
    
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
        
        if (isset($data['data']) && is_array($data['data'])) {
            foreach ($data['data'] as $op) {
                if ($op['ordem'] == '0201055') {
                    $encontrada = true;
                    $codigo_op = $op['codigoOrdemProducao'];
                    echo "✓ OP 0201055 encontrada na página $pagina\n";
                    echo "  codigoOrdemProducao: $codigo_op\n";
                    echo "  quantidade (planejado): " . $op['quantidade'] . "\n";
                    echo "  status: " . $op['status'] . "\n\n";
                    break 2;
                }
            }
        }
    }
    
    echo ".";
}

if ($encontrada && $codigo_op) {
    // Agora buscar dados de Evento/Apontamento consolidado para OP
    echo "\n\nBuscando dados de execução (Evento Consolidado) para codigoOrdemProducao=$codigo_op\n";
    echo "Este endpoint deve conter quantidade de boas produzidas\n\n";
    
    // Tentar diferentes parâmetros
    $tentativas = [
        'codigoOrdemProducao' => '/action/ger/webservice/rest/relatorioEventoConsolidado?codigoOrdemProducao=' . $codigo_op . '&itensPorPagina=1',
        'ordemProducao' => '/action/ger/webservice/rest/relatorioEventoConsolidado?ordemProducao=0201055&itensPorPagina=1',
    ];
    
    foreach ($tentativas as $nome_param => $endpoint) {
        echo "\nTentativa: $nome_param\n";
        echo "URL: " . $codi_url . $endpoint . "\n";
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $codi_url . $endpoint);
        curl_setopt($ch, CURLOPT_USERPWD, "$codi_user:$codi_pass");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        echo "HTTP Code: $http_code\n";
        
        if ($http_code == 200) {
            $data = json_decode($response, true);
            if (isset($data['data']) && is_array($data['data']) && count($data['data']) > 0) {
                echo "✓ Dados encontrados!\n";
                echo json_encode($data['data'][0], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
                break;
            } else {
                echo "Sem dados\n";
            }
        }
    }
    
} else {
    echo "\n\n❌ OP 0201055 não encontrada\n";
}

?>
