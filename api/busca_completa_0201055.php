<?php
/**
 * Buscar OP 0201055 em TODA a resposta (todas as páginas)
 * Ou procurar pelo código interno 23599
 */

$codi_url = 'http://192.168.8.246:8080';
$codi_user = 'Aghiggi';
$codi_pass = '@Ag0351@';

echo "=== BUSCANDO OP 0201055 EM TODA RESPOSTA ===\n\n";

// Tentar primeiro com filtro
$url = $codi_url . '/action/ger/webservice/rest/relatorioQtdeOrdemProducao?ordem=0201055&pageSize=100';

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
    
    if (!$data || !isset($data['data'])) {
        echo "❌ Erro ao decodificar JSON\n";
        echo "Primeiros 500 chars: " . substr($response, 0, 500) . "\n";
        exit(1);
    }
    
    echo "Página 1: " . count($data['data']) . " registros\n\n";
    
    $total_boas = 0;
    $encontrado = false;
    
    // Verificar primeira página
    foreach ($data['data'] as $reg) {
        if (isset($reg['ordemProducao']['ordem']) && 
            ($reg['ordemProducao']['ordem'] == '0201055' || 
             $reg['ordemProducao']['codigoOrdemProducao'] == 23599)) {
            
            $encontrado = true;
            echo "✅ ENCONTRADO NA PÁGINA 1!\n";
            echo json_encode($reg, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
            $total_boas += floatval($reg['qtdeBons'] ?? 0);
        }
    }
    
    // Se não encontrou, procurar em outras páginas
    if (!$encontrado && isset($data['totalPages']) && $data['totalPages'] > 1) {
        echo "Não encontrado na página 1. Procurando nas próximas páginas...\n\n";
        
        for ($page = 2; $page <= $data['totalPages'] && $page <= 5; $page++) {
            echo "Página $page... ";
            
            $url_page = $codi_url . '/action/ger/webservice/rest/relatorioQtdeOrdemProducao?ordem=0201055&pageSize=100&page=' . ($page - 1);
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url_page);
            curl_setopt($ch, CURLOPT_USERPWD, "$codi_user:$codi_pass");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 15);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            
            $response = curl_exec($ch);
            curl_close($ch);
            
            $data = json_decode($response, true);
            echo count($data['data']) . " registros\n";
            
            foreach ($data['data'] as $reg) {
                if (isset($reg['ordemProducao']['ordem']) && 
                    ($reg['ordemProducao']['ordem'] == '0201055' || 
                     $reg['ordemProducao']['codigoOrdemProducao'] == 23599)) {
                    
                    $encontrado = true;
                    echo "✅ ENCONTRADO NA PÁGINA $page!\n";
                    echo json_encode($reg, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
                    $total_boas += floatval($reg['qtdeBons'] ?? 0);
                }
            }
        }
    }
    
    if (!$encontrado) {
        echo "\n❌ OP 0201055 / código 23599 não encontrado nas primeiras 5 páginas.\n";
        echo "total de registros: " . count($data['data']) . " na última página\n";
        
        // Mostrar algumas OPs para verificação
        echo "\nAmostra dos dados (primeiras 3 OPs):\n";
        for ($i = 0; $i < 3 && $i < count($data['data']); $i++) {
            echo "OP: " . $data['data'][$i]['ordemProducao']['ordem'] . 
                 " | Código: " . $data['data'][$i]['ordemProducao']['codigoOrdemProducao'] . 
                 " | qtdeBons: " . $data['data'][$i]['qtdeBons'] . "\n";
        }
    }
}

?>
