<?php
// Buscar eventos - tentando com GET em vez de POST

echo "=== BUSCAR EVENTOS DA OP 201055 (GET com query params) ===\n\n";

$auth = 'Aghiggi:@Ag0351@';

// Tentar GET com filtro na URL
$urls = [
    "Com pageSize" => "http://192.168.8.246:8080/action/ger/webservice/rest/relatorioEvento?pageSize=100&page=1",
    "Com ordem" => "http://192.168.8.246:8080/action/ger/webservice/rest/relatorioEvento?ordem=0201055&pageSize=100",
    "Com codOrdem" => "http://192.168.8.246:8080/action/ger/webservice/rest/relatorioEvento?codOrdemProducao=23599&pageSize=100",
];

foreach ($urls as $nome => $url) {
    echo "Tentativa: $nome\n";
    echo "URL: $url\n";
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
    curl_setopt($ch, CURLOPT_USERPWD, $auth);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    echo "Status: $httpCode\n";
    
    if ($httpCode == 200) {
        $data = json_decode($response, true);
        if ($data) {
            if (isset($data['totalCount'])) {
                echo "✓ Total de eventos: " . $data['totalCount'] . "\n";
                
                // Procurar eventos da OP 201055
                if (isset($data['data']) && count($data['data']) > 0) {
                    $eventos_201055 = array_filter($data['data'], fn($e) => 
                        isset($e['ordens']) && is_array($e['ordens']) &&
                        count($e['ordens']) > 0
                    );
                    
                    echo "Eventos com ordens: " . count($eventos_201055) . "\n";
                    
                    if (count($eventos_201055) > 0) {
                        $primeiro = reset($eventos_201055);
                        echo "\nPrimeiro evento com ordem:\n";
                        echo "  Código Evento: " . ($primeiro['codigoEvento'] ?? 'N/A') . "\n";
                        echo "  Estado: " . ($primeiro['estado'] ?? 'N/A') . "\n";
                        echo "  Início: " . ($primeiro['inicio'] ?? 'N/A') . "\n";
                        echo "  Fim: " . ($primeiro['fim'] ?? 'N/A') . "\n";
                        if (isset($primeiro['ordens']) && count($primeiro['ordens']) > 0) {
                            echo "  Ordem: " . ($primeiro['ordens'][0]['ordemProducao']['ordem'] ?? 'N/A') . "\n";
                        }
                    }
                }
            }
        }
    } else {
        echo "Erro: " . substr($response, 0, 100) . "\n";
    }
    
    echo "\n";
}

// Tentar outro endpoint
echo "\n=== TENTANDO relatorioEventoConsolidado ===\n";

$ch = curl_init("http://192.168.8.246:8080/action/ger/webservice/rest/relatorioEventoConsolidado?pageSize=100&page=1");
curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
curl_setopt($ch, CURLOPT_USERPWD, $auth);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 15);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "Status: $httpCode\n";

if ($httpCode == 200) {
    $data = json_decode($response, true);
    if ($data && isset($data['data'])) {
        echo "Total encontrado: " . count($data['data']) . "\n";
        
        if (count($data['data']) > 0) {
            echo "\nPrimeiros 2 registros:\n";
            for ($i = 0; $i < min(2, count($data['data'])); $i++) {
                echo "\n$i:\n" . json_encode($data['data'][$i], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
            }
        }
    }
} else {
    echo "RESPOSTA: " . substr($response, 0, 300) . "\n";
}

?>
