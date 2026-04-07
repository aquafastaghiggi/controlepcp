<?php
/**
 * API para buscar programações e dados filtrados por programação
 */

require __DIR__ . '/../controlepcp/src/bootstrap.php';

use App\Auth\Auth;

Auth::startSession();
Auth::requireLogin();

header('Content-Type: application/json');

$action = $_GET['action'] ?? null;

try {
    $pdo = new PDO(
        'mysql:host=localhost;dbname=controlepcp_sandbox',
        'root',
        'k7m2y9u4'
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    if ($action === 'programacoes') {
        // Buscar todas as programações
        $stmt = $pdo->query("
            SELECT DISTINCT 
                pp.prg_id,
                pp.prg_linha_id,
                COUNT(pi.prg_id_item) as total_itens,
                pp.prg_numero_op,
                pp.prg_status
            FROM prg_programas pp
            LEFT JOIN prg_itens pi ON pp.prg_id = pi.prg_programa_id
            GROUP BY pp.prg_id, pp.prg_linha_id, pp.prg_numero_op, pp.prg_status
            ORDER BY pp.prg_id DESC
            LIMIT 50
        ");
        echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        
    } elseif ($action === 'filtrar') {
        // Filtrar dados por programa selecionado
        $prg_id = $_GET['prg_id'] ?? null;
        
        if (!$prg_id) {
            throw new Exception('prg_id não fornecido');
        }
        
        // PREVISTO - filtrado por programa
        $stmt = $pdo->prepare("
            SELECT 
                SUM(prg_quantidade) as total_planejado,
                COUNT(DISTINCT prg_itens_op) as ops_previsto
            FROM prg_itens
            WHERE prg_programa_id = ?
            AND prg_itens_op IS NOT NULL 
            AND prg_itens_op != ''
        ");
        $stmt->execute([$prg_id]);
        $prev_data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // REALIZADO - filtrado por programa
        $stmt = $pdo->prepare("
            SELECT 
                SUM(r.quantidade) as total_realizado,
                COUNT(DISTINCT r.ordem_op) as ops_realizado
            FROM realizado_2026_excel r
            WHERE EXISTS (
                SELECT 1 FROM prg_itens pi 
                WHERE pi.prg_programa_id = ?
                AND CAST(pi.prg_itens_op AS CHAR) = r.ordem_op
            )
        ");
        $stmt->execute([$prg_id]);
        $real_data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $response = [
            'success' => true,
            'previsto' => [
                'total' => (float)($prev_data['total_planejado'] ?? 0),
                'ops' => (int)($prev_data['ops_previsto'] ?? 0)
            ],
            'realizado' => [
                'total' => (float)($real_data['total_realizado'] ?? 0),
                'ops' => (int)($real_data['ops_realizado'] ?? 0)
            ]
        ];
        
        // Calcular diferença e percentual
        $total_prev = $response['previsto']['total'];
        $total_real = $response['realizado']['total'];
        $response['diferenca'] = $total_real - $total_prev;
        $response['percentual'] = $total_prev > 0 ? ($total_real / $total_prev) * 100 : 0;
        
        echo json_encode($response);
        
    } else {
        throw new Exception('Ação não reconhecida');
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    http_response_code(400);
}
