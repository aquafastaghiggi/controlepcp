<?php
/**
 * Encontrar 3734 em relatorioEvento
 */

$codi_url = 'http://192.168.8.246:8080';
$codi_user = 'Aghiggi';
$codi_pass = '@Ag0351@';

$url = $codi_url . '/action/ger/webservice/rest/relatorioEvento?ordem=0201055&pageSize=500';

echo "Buscando em: $url\n\n";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_USERPWD, "$codi_user:$codi_pass");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 60);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($http_code == 200 && strlen($response) > 0) {
    // Procurar todas as ocorrências de 3734
    $matches = [];
    preg_match_all('/3734/', $response, $matches, PREG_OFFSET_CAPTURE);
    
    echo "✅ Encontradas " . count($matches[0]) . " ocorrências de 3734\n\n";
    
    // Mostrar contexto de cada ocorrência (250 chars antes e depois)
    foreach ($matches[0] as $i => $match) {
        $pos = $match[1];
        $context_start = max(0, $pos - 250);
        $context_end = min(strlen($response), $pos + 250);
        $context = substr($response, $context_start, $context_end - $context_start);
        
        echo str_repeat("-", 80) . "\n";
        echo "Ocorrência #" . ($i + 1) . ":\n";
        echo str_repeat("-", 80) . "\n";
        echo "..." . htmlspecialchars($context) . "...\n\n";
    }
    
    // JSON decode attempt
    $data = json_decode($response, true);
    if ($data) {
        echo "\n\n" . str_repeat("=", 80) . "\n";
        echo "ANÁLISE JSON:\n";
        echo str_repeat("=", 80) . "\n";
        
        if (isset($data['totalCount'])) {
            echo "Total de eventos: " . $data['totalCount'] . "\n";
            echo "Total de páginas: " . $data['totalPages'] . "\n\n";
        }
        
        if (isset($data['data']) && is_array($data['data'])) {
            $eventos_com_3734 = 0;
            
            foreach ($data['data'] as $evento) {
                // Procurar 3734 em qualquer campo
                $json_evento = json_encode($evento);
                if (strpos($json_evento, '3734') !== false) {
                    $eventos_com_3734++;
                    
                    // Mostrar alguns campos principais
                    echo "✅ Evento #$eventos_com_3734:\n";
                    if (isset($evento['codigoEvento'])) echo "   Código: " . $evento['codigoEvento'] . "\n";
                    if (isset($evento['estado'])) echo "   Estado: " . $evento['estado'] . "\n";
                    if (isset($evento['somatorioQtdeBoasItem'])) echo "   Qtde Boas Item: " . $evento['somatorioQtdeBoasItem'] . "\n";
                    if (isset($evento['somatorioQtdeRuinsItem'])) echo "   Qtde Ruims Item: " . $evento['somatorioQtdeRuinsItem'] . "\n";
                    echo "\n";
                    
                    if ($eventos_com_3734 >= 10) break; // Mostrar apenas os primeiros 10
                }
            }
            
            if ($eventos_com_3734 > 0) {
                echo "\n✅ Total de eventos com '3734': $eventos_com_3734\n";
            }
        }
    }
} else {
    echo "Erro: HTTP $http_code\n";
}

?>
