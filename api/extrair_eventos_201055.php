<?php
// Extrair eventos da OP 201055 corretamente

echo "=== EXTRAINDO EVENTOS DA OP 201055 ===\n\n";

$auth = 'Aghiggi:@Ag0351@';

// Buscar eventos com filtro de código da ordem
$url = "http://192.168.8.246:8080/action/ger/webservice/rest/relatorioEvento?codOrdemProducao=23599&pageSize=100&page=1";

echo "URL: $url\n\n";

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
curl_setopt($ch, CURLOPT_USERPWD, $auth);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 15);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "Status HTTP: $httpCode\n\n";

if ($httpCode == 200) {
    $data = json_decode($response, true);
    
    if ($data === null) {
        echo "ERRO: Falha ao decodificar JSON\n";
        echo "Primeiros 500 chars da resposta:\n";
        echo substr($response, 0, 500) . "\n";
        exit;
    }
    
    echo "Dados decodificados com sucesso\n";
    echo "Keys: " . implode(', ', array_keys($data)) . "\n\n";
    
    if (isset($data['totalCount'])) {
        echo "Total de eventos: " . $data['totalCount'] . "\n";
        echo "Total de páginas: " . $data['totalPages'] . "\n";
        echo "Eventos nesta página: " . count($data['data'] ?? []) . "\n\n";
    }
    
    if (isset($data['data']) && is_array($data['data'])) {
        echo "EVENTOS ENCONTRADOS:\n\n";
        
        foreach ($data['data'] as $i => $evento) {
            echo "=== EVENTO " . ($i + 1) . " ===\n";
            echo "  Código: " . ($evento['codigoEvento'] ?? 'N/A') . "\n";
            echo "  Estado: " . ($evento['estado'] ?? 'N/A') . "\n";
            echo "  Início: " . ($evento['inicio'] ?? 'N/A') . "\n";
            echo "  Fim: " . ($evento['fim'] ?? 'N/A') . "\n";
            echo "  Recurso: " . (isset($evento['grandeza']['recurso']['nomeRecurso']) ? $evento['grandeza']['recurso']['nomeRecurso'] : 'N/A') . "\n";
            
            if (isset($evento['ordens']) && count($evento['ordens']) > 0) {
                foreach ($evento['ordens'] as $j => $ordem) {
                    echo "  Ordem " . ($j + 1) . ":\n";
                    echo "    Ordem: " . ($ordem['ordemProducao']['ordem'] ?? 'N/A') . "\n";
                    echo "    Status da Operação: " . ($ordem['statusOperacao'] ?? 'N/A') . "\n";
                    echo "    Quantidade Produzida: " . ($ordem['quantidadeProduzidaItem'] ?? 'N/A') . "\n";
                }
            }
            
            echo "\n";
        }
        
        // Análise final
        echo "\n=== ANÁLISE DE FINALIZAÇÃO ===\n";
        
        // Procurar último evento
        if (count($data['data']) > 0) {
            $ultimo = $data['data'][count($data['data']) - 1];
            echo "Último evento estado: " . ($ultimo['estado'] ?? 'N/A') . "\n";
            echo "Última data: " . ($ultimo['fim'] ?? 'N/A') . "\n";
            
            // Procurar por estado "PARADA"
            $paradas = array_filter($data['data'], fn($e) => $e['estado'] === 'PARADA');
            echo "Total de eventos com estado PARADA: " . count($paradas) . "\n";
            
            if (count($paradas) > 0) {
                $ultima_parada = end($paradas);
                echo "Última PARADA em: " . ($ultima_parada['fim'] ?? 'N/A') . "\n";
            }
        }
        
    } else {
        echo "Nenhum dado encontrado ou formato diferente\n";
        echo "Resposta completa:\n";
        echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    }
    
} else {
    echo "ERRO HTTP: $httpCode\n";
    echo "Resposta: " . substr($response, 0, 300) . "\n";
}

?>
