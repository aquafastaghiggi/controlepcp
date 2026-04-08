<?php
// Buscar dados de produção realizado direto do banco

echo "═══════════════════════════════════════════════════════════════════════\n";
echo "                  OP 201055 - PLANEJADO vs REALIZADO\n";
echo "═══════════════════════════════════════════════════════════════════════\n\n";

$auth = 'Aghiggi:@Ag0351@';

// 1. Dados do CODI
echo "📊 INFORMAÇÕES DA OP:\n";
echo "────────────────────\n";

$ch = curl_init('http://192.168.8.246:8080/action/ger/webservice/rest/ordemProducao/23599');
curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
curl_setopt($ch, CURLOPT_USERPWD, $auth);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$planejado = 0;
if ($httpCode == 200) {
    $codi_data = json_decode($response, true);
    
    echo "Número: " . $codi_data['ordem'] . "\n";
    echo "Produto: " . $codi_data['item']['nomeItem'] . "\n";
    echo "Data de última alteração: " . $codi_data['ultimaAlteracao'] . "\n";
    echo "Unidade: " . $codi_data['item']['unidadeMedida']['nomeUnidadeMedida'] . "\n\n";
    
    $planejado = $codi_data['quantidade'];
    echo "📦 QUANTIDADE PLANEJADA: " . number_format($planejado, 0, ',', '.') . " CX\n";
}

// 2. Buscar realizado em tabelas de sequências
echo "\n📊 PROCURANDO REALIZADO NO BANCO:\n";
echo "────────────────────────────────\n";

try {
    $pdo = new PDO('mysql:host=localhost;dbname=controlepcp_sandbox', 'root', 'k7m2y9u4');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Procurar pela OP no programa
    $stmt = $pdo->prepare("
        SELECT pp.prg_id 
        FROM prg_programas pp
        WHERE pp.prg_numero_op = '201055' OR pp.prg_numero_op = '0201055'
        LIMIT 1
    ");
    $stmt->execute();
    $prog = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($prog) {
        $prg_id = $prog['prg_id'];
        echo "OP encontrada no banco (prg_id=$prg_id)\n\n";
        
        // Buscar sequências desta programa
        $stmt = $pdo->prepare("
            SELECT 
                SUM(sch_produzido_estimado) as total_produzido,
                COUNT(*) as total_linhas,
                SUM(sch_quantidade) as total_quantidade_linhas,
                GROUP_CONCAT(DISTINCT sch_status) as status_linhas
            FROM sch_linhas
            WHERE sch_programa_id = ?
        ");
        $stmt->execute([$prg_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo "Dados de Sequências (sch_linhas):\n";
        echo "  Total de linhas: " . ($result['total_linhas'] ?? 0) . "\n";
        echo "  Total planejado nas linhas: " . number_format($result['total_quantidade_linhas'] ?? 0, 2, ',', '.') . " un\n";
        echo "  Total produzido estimado: " . number_format($result['total_produzido'] ?? 0, 2, ',', '.') . " un\n";
        echo "  Status das linhas: " . ($result['status_linhas'] ?? 'nenhum') . "\n\n";
    } else {
        echo "⚠️  OP não encontrada no programa\n\n";
    }
    
    // Buscar nos itens
    $stmt = $pdo->prepare("
        SELECT 
            prg_id_item,
            prg_quantidade,
            prg_atualizado_em
        FROM prg_itens
        WHERE prg_itens_op = '201055'
    ");
    $stmt->execute();
    $itens = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Dados de Itens (prg_itens):\n";
    echo "  Total de itens registrados: " . count($itens) . "\n";
    
    if (count($itens) > 0) {
        $total_item_qty = 0;
        foreach ($itens as $item) {
            $total_item_qty += $item['prg_quantidade'];
        }
        echo "  Quantidade total dos itens: " . number_format($total_item_qty, 0, ',', '.') . " un\n";
    }
    
} catch (Exception $e) {
    echo "ERRO ao consultar banco: " . $e->getMessage() . "\n";
}

// 3. Tentar buscar via CODI eventos consolidados
echo "\n📊 DADOS DE REALIZADO (via CODI - Eventos):\n";
echo "──────────────────────────────────────────\n";

$ch = curl_init('http://192.168.8.246:8080/action/ger/webservice/rest/relatorioEvento?codOrdemProducao=23599&pageSize=100&page=1');
curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
curl_setopt($ch, CURLOPT_USERPWD, $auth);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 15);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$realizado = 0;
if ($httpCode == 200) {
    // Salva em arquivo com nome apropriado
    file_put_contents('eventos_201055_data.json', $response);
    
    // Tenta decodificar com tratamento de encoding
    $response_utf8 = iconv('UTF-8', 'UTF-8//IGNORE', $response);
    $data = json_decode($response_utf8, true);
    
    if ($data && isset($data['totalCount'])) {
        echo "Total de eventos: " . number_format($data['totalCount'], 0, ' ', '.') . "\n";
        echo "Total de páginas: " . number_format($data['totalPages'] ?? 0, 0, ' ', '.') . "\n";
        
        // Calcular total produzido somando todos os eventos
        if (isset($data['data']) && is_array($data['data'])) {
            foreach ($data['data'] as $evento) {
                if (isset($evento['ordens']) && is_array($evento['ordens'])) {
                    foreach ($evento['ordens'] as $ordem) {
                        if (isset($ordem['quantidadeProduzidaItem'])) {
                            $realizado += $ordem['quantidadeProduzidaItem'];
                        }
                    }
                }
            }
        }
        
        echo "\n📦 QUANTIDADE REALIZADA: " . number_format($realizado, 0, ',', '.') . " unidades\n";
        
    } else {
        echo "Não foi possível decodificar resposta de eventos\n";
    }
} else {
    echo "CODI retornou HTTP $httpCode ao buscar eventos\n";
}

// RESUMO FINAL
if ($planejado > 0 || $realizado > 0) {
    echo "\n";
    echo "═══════════════════════════════════════════════════════════════════════\n";
    echo "                         RESUMO EXECUTIVO\n";
    echo "═══════════════════════════════════════════════════════════════════════\n\n";
    
    echo "📦 PLANEJADO:  " . number_format($planejado, 0, ',', '.') . " CX\n";
    echo "📦 REALIZADO:  " . number_format($realizado, 0, ',', '.') . " unidades\n\n";
    
    if ($realizado > 0 && $planejado > 0) {
        $percentual = ($realizado / $planejado) * 100;
        $diferenca = $realizado - $planejado;
        
        echo "───────────────────────────────────────────────────────────────────────\n";
        echo "Percentual atingido: " . number_format($percentual, 1, ',', '.') . " %\n";
        
        if ($diferenca >= 0) {
            echo "Status: ✅ META ATINGIDA\n";
            echo "Excedente: " . number_format($diferenca, 0, ',', '.') . " unidades acima da meta\n";
        } else {
            echo "Status: ⚠️  META NÃO ATINGIDA\n";
            echo "Faltante: " . number_format(abs($diferenca), 0, ',', '.') . " unidades para cumprir\n";
        }
    }
}

echo "\n";

?>
