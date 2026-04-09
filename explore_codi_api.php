<?php
/**
 * Script para explorar endpoints da API CODI
 * e encontrar dados de 2026
 */

$auth = 'Aghiggi:@Ag0351@';
$base_url = 'http://192.168.8.246:8080';

echo "=== EXPLORANDO API CODI ===\n\n";

// Testar diferentes endpoints
$endpoints = [
    '/action/ger/webservice/rest/calendarioFabril',
    '/action/ger/webservice/rest/performance',
    '/action/ger/webservice/rest/recurso',
    '/action/ger/webservice/rest/evento',
    '/action/ger/webservice/rest/events',
    '/action/ger/webservice/rest/execucao',
    '/action/ger/webservice/rest/executions',
];

foreach ($endpoints as $endpoint) {
    echo "Testando: $endpoint\n";
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $base_url . $endpoint . '?page=1',
        CURLOPT_USERPWD => $auth,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 5
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr = curl_error($ch);
    curl_close($ch);
    
    echo "  Status: $httpCode";
    if ($httpCode === 200) {
        $response_converted = @iconv('ISO-8859-1', 'UTF-8//IGNORE', $response);
        $data = json_decode($response_converted, true);
        
        if ($data) {
            $count = 0;
            $dates = [];
            
            if (is_array($data)) {
                $count = count($data);
                
                // Procurar por datas de 2026
                foreach (array_slice($data, 0, 5) as $item) {
                    $json_str = json_encode($item);
                    if (preg_match('/2026/', $json_str)) {
                        $dates[] = "✓ Encontrado 2026";
                        break;
                    }
                }
            }
            
            echo " | Items: $count";
            if (!empty($dates)) {
                echo " | " . implode(" | ", $dates);
            }
            echo "\n";
            
            // Mostrar estrutura da primeira resposta que funciona
            if ($count > 0 && is_array($data)) {
                $first = reset($data);
                if (is_array($first)) {
                    echo "  Campos: " . implode(", ", array_keys($first)) . "\n";
                }
            }
        } else {
            echo " | JSON inválido\n";
        }
    } else {
        echo " | Erro\n";
    }
    echo "\n";
}

// Testar com query parameters
echo "\n=== TESTANDO PARMETROS ===\n\n";

$params = [
    '?page=1&limit=50',
    '?page=1&dataInicio=2026-04-01&dataFim=2026-04-07',
    '?dataInicio=2026-01-01',
    '?from=2026-04-01',
    '?date=2026-04-07',
    '?ano=2026',
    '?year=2026',
];

foreach ($params as $param) {
    echo "Testando calendarioFabril$param\n";
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $base_url . '/action/ger/webservice/rest/calendarioFabril' . $param,
        CURLOPT_USERPWD => $auth,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 5
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200) {
        $response_converted = @iconv('ISO-8859-1', 'UTF-8//IGNORE', $response);
        $data = json_decode($response_converted, true);
        
        $count = is_array($data) ? count($data) : 0;
        echo "  ✓ HTTP $httpCode | Items: $count\n";
        
        if ($count > 0 && is_array($data)) {
            $has_2026 = false;
            foreach (array_slice($data, 0, 3) as $item) {
                $json_str = json_encode($item);
                if (preg_match('/2026/', $json_str)) {
                    $has_2026 = true;
                    break;
                }
            }
            if ($has_2026) {
                echo "  ✓✓ ENCONTRADO DADOS DE 2026!\n";
            }
        }
    } else {
        echo "  ✗ HTTP $httpCode\n";
    }
}

// Explorar estrutura completa de uma resposta
echo "\n=== PRIMEIRA RESPOSTA (CALENDÁRIO) ===\n\n";

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $base_url . '/action/ger/webservice/rest/calendarioFabril?page=1&limit=1',
    CURLOPT_USERPWD => $auth,
    CURLOPT_RETURNTRANSFER => true
]);

$response = curl_exec($ch);
curl_close($ch);

$response_converted = @iconv('ISO-8859-1', 'UTF-8//IGNORE', $response);
$data = json_decode($response_converted, true);

if ($data && is_array($data)) {
    $first = reset($data);
    echo json_encode($first, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
}
?>
