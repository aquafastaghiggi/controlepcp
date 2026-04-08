<?php
/**
 * Teste do endpoint CORRETO: ordemProducaoQuantidade
 * Este endpoint retorna quantidades de produção (boas, rejeitos, etc)
 */

$codi_url = 'http://192.168.8.246:8080';
$codi_user = 'Aghiggi';
$codi_pass = '@Ag0351@';

echo "=== TESTANDO: /ordemProducaoQuantidade ===\n\n";

// Montar a URL do endpoint CORRETO
$url = $codi_url . '/action/ger/webservice/rest/ordemProducaoQuantidade';

// Adicionar parâmetro de busca pela ordem 201055
$url .= '?~neq~ordem=0201055'; // ou tente sem operador

echo "URL: $url\n\n";

// Fazer request com curl
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_USERPWD, "$codi_user:$codi_pass");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

echo "Conectando a CODI...\n";
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

echo "HTTP Code: $http_code\n";
echo "Erro curl: " . ($error ? $error : "Nenhum") . "\n\n";

if ($response) {
    $data = json_decode($response, true);
    
    if (json_last_error() === JSON_ERROR_NONE) {
        echo "✓ JSON válido\n";
        echo "Campos retornados:\n";
        
        if (isset($data['data']) && is_array($data['data'])) {
            if (count($data['data']) > 0) {
                $primeiro = $data['data'][0];
                echo "\nPrimeiro registro (OP 201055):\n";
                foreach ($primeiro as $campo => $valor) {
                    $valor_display = is_array($valor) || is_object($valor) 
                        ? json_encode($valor) 
                        : $valor;
                    echo "  ✓ $campo: $valor_display\n";
                }
                
                echo "\n📊 RESUMO DA ESTRUTURA:\n";
                echo json_encode($primeiro, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            } else {
                echo "Nenhum resultado encontrado\n";
            }
        }
    } else {
        echo "✗ JSON inválido: " . json_last_error_msg() . "\n";
        echo "Response: " . substr($response, 0, 500) . "\n";
    }
} else {
    echo "✗ Sem resposta de CODI\n";
}

?>
