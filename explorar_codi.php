<?php
/**
 * Explorar dados CODI - Performance, Calendário e Recursos
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');

$baseUrl = 'http://192.168.8.246:8080';
$user = 'Aghiggi';
$pass = '@Ag0351@';

echo "📊 EXPLORANDO DADOS CODI\n";
echo "=" . str_repeat("=", 80) . "\n\n";

function getCodiData($url, $user, $pass) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    
    curl_setopt($ch, CURLOPT_USERPWD, "$user:$pass");
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json'
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode >= 200 && $httpCode < 300) {
        return json_decode($response, true);
    }
    return null;
}

// 1. RECURSOS
echo "1️⃣ RECURSOS\n";
echo "-" . str_repeat("-", 79) . "\n";

$urlRecurso = "$baseUrl/action/ger/webservice/rest/recurso?pageNumber=1&pageSize=100";
$dataRecurso = getCodiData($urlRecurso, $user, $pass);

if ($dataRecurso) {
    $totalCount = $dataRecurso['totalCount'] ?? count($dataRecurso['data'] ?? []);
    echo "Total: $totalCount recursos encontrados\n\n";
    
    if (isset($dataRecurso['data']) && count($dataRecurso['data']) > 0) {
        echo "Campos: " . implode(" | ", array_keys($dataRecurso['data'][0])) . "\n\n";
        
        $limit = min(5, count($dataRecurso['data']));
        for ($i = 0; $i < $limit; $i++) {
            $r = $dataRecurso['data'][$i];
            echo "[$i] " . json_encode($r, JSON_UNESCAPED_UNICODE) . "\n";
        }
    }
}

// 2. CALENDÁRIO FABRIL
echo "\n2️⃣ CALENDÁRIO FABRIL\n";
echo "-" . str_repeat("-", 79) . "\n";

$urlCalendario = "$baseUrl/action/ger/webservice/rest/calendarioFabril?pageNumber=1&pageSize=100";
$dataCalendario = getCodiData($urlCalendario, $user, $pass);

if ($dataCalendario) {
    $totalCount = $dataCalendario['totalCount'] ?? count($dataCalendario['data'] ?? []);
    echo "Total: $totalCount calendários encontrados\n\n";
    
    if (isset($dataCalendario['data']) && count($dataCalendario['data']) > 0) {
        echo "Campos: " . implode(" | ", array_keys($dataCalendario['data'][0])) . "\n\n";
        
        $limit = min(3, count($dataCalendario['data']));
        for ($i = 0; $i < $limit; $i++) {
            $r = $dataCalendario['data'][$i];
            echo "[$i] " . json_encode($r, JSON_UNESCAPED_UNICODE) . "\n";
        }
    }
}

// 3. PERFORMANCE
echo "\n3️⃣ PERFORMANCE\n";
echo "-" . str_repeat("-", 79) . "\n";

$urlPerformance = "$baseUrl/action/ger/webservice/rest/performance?pageNumber=1&pageSize=100";
$dataPerformance = getCodiData($urlPerformance, $user, $pass);

if ($dataPerformance) {
    $totalCount = $dataPerformance['totalCount'] ?? count($dataPerformance['data'] ?? []);
    echo "Total: $totalCount registros de performance\n\n";
    
    if (isset($dataPerformance['data']) && count($dataPerformance['data']) > 0) {
        echo "Campos: " . implode(" | ", array_keys($dataPerformance['data'][0])) . "\n\n";
        
        $limit = min(5, count($dataPerformance['data']));
        for ($i = 0; $i < $limit; $i++) {
            $r = $dataPerformance['data'][$i];
            echo "[$i] " . json_encode($r, JSON_UNESCAPED_UNICODE) . "\n";
        }
    }
}

// 4. Salvar dados para referência
echo "\n4️⃣ Salvando dados localmente...\n";
$allData = [
    'recursos' => $dataRecurso,
    'calendarios' => $dataCalendario,
    'performance' => $dataPerformance,
    'timestamp' => date('Y-m-d H:i:s')
];

file_put_contents(
    __DIR__ . '/codi_dados_exportados.json',
    json_encode($allData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
);
echo "   ✅ Salvo em: codi_dados_exportados.json\n";

echo "\n" . str_repeat("=", 80) . "\n";
echo "\n✅ Exploração concluída!\n";
