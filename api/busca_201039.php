<?php
/**
 * Buscar OP 201039 e ver dados de execução
 */

$db = new PDO("mysql:host=127.0.0.1;dbname=controlepcp_sandbox;charset=utf8mb4", 'root', 'k7m2y9u4');

$codi_url = 'http://192.168.8.246:8080';
$codi_user = 'Aghiggi';
$codi_pass = '@Ag0351@';

$op_numero = '201039';

echo "=== OP: $op_numero ===\n\n";

// 1️⃣ Local
echo "1️⃣ DADOS LOCAIS:\n";

$sql = "SELECT pp.prg_id, pi.prg_quantidade
        FROM prg_itens pi
        JOIN prg_programas pp ON pi.prg_programa_id = pp.prg_id
        WHERE pi.prg_itens_op = ?
        LIMIT 1";

$stmt = $db->prepare($sql);
$stmt->execute([$op_numero]);
$item = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$item) {
    echo "❌ OP não encontrada localmente\n";
    exit;
}

$programa_id = $item['prg_id'];
$planejado = floatval($item['prg_quantidade']);

echo "  Planejado: $planejado\n";

// Schedules desta OP
$sql_sch = "SELECT sch_quantidade, sch_produzido_estimado FROM sch_linhas WHERE sch_programa_id = ?";
$stmt = $db->prepare($sql_sch);
$stmt->execute([$programa_id]);
$schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);

$total_sch = 0;
$total_estimado = 0;

foreach ($schedules as $sch) {
    if (!empty($sch['sch_quantidade'])) {
        $total_sch += floatval($sch['sch_quantidade']);
    }
    if (!empty($sch['sch_produzido_estimado'])) {
        $total_estimado += floatval($sch['sch_produzido_estimado']);
    }
}

echo "  Total sch_quantidade: $total_sch\n";
echo "  Total sch_produzido_estimado: $total_estimado\n\n";

// 2️⃣ CODI - Buscar OP
echo "2️⃣ BUSCANDO NA CODI:\n";

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
        $op = $data['data'][0];
        
        echo "  ✓ OP encontrada\n";
        echo "  Ordem: " . $op['ordem'] . "\n";
        echo "  Quantidade: " . $op['quantidade'] . "\n";
        echo "  Status: " . $op['status'] . "\n";
        echo "  CodigoOrdemProducao: " . $op['codigoOrdemProducao'] . "\n\n";
        
        // 3️⃣ Buscar EventoConsolidado
        echo "3️⃣ BUSCANDO EVENTO CONSOLIDADO:\n";
        
        $url2 = $codi_url . '/action/ger/webservice/rest/relatorioEventoConsolidado?ordem=' . $op_numero . '&itensPorPagina=1000';
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url2);
        curl_setopt($ch, CURLOPT_USERPWD, "$codi_user:$codi_pass");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response2 = curl_exec($ch);
        $code2 = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        echo "  HTTP Code: $code2\n";
        
        if ($code2 == 200) {
            $data2json = json_decode($response2, true);
            
            if ($data2json === null) {
                echo "  ⚠️  JSON inválido, tentando extrair dados...\n";
                // Tentar extrair o primeiro objeto JSON
                if (preg_match('/\{[^{}]*"totalCount"[^{}]*\}/', $response2, $matches)) {
                    echo "  Encontrado JSON: " . substr($matches[0], 0, 100) . "...\n";
                }
            } else if (isset($data2json['data'])) {
                echo "  ✓ Encontrados " . count($data2json['data']) . " eventos\n\n";
                
                if (count($data2json['data']) > 0) {
                    echo "  📊 CAMPOS DO PRIMEIRO EVENTO:\n";
                    echo "  " . str_repeat("-", 70) . "\n";
                    
                    $evento = $data2json['data'][0];
                    foreach ($evento as $k => $v) {
                        if (is_scalar($v)) {
                            echo "  $k: " . json_encode($v) . "\n";
                        }
                    }
                }
            } else {
                echo "  Resposta: " . substr($response2, 0, 200) . "\n";
            }
        } else {
            echo "  ❌ HTTP Error: $code2\n";
            echo "  Response: " . substr($response2, 0, 200) . "\n";
        }
    } else {
        echo "  ❌ OP não encontrada na CODI\n";
    }
} else {
    echo "  ❌ HTTP Error: $code\n";
}

?>
