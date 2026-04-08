<?php
/**
 * Buscar OP 0201055 de TODAS as formas possíveis
 */

$codi_url = 'http://192.168.8.246:8080';
$codi_user = 'Aghiggi';
$codi_pass = '@Ag0351@';

echo "=== BUSCANDO OP 0201055 DE TODAS AS FORMAS ===\n\n";

// Variações de busca
$variações = [
    'ordem=0201055' => '/action/ger/webservice/rest/ordemProducao?ordem=0201055',
    'ordem=201055' => '/action/ger/webservice/rest/ordemProducao?ordem=201055',
    'codOrdem=0201055' => '/action/ger/webservice/rest/ordemProducao?codOrdem=0201055',
    'codOrdem=201055' => '/action/ger/webservice/rest/ordemProducao?codOrdem=201055',
    
    // Tentar com página específica
    'page=1 (procurrando ao longo das páginas)' => '/action/ger/webservice/rest/ordemProducao?page=1&pageSize=500',
    'page=2' => '/action/ger/webservice/rest/ordemProducao?page=2&pageSize=500',
    'page=3' => '/action/ger/webservice/rest/ordemProducao?page=3&pageSize=500',
];

foreach ($variações as $nome => $endpoint) {
    echo "Tentativa: $nome\n";
    
    $url = $codi_url . $endpoint;
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_USERPWD, "$codi_user:$codi_pass");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    echo "HTTP: $http_code | ";
    
    if ($http_code == 200) {
        $data = json_decode($response, true);
        
        if ($data && isset($data['data'])) {
            // Procurar pela OP 0201055 ou variações
            foreach ($data['data'] as $op) {
                $ordem = isset($op['ordem']) ? $op['ordem'] : '';
                
                if ($ordem == '0201055' || $ordem == '201055' || $ordem == 201055) {
                    echo "✅ ENCONTRADA!\n\n";
                    echo "DADOS COMPLETOS DA OP 0201055:\n";
                    echo str_repeat("-", 70) . "\n";
                    echo json_encode($op, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                    echo "\n" . str_repeat("-", 70) . "\n\n";
                    exit;
                }
            }
            echo "Não encontrada nesta página\n";
        } else {
            echo "Sem dados\n";
        }
    } else {
        echo "Erro\n";
    }
    
    echo "\n";
}

echo "\n❌ OP 0201055 não encontrada em nenhum endpoint\n";

// Tentativa final: Procurar em todos os eventos/apontamentos
echo "\n\nTentando endpoints de eventos...\n";

$eventos_endpoints = [
    'relatorioEvento' => '/action/ger/webservice/rest/relatorioEvento?ordem=0201055&pageSize=500',
    'relatorioEventoConsolidado' => '/action/ger/webservice/rest/relatorioEventoConsolidado?ordem=0201055&pageSize=500',
];

foreach ($eventos_endpoints as $nome => $endpoint) {
    echo "\n$nome:\n";
    
    $url = $codi_url . $endpoint;
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_USERPWD, "$codi_user:$codi_pass");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    echo "HTTP: $http_code\n";
    
    if ($http_code == 200) {
        $data = json_decode($response, true);
        
        if ($data && isset($data['data']) && count($data['data']) > 0) {
            echo "✅ Encontrados " . count($data['data']) . " registros!\n";
            echo "PRIMEIRO REGISTRO:\n";
            echo json_encode($data['data'][0], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
        }
    } elseif ($http_code == 500) {
        echo "HTTP 500 - Server error\n";
    } elseif ($http_code == 400) {
        echo "HTTP 400 - Interface não está ativada\n";
    }
}

?>
