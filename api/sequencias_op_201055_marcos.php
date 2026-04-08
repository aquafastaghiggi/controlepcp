<?php
// Teste mais específico: buscar SEQUÊNCIAS da OP 201055

echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║      SEQUÊNCIAS DE PRODUÇÃO - OP 201055 (User: marcos.brun)   ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n\n";

$user = 'marcos.brun';
$pass = 'Eb035611!';

// Primeiro, listar todas as sequências
echo "📋 BUSCANDO TODAS AS SEQUÊNCIAS...\n";
echo "──────────────────────────────────────────────────────────────\n\n";

$url = "http://192.168.8.246:8080/action/ger/webservice/rest/sequenciamentoProducao?pageSize=500&page=1";

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
curl_setopt($ch, CURLOPT_USERPWD, "$user:$pass");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 15);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "Status HTTP: $httpCode\n\n";

if ($httpCode == 200) {
    $data = json_decode($response, true);
    
    if ($data && isset($data['totalCount'])) {
        echo "Total de sequências: " . $data['totalCount'] . "\n";
        echo "Total de páginas: " . ($data['totalPages'] ?? 1) . "\n";
        echo "Sequências nesta página: " . count($data['data'] ?? []) . "\n\n";
        
        // Procurar sequências para a OP 201055 (código 0201055)
        $seq_201055 = [];
        
        if (isset($data['data']) && is_array($data['data'])) {
            foreach ($data['data'] as $seq) {
                if (isset($seq['ordemProducao']['ordem']) && $seq['ordemProducao']['ordem'] == '0201055') {
                    $seq_201055[] = $seq;
                } else if (isset($seq['ordemProducao']['codigoOrdemProducao']) && $seq['ordemProducao']['codigoOrdemProducao'] == 23599) {
                    $seq_201055[] = $seq;
                }
            }
        }
        
        echo "═════════════════════════════════════════════════════════════════\n";
        echo "SEQUÊNCIAS ENCONTRADAS PARA OP 201055: " . count($seq_201055) . "\n";
        echo "═════════════════════════════════════════════════════════════════\n\n";
        
        if (count($seq_201055) > 0) {
            foreach ($seq_201055 as $i => $seq) {
                echo "SEQUÊNCIA " . ($i + 1) . ":\n";
                echo "────────────────────────────────────────────────────────────\n";
                
                $ordem = $seq['ordemProducao'] ?? [];
                $recurso = $seq['recurso'] ?? [];
                
                echo "  Código: " . ($seq['codigoProgramacao'] ?? 'N/A') . "\n";
                echo "  Ordem: " . ($ordem['ordem'] ?? 'N/A') . "\n";
                echo "  Produto: " . ($ordem['item']['nomeItem'] ?? 'N/A') . "\n";
                echo "  Máquina: " . ($recurso['nomeRecurso'] ?? 'N/A') . "\n";
                echo "  Quantidade: " . ($seq['quantidade'] ?? 'N/A') . "\n";
                
                // Campos de data/status
                if (isset($seq['dataInicio'])) {
                    echo "  Data Início: " . $seq['dataInicio'] . "\n";
                }
                if (isset($seq['dataFim'])) {
                    echo "  Data Fim: " . $seq['dataFim'] . "\n";
                }
                if (isset($seq['status'])) {
                    echo "  Status: " . $seq['status'] . "\n";
                }
                if (isset($seq['statusProducao'])) {
                    echo "  Status Produção: " . $seq['statusProducao'] . "\n";
                }
                if (isset($seq['statusSequencia'])) {
                    echo "  Status Sequência: " . $seq['statusSequencia'] . "\n";
                }
                
                // Campos de capacidade
                if (isset($seq['quantidadeProduzida'])) {
                    echo "  Quantidade Produzida: " . $seq['quantidadeProduzida'] . "\n";
                }
                if (isset($seq['tempoProcesso'])) {
                    echo "  Tempo Processo: " . $seq['tempoProcesso'] . " min\n";
                }
                
                echo "\n";
            }
        } else {
            echo "⚠️  Nenhuma sequência encontrada para OP 201055\n\n";
            
            // Listar as primeiras sequências para debug
            echo "Primeiras 3 sequências disponíveis:\n";
            if (isset($data['data']) && count($data['data']) > 0) {
                for ($i = 0; $i < min(3, count($data['data'])); $i++) {
                    $seq = $data['data'][$i];
                    echo "\n  " . ($i + 1) . ". " . 
                         ($seq['ordemProducao']['ordem'] ?? 'N/A') . " - " . 
                         ($seq['ordemProducao']['item']['nomeItem'] ?? 'N/A') . "\n";
                }
            }
        }
    }
} else {
    echo "ERRO HTTP $httpCode na requisição\n";
    echo "Resposta: " . substr($response, 0, 300) . "\n";
}

// Tente também acessar a especificação completa de uma sequência se encontrar
echo "\n";
echo "═════════════════════════════════════════════════════════════════\n";
echo "TESTE DE ENDPOINTS ALTERNATIVOS\n";
echo "═════════════════════════════════════════════════════════════════\n\n";

$endpoints_alt = [
    "Listar com filtro de código de ordem" => "http://192.168.8.246:8080/action/ger/webservice/rest/sequenciamentoProducao?codigoOrdemProducao=23599&pageSize=100",
    "Listar com número de ordem" => "http://192.168.8.246:8080/action/ger/webservice/rest/sequenciamentoProducao?ordem=0201055&pageSize=100",
];

foreach ($endpoints_alt as $nome => $url) {
    echo "Teste: $nome\n";
    echo "URL: $url\n";
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
    curl_setopt($ch, CURLOPT_USERPWD, "$user:$pass");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    echo "Status: $httpCode";
    
    if ($httpCode == 200) {
        $d = json_decode($response, true);
        if ($d && isset($d['totalCount'])) {
            echo " - Total: " . $d['totalCount'] . " registros\n";
        } else {
            echo " - Resposta recebida\n";
        }
    } else {
        echo " - Erro\n";
    }
    
    echo "\n";
}

?>
