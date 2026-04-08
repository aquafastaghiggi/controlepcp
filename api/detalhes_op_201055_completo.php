<?php
// Verificar detalhes da OP 201055 via API

echo "=== DETALHES COMPLETOS DA OP 201055 ===\n\n";

$auth = 'Aghiggi:@Ag0351@';

// Buscar OP pelo código interno
$url = "http://192.168.8.246:8080/action/ger/webservice/rest/ordemProducao/23599";

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
curl_setopt($ch, CURLOPT_USERPWD, $auth);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode == 200) {
    $data = json_decode($response, true);
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
    
    // Extrair informações importantes
    echo "=== INFORMAÇÕES EXTRAÍDAS ===\n";
    echo "Código OP: " . ($data['codigoOrdemProducao'] ?? 'N/A') . "\n";
    echo "Número OP: " . ($data['ordem'] ?? 'N/A') . "\n";
    echo "Produto: " . ($data['item']['nomeItem'] ?? 'N/A') . "\n";
    echo "Código Item: " . ($data['item']['codItem'] ?? 'N/A') . "\n";
    echo "Status: " . ($data['status'] ?? 'N/A') . "\n";
    echo "Quantidade: " . ($data['quantidade'] ?? 'N/A') . "\n";
    echo "Última Alteração: " . ($data['ultimaAlteracao'] ?? 'N/A') . "\n";
    
    // Agora procurar eventos para este codItem específico
    echo "\n\n=== BUSCANDO EVENTOS PARA ESTE ITEM ===\n";
    
    $codItem = $data['item']['codItem'];
    echo "Procurando eventos para codItem: $codItem\n\n";
    
    // Buscar eventos - precisa ser específico
    // Primeiro, listar sequências
    $ch = curl_init("http://192.168.8.246:8080/action/ger/webservice/rest/sequenciamentoProducao?pageSize=100&page=1");
    curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
    curl_setopt($ch, CURLOPT_USERPWD, $auth);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode == 200) {
        $seq_data = json_decode($response, true);
        if (isset($seq_data['data']) && count($seq_data['data']) > 0) {
            echo "Total de sequências: " . count($seq_data['data']) . "\n";
            
            // Procurar sequências para este item
            $seq_matches = [];
            foreach ($seq_data['data'] as $seq) {
                if (isset($seq['sku']) && $seq['sku'] == $codItem) {
                    $seq_matches[] = $seq;
                }
                if (isset($seq['codigoOrdemProducao']) && $seq['codigoOrdemProducao'] == 23599) {
                    $seq_matches[] = $seq;
                }
            }
            
            echo "Sequências encontradas para este item: " . count($seq_matches) . "\n";
            
            if (count($seq_matches) > 0) {
                echo "\nPrimeira sequência encontrada:\n";
                echo json_encode($seq_matches[0], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
            }
        }
    }
    
} else {
    echo "ERRO: HTTP $httpCode\n";
}

?>
