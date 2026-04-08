<?php
/**
 * Status completo da ordem 201055
 */

$codi_url = 'http://192.168.8.246:8080';
$codi_user = 'Aghiggi';
$codi_pass = '@Ag0351@';

echo "=== STATUS COMPLETO DA ORDEM 201055 ===\n\n";

// Buscar CODI
$url = $codi_url . '/action/ger/webservice/rest/ordemProducao?ordem=0201055';

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_USERPWD, "$codi_user:$codi_pass");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($http_code == 200) {
    $data = json_decode($response, true);
    
    if ($data && isset($data['data'][0])) {
        $ordem = $data['data'][0];
        
        echo "🔴 STATUS ATUAL: " . strtoupper($ordem['status']) . "\n\n";
        
        echo "📋 DADOS:\n";
        echo str_repeat("-", 60) . "\n";
        echo "OP (CODI): " . $ordem['ordem'] . "\n";
        echo "Código Interno: " . $ordem['codigoOrdemProducao'] . "\n";
        echo "Quantidade: " . $ordem['quantidade'] . " " . ($ordem['item']['unidadeMedida']['nomeUnidadeMedida'] ?? 'un') . "\n";
        echo "Produto: " . $ordem['item']['nomeItem'] . "\n";
        echo "Última Alteração: " . $ordem['ultimaAlteracao'] . "\n";
        echo str_repeat("-", 60) . "\n";
        
        if ($ordem['status'] == 'INICIADO') {
            echo "\n⚠️  ORDEM AINDA EM PRODUÇÃO\n";
            echo "Não foi finalizada ainda!\n";
        } elseif ($ordem['status'] == 'FINALIZADO') {
            echo "\n✅ ORDEM FOI FINALIZADA\n";
            echo "Data (última mudança): " . $ordem['ultimaAlteracao'] . "\n";
        }
    }
}

?>
