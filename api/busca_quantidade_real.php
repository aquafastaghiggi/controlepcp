<?php
/**
 * Estratégia nova: buscar qualquer OP que tenha dados de execução
 * e ver qual endpoint retorna a quantidade de boas produzidas
 */

$codi_url = 'http://192.168.8.246:8080';
$codi_user = 'Aghiggi';
$codi_pass = '@Ag0351@';

echo "=== ESTRATÉGIA NOVA: BUSCAR PELA QUANTIDADE, NÃO PERCENTUAL ===\n\n";

// Buscar primeira OP com dados
echo "1️⃣  Buscando primeira OP da CODI...\n";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $codi_url . '/action/ger/webservice/rest/ordemProducao?pagina=1&itensPorPagina=5');
curl_setopt($ch, CURLOPT_USERPWD, "$codi_user:$codi_pass");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$data = json_decode($response, true);

if (isset($data['data'][0])) {
    $op = $data['data'][0];
    $ordem = $op['ordem'];
    $codigo_op = $op['codigoOrdemProducao'];
    
    echo "✓ OP encontrada: $ordem (código: $codigo_op)\n";
    echo "  Planejado: " . $op['quantidade'] . "\n\n";
    
    // 2️⃣  Buscar dados de Evento Consolidado (deve ter quantidade real)
    echo "2️⃣  Buscando Evento Consolidado para esta OP...\n";
    
    $url_evento = $codi_url . '/action/ger/webservice/rest/relatorioEventoConsolidado?ordem=' . $ordem;
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url_evento);
    curl_setopt($ch, CURLOPT_USERPWD, "$codi_user:$codi_pass");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    echo "HTTP Code: $http_code\n\n";
    
    if ($http_code == 200) {
        $data_evento = json_decode($response, true);
        
        if (isset($data_evento['data']) && is_array($data_evento['data'])) {
            echo "✓ Encontrados " . count($data_evento['data']) . " registros de evento\n\n";
            
            if (count($data_evento['data']) > 0) {
                echo "📊 CAMPOS DO EVENTO CONSOLIDADO (primeiro registro):\n";
                echo str_repeat("-", 80) . "\n";
                
                $evento = $data_evento['data'][0];
                foreach ($evento as $campo => $valor) {
                    if (is_scalar($valor)) {
                        echo sprintf("%-40s : %s\n", $campo, $valor);
                    } elseif (is_array($valor)) {
                        echo sprintf("%-40s : [array com " . count($valor) . " itens]\n", $campo);
                        if (strpos(strtolower($campo), 'quant') !== false || strpos(strtolower($campo), 'boas') !== false) {
                            echo "  └─ " . json_encode($valor) . "\n";
                        }
                    } elseif (is_object($valor)) {
                        echo sprintf("%-40s : [object]\n", $campo);
                    }
                }
                
                // Procurar campos que pareçam ser quantidade
                echo "\n🔍 CAMPOS SUSPEITOS DE QUANTIDADE:\n";
                echo str_repeat("-", 80) . "\n";
                
                foreach ($evento as $campo => $valor) {
                    $campo_lower = strtolower($campo);
                    if ((strpos($campo_lower, 'quant') !== false || 
                         strpos($campo_lower, 'soma') !== false ||
                         strpos($campo_lower, 'boas') !== false ||
                         strpos($campo_lower, 'produzida') !== false ||
                         strpos($campo_lower, 'realizado') !== false ||
                         strpos($campo_lower, 'executado') !== false) &&
                        is_numeric($valor)) {
                        echo "✓ $campo = $valor\n";
                    }
                }
            }
        } else {
            echo "Sem dados na resposta\n";
            echo substr($response, 0, 500) . "\n";
        }
    } else {
        echo "Erro HTTP $http_code\n";
        echo substr($response, 0, 500) . "\n";
    }
    
    // 3️⃣  Tentar Evento (consolidação por evento/apontamento)
    echo "\n\n3️⃣  Testando endpoint /relatorioEvento (sem consolidação)...\n";
    
    $url_evento_raw = $codi_url . '/action/ger/webservice/rest/relatorioEvento?ordem=' . $ordem . '&itensPorPagina=10';
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url_evento_raw);
    curl_setopt($ch, CURLOPT_USERPWD, "$codi_user:$codi_pass");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    echo "HTTP Code: $http_code\n";
    
    if ($http_code == 200) {
        $data_evento_raw = json_decode($response, true);
        
        if (isset($data_evento_raw['data']) && count($data_evento_raw['data']) > 0) {
            echo "✓ Encontrados " . count($data_evento_raw['data']) . " eventos\n\n";
            
            echo "CAMPOS DO EVENTO (sem consolidação):\n";
            $evento = $data_evento_raw['data'][0];
            foreach ($evento as $campo => $valor) {
                if (is_scalar($valor)) {
                    echo "  $campo: $valor\n";
                }
            }
            
            // Somar quantidade de boas/rejeitados
            echo "\n📊 SOMATÓRIOS:\n";
            $total_boas = 0;
            $total_rejeitados = 0;
            
            foreach ($data_evento_raw['data'] as $evento) {
                foreach ($evento as $campo => $valor) {
                    $campo_lower = strtolower($campo);
                    if (strpos($campo_lower, 'boas') !== false && is_numeric($valor)) {
                        $total_boas += floatval($valor);
                    }
                    if (strpos($campo_lower, 'rejeita') !== false && is_numeric($valor)) {
                        $total_rejeitados += floatval($valor);
                    }
                }
            }
            
            echo "Total Boas: $total_boas\n";
            echo "Total Rejeitados: $total_rejeitados\n";
        }
    }
    
}

?>
