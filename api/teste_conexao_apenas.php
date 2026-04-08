<?php
/**
 * Testar APENAS CONEXAO com CODI via Postman
 * Sem buscar dados, só verifica se conecta
 */

$codi_url = 'http://192.168.8.246:8080';
$codi_user = 'Aghiggi';
$codi_pass = '@Ag0351@';

// Endpoint simples para testar conexão
$url = $codi_url . '/action/ger/webservice/rest/relatorioEvento?ordem=0201055&pageSize=1';

echo "=== TESTANDO CONEXÃO COM CODI (SEM BUSCAR DADOS) ===\n\n";

echo "URL: $url\n";
echo "User: $codi_user\n";
echo "Pass: $codi_pass\n\n";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_USERPWD, "$codi_user:$codi_pass");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
// Receber apenas os primeiros 100 bytes (pra não sobrecarregar)
// curl_setopt($ch, CURLOPT_BINARYTRANSFER, true);
// curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);

// Fazer GET normal
$started = false;
curl_setopt($ch, CURLOPT_WRITEFUNCTION, function($curl, $data) use (&$started) {
    if (!$started) {
        echo "✅ Recebendo dados do servidor...\n";
        $started = true;
    }
    // Retornar 0 para parar o download após começar
    return 0;
});

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);
curl_close($ch);

echo str_repeat("=", 70) . "\n";
echo "RESULTADO DA CONEXÃO:\n";
echo str_repeat("=", 70) . "\n\n";

if ($curl_error) {
    echo "❌ ERRO DE CONEXÃO: $curl_error\n";
} else {
    echo "✅ CONEXÃO ESTABELECIDA\n\n";
    echo "HTTP Status Code: $http_code\n";
    
    if ($http_code == 200) {
        echo "✅ Autenticação: OK\n";
        echo "✅ Endpoint: RESPONDENDO\n\n";
        echo "Você pode usar no Postman! ✓\n";
    } elseif ($http_code == 401) {
        echo "❌ Autenticação: FALHOU\n";
        echo "Verifique user/senha\n";
    } elseif ($http_code == 0) {
        echo "❌ Nenhuma resposta do servidor\n";
    } else {
        echo "⚠️  HTTP $http_code\n";
    }
}

echo "\n" . str_repeat("=", 70) . "\n";
echo "\n📋 PARA USAR NO POSTMAN:\n";
echo str_repeat("-", 70) . "\n";
echo "Method: GET\n";
echo "URL: $url\n";
echo "Auth Type: Basic Auth\n";
echo "Username: $codi_user\n";
echo "Password: $codi_pass\n";

?>
