<?php
/**
 * API CODI - Chamada Real
 * Busca dados de execução direto do CODI
 */

header('Content-Type: application/json; charset=utf-8');

$codi_user = 'integrador';
$codi_pass = 'integrador123';
$codi_url = 'http://codi.local/action/ger/webservice/rest/ordemProducao';

try {
    $op = $_GET['op'] ?? null;
    $action = $_GET['action'] ?? 'buscar_op';
    
    if ($action === 'buscar_op' && $op) {
        // Chamar API CODI para buscar a OP
        $auth = base64_encode("$codi_user:$codi_pass");
        
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => "Authorization: Basic $auth\r\n",
                'timeout' => 10
            ]
        ]);
        
        // Buscar primeira página para encontrar a OP
        $url = "$codi_url?page=1&pageSize=100";
        
        $response = @file_get_contents($url, false, $context);
        
        if ($response === false) {
            throw new Exception('Erro ao conectar com CODI API');
        }
        
        $data = json_decode($response, true);
        
        if (!$data || !isset($data['data'])) {
            throw new Exception('Resposta inválida da API CODI');
        }
        
        // Procurar a OP nos dados retornados
        $found = null;
        foreach ($data['data'] as $order) {
            if ($order['ordem'] == $op) {
                $found = $order;
                break;
            }
        }
        
        if (!$found) {
            // Se não encontrou na primeira página, informar
            echo json_encode([
                'status' => 'not_found',
                'op' => $op,
                'message' => 'OP não encontrada na API CODI',
                'total_apis' => $data['totalCount'] ?? 0
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            exit;
        }
        
        echo json_encode([
            'status' => 'ok',
            'op' => $op,
            'data' => $found
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }
    
} catch (Exception $e) {
    echo json_encode([
        'error' => $e->getMessage(),
        'op' => $_GET['op'] ?? null
    ]);
}
?>
