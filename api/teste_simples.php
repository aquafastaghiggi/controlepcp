<?php
/**
 * Simples: Buscar uma OP com schedules e procurar na CODI
 */

$db = new PDO("mysql:host=127.0.0.1;dbname=controlepcp_sandbox;charset=utf8mb4", 'root', 'k7m2y9u4');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$codi_url = 'http://192.168.8.246:8080';
$codi_user = 'Aghiggi';
$codi_pass = '@Ag0351@';

echo "=== BUSCANDO OP COM DADOS LOCAIS ===\n\n";

// Buscar primeira OP que tem schedules
$sql = "SELECT sl.sch_programa_id, COUNT(*) as sch_count
        FROM sch_linhas sl
        WHERE sl.sch_tipo = 'producao'
        GROUP BY sl.sch_programa_id
        LIMIT 1";

$row = $db->query($sql)->fetch(PDO::FETCH_ASSOC);

if (!$row) {
    echo "❌ Nenhum schedule encontrado\n";
    exit;
}

$programa_id = $row['sch_programa_id'];

// Obter dados do programa
$sql2 = "SELECT prg_id, prg_programa_op FROM prg_programas WHERE prg_id = ?";
$prog = $db->prepare($sql2)->execute([$programa_id])->fetch(PDO::FETCH_ASSOC);

if (!$prog) {
    echo "❌ Programa não encontrado\n";
    exit;
}

$op_numero = $prog['prg_programa_op'];

// Obter planejado
$sql3 = "SELECT prg_quantidade FROM prg_itens WHERE prg_id = ?";
$item = $db->prepare($sql3)->execute([$programa_id])->fetch(PDO::FETCH_ASSOC);
$planejado = floatval($item['prg_quantidade'] ?? 0);

// Obter realizado local
$sql4 = "SELECT SUM(sch_quantidade) as total FROM sch_linhas WHERE sch_programa_id = ? AND sch_tipo = 'producao'";
$sch = $db->prepare($sql4)->execute([$programa_id])->fetch(PDO::FETCH_ASSOC);
$realizado_local = floatval($sch['total'] ?? 0);

echo "✓ OP LOCAL: $op_numero\n";
echo "  Programa ID: $programa_id\n";
echo "  Planejado: $planejado\n";
echo "  Realizado (local): $realizado_local\n\n";

// Buscar na CODI
echo "Buscando na CODI...\n";

$url = $codi_url . '/action/ger/webservice/rest/ordemProducao?ordem=' . $op_numero;

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_USERPWD, "$codi_user:$codi_pass");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

$response = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($code == 200) {
    $data = json_decode($response, true);
    
    if (isset($data['data'][0])) {
        $op_codi = $data['data'][0];
        
        echo "\n✓ OP CODI encontrada!\n";
        echo "  Ordem: " . $op_codi['ordem'] . "\n";
        echo "  Quantidade: " . $op_codi['quantidade'] . "\n";
        echo "  Status: " . $op_codi['status'] . "\n";
        echo "  CodigoOrdemProducao: " . $op_codi['codigoOrdemProducao'] . "\n\n";
        
        // Agora buscar dados consolidados
        echo "Buscando Evento Consolidado...\n\n";
        
        $url2 = $codi_url . '/action/ger/webservice/rest/relatorioEventoConsolidado?ordem=' . $op_numero . '&itensPorPagina=100';
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url2);
        curl_setopt($ch, CURLOPT_USERPWD, "$codi_user:$codi_pass");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response2 = curl_exec($ch);
        $code2 = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        echo "HTTP: $code2\n";
        
        if ($code2 == 200) {
            $data2 = json_decode($response2, true);
            
            if (isset($data2['data'])) {
                echo "✓ Encontrados " . count($data2['data']) . " eventos\n\n";
                
                if (count($data2['data']) > 0) {
                    $evento = $data2['data'][0];
                    
                    echo "📊 ESTRUTURA DO EVENTO:\n";
                    echo str_repeat("-", 60) . "\n";
                    foreach ($evento as $k => $v) {
                        if (is_scalar($v)) {
                            echo sprintf("%-40s: %s\n", $k, json_encode($v));
                        }
                    }
                }
            } else {
                echo "Erro ou sem dados:\n" . substr($response2, 0, 300) . "\n";
            }
        }
    }
} else {
    echo "❌ Erro HTTP $code\n";
    echo substr($response, 0, 300) . "\n";
}

?>
