<?php
/**
 * Testar APENAS CONEXAO com CODI
 * Sem parâmetros, sem buscar dados
 */

$codi_url = 'http://192.168.8.246:8080';
$codi_user = 'Aghiggi';
$codi_pass = '@Ag0351@';

// URL base SEM parâmetros
$url = $codi_url . '/action/ger/webservice/rest/relatorioEvento';

echo "=== CONECTANDO NA CODI ===\n\n";

echo "📍 URL: $url\n";
echo "👤 User: $codi_user\n";
echo "🔐 Pass: $codi_pass\n\n";

echo str_repeat("=", 70) . "\n";
echo "CONECTANDO...\n";
echo str_repeat("=", 70) . "\n\n";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_USERPWD, "$codi_user:$codi_pass");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);
curl_close($ch);

echo "RESULTADO:\n";
echo str_repeat("-", 70) . "\n";

if ($curl_error) {
    echo "❌ ERRO: $curl_error\n";
} else {
    echo "✅ CONECTADO\n";
    echo "Status HTTP: $http_code\n\n";
    
    if ($http_code == 200) {
        echo "✅ Autenticação: OK\n";
        echo "✅ Servidor: RESPONDENDO\n";
        echo "\n🎯 Pronto pra usar no Postman!\n";
    } elseif ($http_code == 401) {
        echo "❌ ERRO 401: Autenticação falhou\n";
    } else {
        echo "Status: $http_code\n";
    }
}

echo "\n" . str_repeat("=", 70) . "\n";
echo "\n📋 COPIA ISSO NO POSTMAN:\n\n";
echo "Method:    GET\n";
echo "URL:       $url\n";
echo "Auth:      Basic Auth\n";
echo "Username:  $codi_user\n";
echo "Password:  $codi_pass\n";

?>
