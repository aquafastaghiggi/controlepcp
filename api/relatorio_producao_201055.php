<?php
// Buscar dados de produção planejado vs realizado da OP 201055

echo "═══════════════════════════════════════════════════════════════════════\n";
echo "                  ANÁLISE: OP 201055 - PLANEJADO vs REALIZADO\n";
echo "═══════════════════════════════════════════════════════════════════════\n\n";

$auth = 'Aghiggi:@Ag0351@';

// 1. Dados do CODI
echo "📊 DADOS DO CODI:\n";
echo "────────────────\n";

$ch = curl_init('http://192.168.8.246:8080/action/ger/webservice/rest/ordemProducao/23599');
curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
curl_setopt($ch, CURLOPT_USERPWD, $auth);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$codi_data = null;
if ($httpCode == 200) {
    $codi_data = json_decode($response, true);
    
    echo "OP: " . $codi_data['ordem'] . "\n";
    echo "Produto: " . $codi_data['item']['nomeItem'] . "\n";
    echo "Código CODI: " . $codi_data['codigoOrdemProducao'] . "\n";
    echo "Unidade: " . $codi_data['item']['unidadeMedida']['nomeUnidadeMedida'] . "\n\n";
    
    echo "📦 PLANEJADO: " . number_format($codi_data['quantidade'], 0, ',', '.') . " " . $codi_data['item']['unidadeMedida']['nomeUnidadeMedida'] . "\n";
}

// 2. Dados do Banco Local
echo "\n📊 DADOS DO BANCO LOCAL:\n";
echo "──────────────────────\n";

try {
    $pdo = new PDO('mysql:host=localhost;dbname=controlepcp_sandbox', 'root', 'k7m2y9u4');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Buscar OP no banco
    $stmt = $pdo->prepare("
        SELECT 
            pi.prg_id_item,
            pi.prg_programa_id,
            pi.prg_quantidade,
            pp.prg_numero_op,
            pp.prg_status,
            pp.prg_atualizado_em
        FROM prg_itens pi
        JOIN prg_programas pp ON pi.prg_programa_id = pp.prg_id
        WHERE pi.prg_itens_op = '201055'
        LIMIT 1
    ");
    $stmt->execute();
    $banco_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($banco_data) {
        echo "OP no banco: " . $banco_data['prg_numero_op'] . "\n";
        echo "Status: " . $banco_data['prg_status'] . "\n";
        echo "Última atualização: " . $banco_data['prg_atualizado_em'] . "\n\n";
        
        echo "📦 PLANEJADO (banco): " . number_format($banco_data['prg_quantidade'], 0, ',', '.') . " unidades\n";
    }
    
} catch (Exception $e) {
    echo "ERRO ao consultar banco: " . $e->getMessage() . "\n";
}

// 3. Buscar REALIZADO via API do controle local
echo "\n📊 DADOS DE REALIZADO (Controle Local):\n";
echo "────────────────────────────────────\n";

$ch = curl_init('http://localhost/controlepcp/api_integrated.php?action=detalhe_op&op=201055');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode == 200) {
    $local_data = json_decode($response, true);
    
    if ($local_data && isset($local_data['realizado'])) {
        echo "📦 REALIZADO: " . number_format($local_data['realizado'], 0, ',', '.') . " unidades\n";
        
        if ($codi_data) {
            $planejado = $codi_data['quantidade'];
            $realizado = $local_data['realizado'];
            
            echo "\n";
            echo "═══════════════════════════════════════════════════════════════════════\n";
            echo "                         COMPARATIVO FINAL\n";
            echo "═══════════════════════════════════════════════════════════════════════\n\n";
            
            echo "Planejado:  " . number_format($planejado, 0, ',', '.') . " CX\n";
            echo "Realizado:  " . number_format($realizado, 0, ',', '.') . " unidades\n";
            
            // Calcular percentual
            if ($planejado > 0) {
                $percentual = ($realizado / $planejado) * 100;
                echo "Percentual: " . number_format($percentual, 2, ',', '.') . " %\n\n";
                
                if ($realizado >= $planejado) {
                    echo "✅ CUMPRIDA: Produziu " . number_format($realizado - $planejado, 0, ',', '.') . " unidades a mais\n";
                } else {
                    $diferenca = $planejado - $realizado;
                    echo "❌ NÃO CUMPRIDA: Faltam " . number_format($diferenca, 0, ',', '.') . " unidades\n";
                }
            }
        }
    }
} else {
    echo "Erro ao consultar API local (HTTP $httpCode)\n";
}

echo "\n";

?>
