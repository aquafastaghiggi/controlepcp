<?php
/**
 * Tester para o endpoint consulta_op_com_codi.php
 */

$op = '201055';
$url = "http://localhost/controlepcp/consulta_op_com_codi.php?op={$op}&data_inicio=2026-03-27&data_fim=2026-03-28";

echo "Testando: {$url}\n\n";

try {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($http_code !== 200) {
        echo "HTTP {$http_code}\n";
        if ($error) echo "Erro: {$error}\n";
    }
    
    if (!empty($response)) {
        echo $response;
    } else {
        echo "Resposta vazia\n";
    }
    
} catch (Exception $e) {
    echo "Erro: " . $e->getMessage();
}
?>
