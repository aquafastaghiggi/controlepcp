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
        // PLANEJADO (do banco local)
        $sql_planejado = "
            SELECT 
                'Planejado' AS tipo,
                p.prg_itens_op AS op,
                p.prg_sku AS sku,
                p.prg_quantidade AS quantidade,
                p.prg_programa_id,
                COUNT(DISTINCT s.sch_id) AS schedules_count
            FROM prg_itens p
            LEFT JOIN sch_linhas s ON p.prg_programa_id = s.sch_programa_id 
                AND s.sch_sku = p.prg_sku
            WHERE p.prg_itens_op = :op
            GROUP BY p.prg_sku
        ";
        
        $stmt = $pdo_local->prepare($sql_planejado);
        $stmt->execute(['op' => $op]);
        $planejado = $stmt->fetchAll(PDO::FETCH_ASSOC);
       
        // Buscar REALIZADO na CODI
        $realizado_codi = get_codi_order($op);
        
        $result = [
            'op' => $op,
            'planejado' => [
                'encontrado' => count($planejado) > 0,
                'itens' => $planejado,
                'total_itens' => count($planejado),
                'total_quantidade' => count($planejado) > 0 ? array_sum(array_column($planejado, 'quantidade')) : 0
            ],
            'realizado' => [
                'encontrado' => $realizado_codi !== null,
                'dados' => $realizado_codi,
                'origem' => 'CODI'
            ],
            'status' => [
                'planejado_no_local' => count($planejado) > 0 ? '✓' : '✗',
                'realizado_na_codi' => $realizado_codi !== null ? '✓' : '✗',
                'aviso' => $realizado_codi === null ? 'OP não foi encontrada na CODI' : ''
            ]
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
