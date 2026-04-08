<?php
/**
 * Extrair quantidade e data da ordem 201055
 */

$codi_url = 'http://192.168.8.246:8080';
$codi_user = 'Aghiggi';
$codi_pass = '@Ag0351@';

// Buscar ordem 201055
$url = $codi_url . '/action/ger/webservice/rest/relatorioEvento?ordem=201055&pageSize=100';

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_USERPWD, "$codi_user:$codi_pass");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 15);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($http_code == 200) {
    $data = json_decode($response, true);
    
    if ($data && isset($data['data'])) {
        $eventos = $data['data'];
        
        echo "=== ORDEM 201055 ===\n\n";
        
        if (count($eventos) > 0) {
            $primeira_data = null;
            $ultima_data = null;
            $total_boas = 0;
            
            foreach ($eventos as $evento) {
                // Capturar primeira data
                if ($primeira_data === null && isset($evento['inicio'])) {
                    $primeira_data = $evento['inicio'];
                }
                
                // Capturar última data
                if (isset($evento['fim'])) {
                    $ultima_data = $evento['fim'];
                }
                
                // Somar quantidade de boas
                if (isset($evento['somatorioQtdeBoasItem'])) {
                    $total_boas += floatval($evento['somatorioQtdeBoasItem']);
                }
            }
            
            echo "📊 DADOS DA ORDEM:\n";
            echo str_repeat("-", 50) . "\n";
            echo "Total de Boas (Item): " . number_format($total_boas, 2, ',', '.') . "\n";
            echo "Primeira Data: $primeira_data\n";
            echo "Última Data: $ultima_data\n";
            echo str_repeat("-", 50) . "\n";
            
            // Também mostrar a quantidade original se tiver
            if (isset($eventos[0]['ordens']) && is_array($eventos[0]['ordens'])) {
                foreach ($eventos[0]['ordens'] as $ordem) {
                    if (isset($ordem['ordemProducao'])) {
                        echo "\n📦 Informações da Ordem:\n";
                        echo "Ordem: " . ($ordem['ordemProducao']['ordem'] ?? '?') . "\n";
                        echo "Quantidade (CODI): " . ($ordem['ordemProducao']['quantidade'] ?? '?') . "\n";
                    }
                }
            }
        }
    }
}

?>
