<?php
/**
 * Buscar OP e seus dados no banco local e na CODI
 */

// Banco local
$db_host = '127.0.0.1';
$db_user = 'root';
$db_pass = 'k7m2y9u4';
$db_name = 'controlepcp_sandbox';

$pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// CODI
$codi_url = 'http://192.168.8.246:8080';
$codi_user = 'Aghiggi';
$codi_pass = '@Ag0351@';

echo "=== BUSCA: OP LOCAIS vs CODI ===\n\n";

// 1️⃣ Buscar primeira OP do banco local
echo "1️⃣ Buscando OPs com dados de execução (sch_linhas)...\n\n";

$query = "SELECT DISTINCT pl.prg_programa_op, pi.prg_quantidade
FROM sch_linhas sl
JOIN prg_programas pl ON sl.sch_programa_id = pl.prg_id
JOIN prg_itens pi ON pl.prg_programa_id = pi.prg_id
WHERE sl.sch_tipo = 'producao'
LIMIT 10";

$stmt = $pdo->query($query);
$ops = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($ops as $op) {
    echo "OP: " . $op['prg_programa_op'] . " | Planejado: " . $op['prg_quantidade'] . "\n";
}

if (count($ops) > 0) {
    $op_numero = $ops[0]['prg_programa_op'];
    $planejado = floatval($ops[0]['prg_quantidade']);
    
    echo "\n✓ Testando OP: $op_numero\n";
    echo "  Planejado (local): $planejado\n\n";
    
    // 2️⃣ Buscar quantidade realizada no local (sch_linhas)
    echo "2️⃣ Buscando realizado local (sch_linhas):\n";
    
    $query_local = "SELECT SUM(sch_quantidade) as total_realizado
    FROM sch_linhas sl
    JOIN prg_programas pl ON sl.sch_programa_id = pl.prg_id
    WHERE pl.prg_programa_op = ? AND sl.sch_tipo = 'producao'";
    
    $stmt = $pdo->prepare($query_local);
    $stmt->execute([$op_numero]);
    $resultado_local = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $realizado_local = floatval($resultado_local['total_realizado'] ?? 0);
    
    echo "  Realizado (sch_linhas): $realizado_local\n";
    if ($planejado > 0) {
        $taxa_local = ($realizado_local / $planejado) * 100;
        echo "  Taxa: " . number_format($taxa_local, 2) . "%\n";
    }
    
    // 3️⃣ Buscar na CODI
    echo "\n3️⃣ Buscando OP na CODI...\n";
    
    $url = $codi_url . '/action/ger/webservice/rest/ordemProducao?ordem=' . $op_numero;
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_USERPWD, "$codi_user:$codi_pass");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    echo "  HTTP Code: $http_code\n";
    
    if ($http_code == 200) {
        $data = json_decode($response, true);
        
        if (isset($data['data']) && count($data['data']) > 0) {
            $op_codi = $data['data'][0];
            echo "  ✓ OP encontrada na CODI\n";
            echo "  Ordem: " . $op_codi['ordem'] . "\n";
            echo "  Quantidade CODI: " . $op_codi['quantidade'] . "\n";
            echo "  Status: " . $op_codi['status'] . "\n";
            echo "  Código interno: " . $op_codi['codigoOrdemProducao'] . "\n";
            
            // 4️⃣ Buscar em Event Consolidado
            echo "\n4️⃣ Buscando dados de execução (Evento Consolidado)...\n";
            
            $url_evento = $codi_url . '/action/ger/webservice/rest/relatorioEventoConsolidado?ordem=' . $op_numero . '&itensPorPagina=100';
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url_evento);
            curl_setopt($ch, CURLOPT_USERPWD, "$codi_user:$codi_pass");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            echo "  HTTP Code: $http_code\n";
            
            if ($http_code == 200) {
                $data_evento = json_decode($response, true);
                
                if (isset($data_evento['data']) && count($data_evento['data']) > 0) {
                    echo "  ✓ Eventos encontrados: " . count($data_evento['data']) . "\n\n";
                    
                    // Mostrar campos do primeiro evento
                    $evento = $data_evento['data'][0];
                    echo "  Campos do evento:\n";
                    foreach ($evento as $k => $v) {
                        if (is_scalar($v)) {
                            echo "    - $k: " . json_encode($v) . "\n";
                        }
                    }
                }
            }
        }
    }
}

?>
