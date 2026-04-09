<?php
/**
 * Buscar quando ordem 201055 foi finalizada
 */

$codi_url = 'http://192.168.8.246:8080';
$codi_user = 'Aghiggi';
$codi_pass = '@Ag0351@';

echo "=== BUSCANDO FINALIZAÇÃO DA ORDEM 201055 ===\n\n";

// Tentar buscar ordem na CODI com mais detalhes
$url = $codi_url . '/action/ger/webservice/rest/ordemProducao?ordem=0201055&pageSize=50';

echo "Buscando em: /ordemProducao\n\n";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_USERPWD, "$codi_user:$codi_pass");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 15);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($http_code == 200) {
    $data = json_decode($response, true);

    if ($data && isset($data['data'])) {
        echo "✅ Ordem encontrada\n\n";

        echo "DADOS COMPLETOS:\n";
        echo str_repeat("=", 80) . "\n";
        echo json_encode($data['data'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
        echo str_repeat("=", 80) . "\n\n";

        // Procurar por campos de finalização
        if (count($data['data']) > 0) {
            $ordem = $data['data'][0];

            echo "📋 INFORMAÇÕES PRINCIPAIS:\n";
            echo str_repeat("-", 80) . "\n";

            foreach ($ordem as $chave => $valor) {
                if (is_scalar($valor)) {
                    echo "$chave: $valor\n";
                }
            }
        }
    } else {
        echo "Resposta:\n";
        echo json_encode($data, JSON_PRETTY_PRINT) . "\n";
    }
} else {
    echo "Erro: HTTP $http_code\n";
}

?>
