<?php
/**
 * Buscar endpoint de OPERAÇÕES da OP 0201055
 * que retorna "Quantidade de boas: 3734"
 */

$codi_url = 'http://192.168.8.246:8080';
$codi_user = 'Aghiggi';
$codi_pass = '@Ag0351@';

echo "=== BUSCANDO ENDPOINT DE OPERAÇÕES ===\n";
echo "Procurando: Operações com quantidadeBoas = 3734\n\n";

// Variações de endpoints para operações
$endpoints_teste = [
    'operacao' => '/action/ger/webservice/rest/operacao?ordem=0201055&pageSize=500',
    'operacaoOrdemProducao' => '/action/ger/webservice/rest/operacaoOrdemProducao?ordem=0201055&pageSize=500',
    'operacoesProducao' => '/action/ger/webservice/rest/operacoesProducao?ordem=0201055&pageSize=500',
    'relatorioOperacao' => '/action/ger/webservice/rest/relatorioOperacao?ordem=0201055&pageSize=500',
    'operacaoQuantidade' => '/action/ger/webservice/rest/operacaoQuantidade?ordem=0201055&pageSize=500',
    'operacaoExecucao' => '/action/ger/webservice/rest/operacaoExecucao?ordem=0201055&pageSize=500',
];

foreach ($endpoints_teste as $nome => $endpoint) {
    echo "Testando: $nome\n";
    
    $url = $codi_url . $endpoint;
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_USERPWD, "$codi_user:$codi_pass");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    echo "HTTP: $http_code | ";
    
    if ($http_code == 200) {
        $data = json_decode($response, true);
        
        if ($data && isset($data['data'])) {
            $count = count($data['data']);
            echo "✅ Encontrados $count registros\n";
            
            if ($count > 0) {
                // Procurar por 3734
                foreach ($data['data'] as $i => $op) {
                    $json_str = json_encode($op);
                    
                    if (strpos($json_str, '3734') !== false) {
                        echo "\n✅✅✅ ENCONTRADO 3734!\n\n";
                        echo "📊 REGISTRO:\n";
                        echo str_repeat("-", 70) . "\n";
                        echo json_encode($op, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
                        echo str_repeat("-", 70) . "\n";
                        
                        // Procurar pelo nome do campo
                        echo "\n🔍 CAMPOS ENCONTRADOS:\n";
                        foreach ($op as $k => $v) {
                            if (is_scalar($v)) {
                                echo "  $k: " . $v . "\n";
                            }
                        }
                        exit;
                    }
                }
                
                echo "(Sem 3734 nos registros)\n";
                echo "Primeiro registro:\n";
                echo json_encode($data['data'][0], JSON_PRETTY_PRINT) . "\n";
            }
        } else {
            echo "Sem dados\n";
        }
    } elseif ($http_code == 400) {
        echo "❌ HTTP 400 - Interface não configurada\n";
    } elseif ($http_code == 404) {
        echo "❌ HTTP 404 - Endpoint não existe\n";
    } else {
        echo "❌ HTTP $http_code\n";
    }
    
    echo "\n";
}

echo "\n❌ Nenhum endpoint de operações funcionou\n";
echo "Pode ser que precise ativar a interface 'operacao' ou similar no Gerente de Integração\n";

?>
