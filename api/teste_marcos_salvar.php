<?php
// Teste simplificado - salvar respostas em arquivos para análise

echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║   TESTE COM MARCOS.BRUN - Salvando respostas em arquivos      ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n\n";

$user = 'marcos.brun';
$pass = 'Eb035611!';

$testes = [
    "sequencias_todas" => "http://192.168.8.246:8080/action/ger/webservice/rest/sequenciamentoProducao?pageSize=100&page=1",
    "sequencias_filtro_ordem" => "http://192.168.8.246:8080/action/ger/webservice/rest/sequenciamentoProducao?codigoOrdemProducao=23599&pageSize=100",
    "relatorio_em_dados" => "http://192.168.8.246:8080/action/ger/webservice/rest/relatorioEventoDados?codOrdemProducao=23599&pageSize=100&page=1",
];

foreach ($testes as $nome => $url) {
    echo "Testando: $nome\n";
    echo "URL: $url\n";
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
    curl_setopt($ch, CURLOPT_USERPWD, "$user:$pass");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    echo "Status: $httpCode\n";
    
    $filename = "response_marcos_$nome.json";
    $bytes = file_put_contents($filename, $response);
    
    echo "Salvado em: $filename (" . number_format($bytes, 0, '.', '.') . " bytes)\n";
    
    // Tentar decodificar
    $response_utf8 = iconv('UTF-8', 'UTF-8//IGNORE', $response);
    $data = json_decode($response_utf8, true);
    
    if ($data) {
        if (isset($data['totalCount'])) {
            echo "✓ Total de registros: " . $data['totalCount'] . "\n";
        } else if (isset($data['error'])) {
            echo "✗ Erro: " . $data['error'] . "\n";
        } else if (is_array($data) && count($data) > 0) {
            echo "✓ Dados encontrados (" . count($data) . " itens)\n";
            // Mostrar primeiro item
            $first = reset($data);
            if (is_array($first)) {
                echo "  Primeira chave: " . implode(', ', array_slice(array_keys($first), 0, 3)) . "...\n";
            }
        }
    } else {
        echo "Não foi possível decodificar JSON\n";
        // Mostrar primeiras linhas
        echo "Primeiros 200 chars: " . substr($response, 0, 200) . "...\n";
    }
    
    echo "\n";
}

echo "\n";
echo "═════════════════════════════════════════════════════════════════\n";
echo "Testes completados! Arquivos salvos em response_marcos_*.json\n";
echo "═════════════════════════════════════════════════════════════════\n";

// Agora vamos analisar manualmente o arquivo de sequências
echo "\n\n📊 ANÁLISE DO ARQUIVO response_marcos_sequencias_filtro_ordem.json\n";
echo "────────────────────────────────────────────────────────────────\n\n";

if (file_exists('response_marcos_sequencias_filtro_ordem.json')) {
    $content = file_get_contents('response_marcos_sequencias_filtro_ordem.json');
    $content_utf8 = iconv('UTF-8', 'UTF-8//IGNORE', $content);
    $data = json_decode($content_utf8, true);
    
    if ($data && isset($data['totalCount'])) {
        echo "✓ Total encontrado: " . $data['totalCount'] . " sequências\n";
        
        if (isset($data['data']) && is_array($data['data'])) {
            echo "✓ Itens neste resultado: " . count($data['data']) . "\n\n";
            
            if (count($data['data']) > 0) {
                echo "PRIMEIRA SEQUÊNCIA ENCONTRADA:\n";
                $seq = $data['data'][0];
                
                echo json_encode($seq, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
            }
        }
    } else {
        echo "Não foi decodificado corretamente\n";
    }
}

?>
