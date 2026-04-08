<?php
/**
 * API INTEGRADA: Busca OP em ambos os sistemas (Local + CODI)
 * 
 * Credenciais CODI:
 * - URL: http://192.168.8.246:8080
 * - User: Aghiggi
 * - Pass: @Ag0351@
 */

header('Content-Type: application/json; charset=utf-8');

// Conexão Local
$pdo_local = new PDO(
    'mysql:host=127.0.0.1:3306;dbname=controlepcp_sandbox;charset=utf8mb4',
    'root',
    'k7m2y9u4'
);

// Credenciais CODI
$codi_url = 'http://192.168.8.246:8080';
$codi_user = 'Aghiggi';
$codi_pass = '@Ag0351@';

function get_codi_order($op) {
    global $codi_url, $codi_user, $codi_pass;
    
    // Tentar buscar a OP com diferentes formatos
    $op_variants = [
        $op,                      // 201055
        str_pad($op, 7, '0', STR_PAD_LEFT),  // 0201055
        str_pad($op, 8, '0', STR_PAD_LEFT),  // 00201055
    ];
    
    // Procurar em no máximo 100 páginas (cobrindo ~50.000 OPs)
    for ($page = 1; $page <= 100; $page++) {
        $url = $codi_url . '/action/ger/webservice/rest/ordemProducao?page=' . $page . '&pageSize=500';
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_USERPWD, "$codi_user:$codi_pass");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($http_code === 200 && !empty($response)) {
            $response_utf8 = iconv('ISO-8859-1', 'UTF-8', $response);
            $data = json_decode($response_utf8, true);
            
            if (isset($data['data']) && is_array($data['data'])) {
                foreach ($data['data'] as $order) {
                    // Procurar por qualquer variante da OP
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

try {
    $action = $_GET['action'] ?? 'detalhe_op';
    $op = $_GET['op'] ?? null;
    
    if ($action === 'detalhe_op' && $op) {
        // PLANEJADO: De prg_itens (dados brutos)
        $sql_planejado = "
            SELECT 
                prg_itens_op AS op,
                prg_sku AS sku,
                prg_quantidade AS quantidade_planejada,
                prg_programa_id,
                prg_inicio_planejado
            FROM prg_itens
            WHERE prg_itens_op = :op
            ORDER BY prg_sku
        ";
        
        $stmt = $pdo_local->prepare($sql_planejado);
        $stmt->execute(['op' => $op]);
        $planejado_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Calcular total planejado
        $total_planejado = 0;
        foreach ($planejado_items as $item) {
            $total_planejado += (float)$item['quantidade_planejada'];
        }
        
        // REALIZADO: De sch_linhas (execução real no período 27/03-28/03)
        // Soma de schedules para cada SKU dessa OP no período
        $sql_realizado = "
            SELECT 
                p.prg_sku,
                SUM(CAST(s.sch_quantidade AS DECIMAL(10,2))) as quantidade_realizada,
                COUNT(*) as total_schedules,
                MIN(DATE(s.sch_data_inicio)) as data_inicio,
                MAX(DATE(s.sch_data_inicio)) as data_fim
            FROM sch_linhas s
            INNER JOIN prg_itens p ON s.sch_programa_id = p.prg_programa_id
            WHERE p.prg_itens_op = :op
              AND DATE(s.sch_data_inicio) >= '2026-03-27'
              AND DATE(s.sch_data_inicio) <= '2026-03-28'
              AND s.sch_quantidade IS NOT NULL
              AND s.sch_quantidade != ''
              AND CAST(s.sch_quantidade AS DECIMAL) > 0
            GROUP BY p.prg_sku
        ";
        
        $stmt = $pdo_local->prepare($sql_realizado);
        $stmt->execute(['op' => $op]);
        $realizado_execucoes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Calcular total realizado
        $total_realizado = 0;
        $realizado_map = [];
        foreach ($realizado_execucoes as $item) {
            $total_realizado += (float)$item['quantidade_realizada'];
            $realizado_map[$item['prg_sku']] = $item;
        }
        
        // Calcular taxa de execução
        $taxa_execucao = $total_planejado > 0 ? ($total_realizado / $total_planejado) : 0;
        $quantidade_realizada_taxa = $total_planejado * $taxa_execucao;
        
        // Buscar OP na CODI se quiser dados adicionais
        $codi_encontrada = false;
        $codi_data = null;
        
        if (function_exists('get_codi_order')) {
            $codi_data = get_codi_order($op);
            $codi_encontrada = $codi_data !== null;
        }
        
        $result = [
            'op' => $op,
            'planejado' => [
                'encontrado' => count($planejado_items) > 0,
                'itens' => $planejado_items,
                'total_itens' => count($planejado_items),
                'total_quantidade' => $total_planejado
            ],
            'realizado' => [
                'encontrado' => count($realizado_execucoes) > 0,
                'detalhes' => $realizado_execucoes,
                'total_quantidade' => $total_realizado,
                'taxa_execucao' => round($taxa_execucao * 100, 2) . '%',
                'quantidade_pela_taxa' => round($quantidade_realizada_taxa, 2) // Essa é a quantidade "realizada" calculada
            ],
            'comparativo' => [
                'planejado' => $total_planejado,
                'realizado' => $total_realizado,
                'realizado_pela_taxa' => round($quantidade_realizada_taxa, 2),
                'diferenca' => round($total_realizado - $total_planejado, 2),
                'taxa_execucao' => round($taxa_execucao * 100, 2) . '%'
            ],
            'status' => [
                'planejado_no_local' => count($planejado_items) > 0 ? '✓' : '✗',
                'realizado_no_periodo' => count($realizado_execucoes) > 0 ? '✓' : '✗',
                'codi_encontrada' => $codi_encontrada ? '✓' : '✗'
            ],
            'periodo' => [
                'data_inicio' => '2026-03-27',
                'data_fim' => '2026-03-28'
            ],
            'codi' => $codi_data
        ];
        
        echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        
    } else if ($action === 'lista_ops') {
        // Listar todas as OPs do sistema local
        $sql = "
            SELECT DISTINCT prg_itens_op AS op
            FROM prg_itens
            ORDER BY prg_itens_op
        ";
        
        $stmt = $pdo_local->query($sql);
        $ops = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        echo json_encode(['ops' => $ops], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        
    } else if ($action === 'codi_ops') {
        // Listar OPs disponíveis na CODI
        $ops_codi = [];
        
        for ($page = 1; $page <= 20; $page++) {
            $url = $codi_url . '/action/ger/webservice/rest/ordemProducao?page=' . $page . '&pageSize=500';
            
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_USERPWD, "$codi_user:$codi_pass");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5);
            
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($http_code === 200) {
                $response_utf8 = iconv('ISO-8859-1', 'UTF-8', $response);
                $data = json_decode($response_utf8, true);
                
                if (isset($data['data'])) {
                    foreach ($data['data'] as $order) {
                        $ops_codi[] = [
                            'ordem' => $order['ordem'],
                            'status' => $order['status'],
                            'quantidade' => $order['quantidade']
                        ];
                    }
                }
            }
        }
        
        echo json_encode([
            'total' => count($ops_codi),
            'ops' => array_slice($ops_codi, 0, 50)
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'Action or OP not specified']);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
