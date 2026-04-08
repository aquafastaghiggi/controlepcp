<?php
// Testes com usuário marcos.brun (mais permissões)

echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║          TESTES COM USUÁRIO: marcos.brun (Permissões altas)  ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n\n";

$user_marcos = 'marcos.brun';
$pass_marcos = 'Eb035611!';

$endpoints = [
    "Ordem Produção (código 23599)" => [
        "url" => "http://192.168.8.246:8080/action/ger/webservice/rest/ordemProducao/23599",
        "method" => "GET"
    ],
    "Buscar por número 0201055" => [
        "url" => "http://192.168.8.246:8080/action/ger/webservice/rest/ordemProducao?ordem=0201055",
        "method" => "GET"
    ],
    "Sequenciamento Produção" => [
        "url" => "http://192.168.8.246:8080/action/ger/webservice/rest/sequenciamentoProducao?pageSize=100&page=1",
        "method" => "GET"
    ],
    "Relatório Evento (Consolidado - com filtro)" => [
        "url" => "http://192.168.8.246:8080/action/ger/webservice/rest/relatorioEventoConsolidado?codOrdemProducao=23599&pageSize=50",
        "method" => "GET"
    ],
    "Status de Produção" => [
        "url" => "http://192.168.8.246:8080/action/ger/webservice/rest/statusProducao?codOrdemProducao=23599",
        "method" => "GET"
    ],
    "Acompanhamento de Ordem" => [
        "url" => "http://192.168.8.246:8080/action/ger/webservice/rest/acompanhamentoOrdem/23599",
        "method" => "GET"
    ]
];

foreach ($endpoints as $nome => $config) {
    echo "\n" . str_repeat("─", 70) . "\n";
    echo "📡 $nome\n";
    echo str_repeat("─", 70) . "\n";
    
    echo "URL: " . $config['url'] . "\n";
    echo "Método: " . $config['method'] . "\n";
    echo "Usuário: $user_marcos\n\n";
    
    $ch = curl_init($config['url']);
    curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
    curl_setopt($ch, CURLOPT_USERPWD, "$user_marcos:$pass_marcos");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    
    if ($config['method'] === 'POST') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['codOrdemProducao' => 23599]));
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    echo "Status HTTP: $httpCode";
    
    if ($error) {
        echo " ❌ ERRO: $error\n";
    } else {
        echo " ✓\n\n";
        
        if ($httpCode == 200 || $httpCode == 400) {
            // Tentar decodificar
            $data = json_decode($response, true);
            
            if ($data !== null) {
                if (isset($data['error'])) {
                    echo "⚠️  Erro na resposta: " . $data['error'] . "\n";
                    if (isset($data['message'])) {
                        echo "   Mensagem: " . $data['message'] . "\n";
                    }
                } else if (isset($data['data']) && is_array($data['data'])) {
                    echo "✅ Dados encontrados: " . count($data['data']) . " registros\n";
                    
                    if (count($data['data']) > 0) {
                        echo "\nPrimeiro registro:\n";
                        $primeiro = $data['data'][0];
                        foreach (array_slice($primeiro, 0, 5) as $k => $v) {
                            $val_display = is_array($v) ? "array(" . count($v) . ")" : substr($v, 0, 50);
                            echo "  $k: $val_display\n";
                        }
                    }
                } else if (isset($data['codigoOrdemProducao'])) {
                    echo "✅ Ordem encontrada\n";
                    echo "  Código: " . $data['codigoOrdemProducao'] . "\n";
                    echo "  Ordem: " . $data['ordem'] . "\n";
                    echo "  Status: " . $data['status'] . "\n";
                    echo "  Quantidade: " . $data['quantidade'] . "\n";
                } else if (isset($data['totalCount'])) {
                    echo "✅ Total de registros: " . $data['totalCount'] . "\n";
                    echo "   Total de páginas: " . ($data['totalPages'] ?? 'N/A') . "\n";
                } else {
                    echo "Resposta: " . substr(json_encode($data), 0, 200) . "...\n";
                }
            } else {
                echo "Resposta (primeiras 300 chars): " . substr($response, 0, 300) . "...\n";
            }
        } else {
            echo "Resposta: " . substr($response, 0, 200) . "...\n";
        }
    }
}

echo "\n\n";
echo "═════════════════════════════════════════════════════════════════\n";
echo "                    TESTES COMPLETADOS\n";
echo "═════════════════════════════════════════════════════════════════\n";

?>
