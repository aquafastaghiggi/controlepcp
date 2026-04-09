<?php
/**
 * DEBUG: Mostra TODOS os campos retornados pela API CODI para OP 201055
 */

$codi_url = 'http://192.168.8.246:8080';
$codi_user = 'Aghiggi';
$codi_pass = '@Ag0351@';

function buscar_codi_order($op) {
    global $codi_url, $codi_user, $codi_pass;
    
    $op_variants = [$op, str_pad($op, 7, '0', STR_PAD_LEFT)];
    
    for ($page = 1; $page <= 100; $page++) {
        $url = $codi_url . '/action/ger/webservice/rest/ordemProducao?page=' . $page . '&pageSize=500';
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_USERPWD, "$codi_user:$codi_pass");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code === 200 && !empty($response)) {
            $response_utf8 = iconv('ISO-8859-1', 'UTF-8', $response);
            $data = json_decode($response_utf8, true);
            
            if (isset($data['data']) && is_array($data['data'])) {
                foreach ($data['data'] as $order) {
                    foreach ($op_variants as $variant) {
                        if (isset($order['ordem']) && $order['ordem'] == $variant) {
                            return $order;
                        }
                    }
                }
            }
        }
    }
    
    return null;
}

header('Content-Type: text/html; charset=utf-8');

echo "<h1>🔍 DEBUG: Campos retornados pela CODI para OP 201055</h1>";

$ordem = buscar_codi_order('201055');

if (!$ordem) {
    echo "<p style='color: red;'><strong>❌ OP 201055 não encontrada na CODI</strong></p>";
    exit;
}

echo "<p style='color: green;'><strong>✅ OP encontrada!</strong></p>";

echo "<h2>Todos os campos:</h2>";
echo "<table border='1' cellpadding='10' cellspacing='0' style='border-collapse: collapse;'>";
echo "<tr style='background-color: #f0f0f0;'>";
echo "<th>Campo</th>";
echo "<th style='max-width: 500px;'>Valor</th>";
echo "<th>Tipo</th>";
echo "</tr>";

foreach ($ordem as $key => $value) {
    $tipo = gettype($value);
    if (is_array($value) || is_object($value)) {
        $valor_display = '<pre>' . json_encode($value, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . '</pre>';
    } else {
        $valor_display = htmlspecialchars((string)$value);
    }
    
    // Destacar campos que parecem ser percentuais
    $highlight = '';
    if (stripos($key, 'percent') !== false || stripos($key, 'taxa') !== false || 
        stripos($key, 'eficiencia') !== false || stripos($key, 'executa') !== false) {
        $highlight = 'background-color: #ffffcc;';
    }
    
    echo "<tr style='{$highlight}'>";
    echo "<td><strong>" . htmlspecialchars($key) . "</strong></td>";
    echo "<td>" . $valor_display . "</td>";
    echo "<td>" . htmlspecialchars($tipo) . "</td>";
    echo "</tr>";
}

echo "</table>";

echo "<h2>JSON completo:</h2>";
echo "<pre>" . json_encode($ordem, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "</pre>";
?>
