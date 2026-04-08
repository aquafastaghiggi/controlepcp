<?php
// Script para extrair informações detalhadas via marcos.brun

echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║        EXTRAÇÃO DETALHADA COM USUÁRIO: marcos.brun            ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n\n";

$user = 'marcos.brun';
$pass = 'Eb035611!';

// 1. Verificar ordem diretamente com endpoints específicos
echo "1️⃣  TESTE 1: Dados diretos da Ordem 23599\n";
echo str_repeat("─", 70) . "\n";

$endpoints = [
    "Detalhes da Ordem" => "http://192.168.8.246:8080/action/ger/webservice/rest/ordemProducao/23599",
    "Busca por Order ID" => "http://192.168.8.246:8080/action/ger/webservice/rest/ordemProducao?id=23599",
    "Busca por número" => "http://192.168.8.246:8080/action/ger/webservice/rest/ordemProducao?numero=0201055",
];

foreach ($endpoints as $nome => $url) {
    echo "\n$nome:\n";
    echo "URL: $url\n";
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
    curl_setopt($ch, CURLOPT_USERPWD, "$user:$pass");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    echo "Status: $httpCode\n";
    
    if ($httpCode == 200) {
        $response_utf8 = iconv('UTF-8', 'UTF-8//IGNORE', $response);
        $data = json_decode($response_utf8, true);
        
        if ($data) {
            // Mostrar resumo
            if (isset($data['codigoOrdemProducao'])) {
                echo "✓ Ordem: " . $data['codigoOrdemProducao'] . "\n";
                echo "  Status: " . $data['status'] . "\n";
                echo "  Quantidade: " . $data['quantidade'] . " " . ($data['item']['unidadeMedida']['nomeUnidadeMedida'] ?? '') . "\n";
                
                // Verificar campos adicionais que possam indicar conclusão
                if (isset($data['dataInicio'])) echo "  Data Início: " . $data['dataInicio'] . "\n";
                if (isset($data['dataFim'])) echo "  Data Fim: " . $data['dataFim'] . "\n";
                if (isset($data['dataConclusao'])) echo "  Data Conclusão: " . $data['dataConclusao'] . "\n";
                if (isset($data['percentualConclusao'])) echo "  % Conclusão: " . $data['percentualConclusao'] . "\n";
                if (isset($data['statusProducao'])) echo "  Status Produção: " . $data['statusProducao'] . "\n";
                
            } else if (isset($data['data']) && is_array($data['data'])) {
                echo "✓ Encontrado em lista: " . count($data['data']) . " resultado(s)\n";
                
                if (count($data['data']) > 0) {
                    $primeiro = $data['data'][0];
                    echo "  Primeiro: " . ($primeiro['codigoOrdemProducao'] ?? 'N/A') . " - " . 
                         ($primeiro['status'] ?? 'N/A') . "\n";
                }
            } else {
                echo "✓ Resposta diferente\n";
            }
        } else {
            echo "✗ Não decodificado\n";
        }
    }
}

echo "\n\n";
echo "2️⃣  TESTE 2: Buscar com filtros avançados\n";
echo str_repeat("─", 70) . "\n";

$filtros = [
    "Filtro status" => "http://192.168.8.246:8080/action/ger/webservice/rest/ordemProducao?status=FINALIZADO&pageSize=100",
    "Filtro status2" => "http://192.168.8.246:8080/action/ger/webservice/rest/ordemProducao?status=CONCLUIDO&pageSize=100",
    "Listar por intervalo" => "http://192.168.8.246:8080/action/ger/webservice/rest/ordemProducao?pageSize=100&page=1",
];

foreach ($filtros as $nome => $url) {
    echo "\n$nome:\n";
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
    curl_setopt($ch, CURLOPT_USERPWD, "$user:$pass");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    echo "Status: $httpCode\n";
    
    if ($httpCode == 200) {
        $response_utf8 = iconv('UTF-8', 'UTF-8//IGNORE', $response);
        $data = json_decode($response_utf8, true);
        
        if ($data && isset($data['totalCount'])) {
            echo "Total encontrado: " . $data['totalCount'] . "\n";
            
            // Procurar pela 201055
            if (isset($data['data'])) {
                $match_201055 = array_filter($data['data'], fn($o) => 
                    ($o['codigoOrdemProducao'] ?? null) == 23599 ||
                    ($o['ordem'] ?? null) == '0201055'
                );
                
                if (count($match_201055) > 0) {
                    echo "✓ OP 201055 encontrada neste filtro!\n";
                    $ordem = reset($match_201055);
                    echo "  Status: " . ($ordem['status'] ?? 'N/A') . "\n";
                }
            }
        }
    }
}

echo "\n\n";
echo "3️⃣  TESTE 3: Endpoints admin/configuração\n";
echo str_repeat("─", 70) . "\n";

$admin_endpoints = [
    "Monitoramento" => "http://192.168.8.246:8080/action/ger/webservice/rest/monitoramento?codOrdemProducao=23599",
    "Rastreamento" => "http://192.168.8.246:8080/action/ger/webservice/rest/rastreamento?codOrdemProducao=23599",
];

foreach ($admin_endpoints as $nome => $url) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
    curl_setopt($ch, CURLOPT_USERPWD, "$user:$pass");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    echo "$nome: HTTP $httpCode\n";
}

?>
