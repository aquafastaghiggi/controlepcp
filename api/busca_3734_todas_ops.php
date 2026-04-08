<?php
/**
 * Buscar 3734 em TODAS as OPs da CODI
 */

$codi_url = 'http://192.168.8.246:8080';
$codi_user = 'Aghiggi';
$codi_pass = '@Ag0351@';

echo "=== BUSCANDO 3734 EM TODAS AS OPs ===\n\n";

// Buscar todas as OPs
$url = $codi_url . '/action/ger/webservice/rest/ordemProducao?page=1&pageSize=500';

echo "Buscando OPs (página 1, 500 itens)...\n";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_USERPWD, "$codi_user:$codi_pass");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($http_code == 200) {
    $data = json_decode($response, true);
    
    if (isset($data['data'])) {
        echo "Encontradas " . count($data['data']) . " OPs\n\n";
        
        $encontrou = false;
        
        foreach ($data['data'] as $op) {
            // Procurar por 3734 em qualquer campo
            $json_op = json_encode($op);
            
            if (strpos($json_op, '3734') !== false) {
                echo "✅ ENCONTRADO 3734 NA OP:\n";
                echo json_encode($op, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
                $encontrou = true;
            }
            
            // Também mostrar 201055 se existir
            if (isset($op['ordem']) && $op['ordem'] == '0201055') {
                echo "📍 OP 0201055 encontrada:\n";
                echo json_encode($op, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
            }
        }
        
        if (!$encontrou) {
            echo "\n❌ 3734 NÃO ENCONTRADO EM NENHUMA OP\n";
            echo "\nPrimeiras 3 OPs para referência:\n";
            for ($i = 0; $i < 3 && $i < count($data['data']); $i++) {
                echo "\nOP " . ($i+1) . ":\n";
                echo json_encode($data['data'][$i], JSON_PRETTY_PRINT) . "\n";
            }
        }
    } else {
        echo "Sem dados\n";
    }
} else {
    echo "❌ HTTP $http_code\n";
}

?>
