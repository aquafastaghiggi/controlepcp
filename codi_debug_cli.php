<?php
/**
 * Script CLI: Mostra todos os campos da OP 201055 na CODI
 */

$codi_url = 'http://192.168.8.246:8080';
$codi_user = 'Aghiggi';
$codi_pass = '@Ag0351@';

echo "\n=== CONECTANDO À CODI ===\n";
echo "URL: {$codi_url}\n";
echo "User: {$codi_user}\n\n";

function buscar_codi_order($op) {
    global $codi_url, $codi_user, $codi_pass;
    
    $op_variants = [$op, str_pad($op, 7, '0', STR_PAD_LEFT)];
    
    echo "Procurando variantes da OP: " . implode(', ', $op_variants) . "\n";
    
    for ($page = 1; $page <= 100; $page++) {
        echo "  Página {$page}...";
        
        $url = $codi_url . '/action/ger/webservice/rest/ordemProducao?page=' . $page . '&pageSize=500';
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_USERPWD, "$codi_user:$codi_pass");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($http_code !== 200) {
            echo " HTTP {$http_code}\n";
            if ($error) echo "    Erro: {$error}\n";
            continue;
        }
        
        if (empty($response)) {
            echo " (vazio)\n";
            continue;
        }
        
        $response_utf8 = iconv('ISO-8859-1', 'UTF-8', $response);
        $data = json_decode($response_utf8, true);
        
        if (!isset($data['data']) || !is_array($data['data'])) {
            echo " (sem 'data')\n";
            continue;
        }
        
        echo " {" . count($data['data']) . " itens}";
        
        foreach ($data['data'] as $order) {
            $op_encontrada = isset($order['ordem']) ? $order['ordem'] : 'SEM CAMPO ORDEM';
            
            foreach ($op_variants as $variant) {
                if (isset($order['ordem']) && $order['ordem'] == $variant) {
                    echo " ✅ ENCONTRADA: {$op_encontrada}\n";
                    return $order;
                }
            }
        }
        echo "\n";
    }
    
    return null;
}

$ordem = buscar_codi_order('201055');

if (!$ordem) {
    echo "\n❌ OP 201055 NÃO ENCONTRADA\n";
    exit(1);
}

echo "\n=== CAMPOS RETORNADOS ===\n\n";

foreach ($ordem as $key => $value) {
    $tipo = gettype($value);
    
    if (is_array($value) || is_object($value)) {
        $valor_str = json_encode($value);
    } else {
        $valor_str = (string)$value;
    }
    
    // Destacar campos interessantes
    $marker = '';
    if (stripos($key, 'percent') !== false || stripos($key, 'taxa') !== false || 
        stripos($key, 'eficiencia') !== false || stripos($key, 'executa') !== false ||
        stripos($key, 'realizado') !== false || stripos($key, 'produzido') !== false) {
        $marker = ' <<< ⭐ POSSÍVEL PERCENTUAL/TAXA ⭐';
    }
    
    printf("%-30s | %-50s | %-10s%s\n", 
        $key, 
        substr($valor_str, 0, 50), 
        $tipo,
        $marker
    );
}

echo "\n=== JSON COMPLETO ===\n";
echo json_encode($ordem, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
echo "\n";
?>
