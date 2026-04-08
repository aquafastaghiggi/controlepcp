<?php
/**
 * Teste completo da API CODI - procurar dados de 2026
 */

$auth = 'Aghiggi:@Ag0351@';
$base_url = 'http://192.168.8.246:8080';

echo "=== TESTE API CODI 2026 ===\n\n";

// Teste 1: Calendário sem filtro
echo "1. Calendário - Sem filtro (page=1)\n";
$ch = curl_init($base_url . '/action/ger/webservice/rest/calendarioFabril?page=1');
curl_setopt($ch, CURLOPT_USERPWD, $auth);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
$response = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$response = @iconv('ISO-8859-1', 'UTF-8//IGNORE', $response);
echo "   Status: $code | Size: " . strlen($response) . "\n";
echo "   Response snippet: " . substr($response, 0, 150) . "...\n";
$json = json_decode($response, true);
if (is_array($json)) {
    echo "   ✓ Array com " . count($json) . " items\n";
    // Descobrir a estrutura
    if (count($json) > 0) {
        $first = reset($json);
        echo "   Tipo de item: " . gettype($first) . "\n";
        if (is_array($first) && isset($first['codigoCalendario'])) {
            echo "   ✓ Tem codigoCalendario\n";
        }
    }
}
echo "\n";

// Teste 2: Com filtro de data 2026
echo "2. Calendário - Com filtro 2026-04\n";
$url = $base_url . '/action/ger/webservice/rest/calendarioFabril?page=1&dataInicio=2026-04-01&dataFim=2026-04-07';
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_USERPWD, $auth);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$response = @iconv('ISO-8859-1', 'UTF-8//IGNORE', $response);
echo "   Status: $code | Size: " . strlen($response) . "\n";
$json = json_decode($response, true);
if (is_array($json)) {
    echo "   ✓ Array com " . count($json) . " items\n";
    
    // Procurar por 2026
    $found_2026 = 0;
    foreach ($json as $item) {
        $json_str = json_encode($item);
        if (strpos($json_str, '2026') !== false) {
            $found_2026++;
        }
    }
    echo "   Items com 2026: $found_2026\n";
    
    // Mostrar um item
    if (count($json) > 0) {
        $first = reset($json);
        echo "   Primeiro item:\n";
        if (is_array($first)) {
            echo "     Keys: " . implode(", ", array_keys($first)) . "\n";
            if (isset($first['calendarData'])) {
                echo "     Data do calendário: " . $first['calendarData'] . "\n";
            }
        } else {
            echo "     Tipo: " . gettype($first) . " | Value: $first\n";
        }
    }
}
echo "\n";

// Teste 3: Com limite maior
echo "3. Calendário - Aumentando limit\n";
$url = $base_url . '/action/ger/webservice/rest/calendarioFabril?page=1&limit=500&dataInicio=2026-04-01';
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_USERPWD, $auth);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "   Status: $code | Size: " . strlen($response) . "\n";
$response = @iconv('ISO-8859-1', 'UTF-8//IGNORE', $response);
$json = json_decode($response, true);
if (is_array($json)) {
    echo "   ✓ Array com " . count($json) . " items\n";
} else {
    echo "   ✗ Não é array\n";
}

?>
