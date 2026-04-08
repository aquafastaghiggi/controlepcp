<?php
/**
 * Tentar buscar com POST ou verificar se há cache
 */

$codi_url = 'http://192.168.8.246:8080';
$codi_user = 'Aghiggi';
$codi_pass = '@Ag0351@';

echo "=== ESTRATÉGIA FINAL: BUSCAR TODO HISTÓRICO ===\n\n";

// GET direto no relatorioEvento
$url = $codi_url . '/action/ger/webservice/rest/relatorioEvento?codOrdemProducao=23599&pageSize=2';

echo "URL: $url\n\n";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_USERPWD, "$codi_user:$codi_pass");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
curl_setopt($ch, CURLOPT_TIMEOUT, 20);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

// Callback para monitorar progresso
$received = 0;
curl_setopt($ch, CURLOPT_WRITEFUNCTION, function($ch, $data) use (&$received) {
    $received += strlen($data);
    echo "Recebidos: $received bytes...\n";
    return strlen($data);
});

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);
curl_close($ch);

echo "\nHTTP: $http_code\n";

if ($curl_error) {
    echo "Erro: $curl_error\n";
} elseif ($http_code == 200) {
    echo "✅ Conectado\n";
    echo "Resposta recebida: " . strlen($response) . " bytes\n";
    
    // Salvar em arquivo
    file_put_contents('/tmp/evento_201055.json', $response);
    echo "Salvo em arquivo temporário\n";
    
    // Tentar decodificar
    $data = json_decode($response, true);
    if ($data) {
        echo "\n✅ JSON decodificado\n";
        if (isset($data['data'])) {
            echo "Eventos encontrados: " . count($data['data']) . "\n";
            
            if (count($data['data']) > 0) {
                echo "\nÚLTIMO EVENTO:\n";
                $ultimo = end($data['data']);
                echo json_encode($ultimo, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
            }
        }
    } else {
        echo "❌ Erro ao decodificar JSON\n";
        echo "Primeiros 300 chars:\n";
        echo substr($response, 0, 300) . "\n";
    }
}

?>
