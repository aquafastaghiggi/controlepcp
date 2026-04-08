<?php
/**
 * Buscar OP 0201055 especificamente no relatorioQtdeOrdemProducao
 * e somar o qtdeBons
 */

$codi_url = 'http://192.168.8.246:8080';
$codi_user = 'Aghiggi';
$codi_pass = '@Ag0351@';

echo "=== BUSCANDO OP 0201055 NO relatorioQtdeOrdemProducao ===\n\n";

$url = $codi_url . '/action/ger/webservice/rest/relatorioQtdeOrdemProducao?ordem=0201055&pageSize=500';

echo "URL: $url\n\n";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_USERPWD, "$codi_user:$codi_pass");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 15);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP: $http_code\n\n";

if ($http_code == 200) {
    $data = json_decode($response, true);
    
    if (isset($data['data'])) {
        echo "Total de registros: " . count($data['data']) . "\n\n";
        
        $total_boas = 0;
        $registros_encontrados = [];
        
        foreach ($data['data'] as $reg) {
            // Verificar se é da OP 0201055
            if (isset($reg['ordemProducao']['ordem']) && $reg['ordemProducao']['ordem'] == '0201055') {
                $qtde_boas = floatval($reg['qtdeBons'] ?? 0);
                $total_boas += $qtde_boas;
                
                $registros_encontrados[] = [
                    'operacao' => $reg['operacao'],
                    'qtdeBons' => $qtde_boas,
                    'status' => $reg['status']
                ];
            }
        }
        
        if (count($registros_encontrados) > 0) {
            echo "✅ OP 0201055 encontrada!\n\n";
            echo "📊 OPERAÇÕES:\n";
            echo str_repeat("-", 70) . "\n";
            
            foreach ($registros_encontrados as $op) {
                echo "Operação: " . $op['operacao'] . " | Boas: " . $op['qtdeBons'] . " | Status: " . $op['status'] . "\n";
            }
            
            echo str_repeat("-", 70) . "\n";
            echo "\n✅ TOTAL DE BOAS (REALIZADO): $total_boas\n";
            
            if ($total_boas == 3734) {
                echo "✅✅✅ ENCONTRADO EXATAMENTE 3734!\n";
            }
        } else {
            echo "❌ OP 0201055 não encontrada nos registros\n";
            echo "\nPrimeiros registros retornados:\n";
            for ($i = 0; $i < 3 && $i < count($data['data']); $i++) {
                $r = $data['data'][$i];
                echo json_encode($r, JSON_PRETTY_PRINT) . "\n";
            }
        }
    }
} else {
    echo "❌ HTTP $http_code\n";
    echo substr($response, 0, 300) . "\n";
}

?>
