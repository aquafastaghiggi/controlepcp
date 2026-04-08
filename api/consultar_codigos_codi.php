<?php
// Consultar pelo código interno na API de ordens

$urls = [
    "ID direto" => "http://192.168.8.246:8080/action/ger/webservice/rest/ordemProducao/23599",
    "Via filtro" => "http://192.168.8.246:8080/action/ger/webservice/rest/ordemProducao?pageSize=1000&codOrdemProducao=23599"
];

foreach ($urls as $nome => $url) {
    echo "\n=== TENTATIVA: $nome ===\n";
    echo "URL: $url\n";
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
    curl_setopt($ch, CURLOPT_USERPWD, 'Aghiggi:@Ag0351@');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    echo "Status HTTP: $httpCode\n";
    
    if ($httpCode == 200) {
        $data = json_decode($response, true);
        if ($data) {
            // Se for um array (busca com filtro), pegar primeiro item
            if (is_array($data) && isset($data['data'])) {
                echo "Total encontrado: " . count($data['data']) . "\n";
                if (count($data['data']) > 0) {
                    echo "\nPrimeiro item:\n";
                    echo json_encode($data['data'][0], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
                }
            } else if (isset($data['codigo'])) {
                // Se for um objeto direto
                echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
            } else {
                echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
            }
        }
    } else {
        echo "Resposta: " . substr($response, 0, 200) . "\n";
    }
}

// Tentar listar todas as ordens com filtro de código interno
echo "\n\n=== LISTANDO TODAS AS ORDENS COM PAGINAÇÃO ===\n";
$url = "http://192.168.8.246:8080/action/ger/webservice/rest/ordemProducao?pageSize=100&page=1";
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
curl_setopt($ch, CURLOPT_USERPWD, 'Aghiggi:@Ag0351@');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 15);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "Status: $httpCode\n";
if ($httpCode == 200) {
    $data = json_decode($response, true);
    if (isset($data['totalCount'])) {
        echo "Total de ordens: " . $data['totalCount'] . "\n";
        echo "Total de páginas: " . $data['totalPages'] . "\n";
        echo "Primeiros 3 itens:\n";
        for ($i = 0; $i < min(3, count($data['data'])); $i++) {
            echo "\n" . ($i+1) . ". Código: " . $data['data'][$i]['codigo'] . 
                 " | Ordem: " . $data['data'][$i]['ordem'] . 
                 " | Status: " . $data['data'][$i]['status'] . "\n";
        }
    }
}
?>
