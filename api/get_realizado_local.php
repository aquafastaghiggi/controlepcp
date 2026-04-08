<?php
/**
 * Usar o endpoint /api_integrated.php para obter realizado
 */

echo "=== BUSCANDO REALIZADO VIA API LOCAL ===\n\n";

$url = 'http://localhost/controlepcp/api_integrated.php?action=detalhe_op&op=201055';

echo "URL: $url\n\n";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP: $http_code\n";
echo "Tamanho: " . strlen($response) . " bytes\n\n";

if ($http_code == 200 && strlen($response) > 10) {
    $data = json_decode($response, true);
    
    if ($data) {
        echo "RESPOSTA COMPLETA:\n";
        echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
        
        // Extrair dados de realizado
        if (isset($data['realizado']['dados'])) {
            $realizado = $data['realizado']['dados'];
            echo "\n========== REALIZADO ==========\n";
            echo "OP: " . ($realizado['ordem'] ?? '?') . "\n";
            echo "Quantidade: " . ($realizado['quantidade'] ?? '?') . "\n";
            echo "Status: " . ($realizado['status'] ?? '?') . "\n";
            echo "================================\n";
        }
    } else {
        echo "Erro ao decodificar JSON:\n";
        echo substr($response, 0, 500) . "\n";
    }
} else {
    echo "Erro HTTP ou resposta vazia\n";
    echo substr($response, 0, 300) . "\n";
}

?>
