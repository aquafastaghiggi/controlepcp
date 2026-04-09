<?php
/**
 * Consulta OP 201055: Planejado (local) × Taxa da CODI = Realizado
 * 
 * Fluxo:
 * 1. Buscar programa de OP 201055 em prg_itens → PLANEJADO
 * 2. Buscar OP 201055 na API CODI → extrai PERCENTUAL/TAXA
 * 3. Calcular: REALIZADO = PLANEJADO × TAXA_CODI
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

// Credenciais CODI
$codi_url = 'http://192.168.8.246:8080';
$codi_user = 'Aghiggi';
$codi_pass = '@Ag0351@';

/**
 * Busca ordem na API CODI
 */
function buscar_codi_order($op, $codi_url, $codi_user, $codi_pass) {
    $op_variants = [
        $op,
        str_pad($op, 7, '0', STR_PAD_LEFT),
        str_pad($op, 8, '0', STR_PAD_LEFT),
    ];
    
    // Procurar em 100 páginas máximo
    for ($page = 1; $page <= 100; $page++) {
        $url = $codi_url . '/action/ger/webservice/rest/ordemProducao?page=' . $page . '&pageSize=500';
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_USERPWD, "$codi_user:$codi_pass");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code === 200 && !empty($response)) {
            $response_utf8 = iconv('ISO-8859-1', 'UTF-8', $response);
            $data = json_decode($response_utf8, true);
            
            if (isset($data['data']) && is_array($data['data'])) {
                foreach ($data['data'] as $order) {
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
    $op = $_GET['op'] ?? '201055';
    $data_inicio = $_GET['data_inicio'] ?? '2026-03-27';
    $data_fim = $_GET['data_fim'] ?? '2026-03-28';
    
    // Conectar ao banco local
    $pdo = new PDO(
        'mysql:host=127.0.0.1:3306;dbname=controlepcp_sandbox;charset=utf8mb4',
        'root',
        'k7m2y9u4'
    );
    
    // ===== 1. PLANEJADO: Buscar programa (apenas 1º) =====
    $stmt = $pdo->prepare("
        SELECT 
            prg_programa_id,
            prg_itens_op,
            prg_sku,
            prg_quantidade,
            prg_data_inicio,
            prg_data_fim
        FROM prg_itens
        WHERE prg_itens_op = ?
        LIMIT 1
    ");
    $stmt->execute([$op]);
    $programa = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$programa) {
        http_response_code(404);
        echo json_encode(['erro' => "OP {$op} não encontrada no sistema local"]);
        exit;
    }
    
    $planejado_quantidade = (float)$programa['prg_quantidade'];
    $prg_programa_id = (int)$programa['prg_programa_id'];
    
    // ===== 2. CODI: Buscar OP e extrair PERCENTUAL =====
    $ordem_codi = buscar_codi_order($op, $codi_url, $codi_user, $codi_pass);
    
    if (!$ordem_codi) {
        http_response_code(404);
        echo json_encode(['erro' => "OP {$op} não encontrada na CODI"]);
        exit;
    }
    
    // ===== OPÇÃO 1: Buscar taxa na resposta CODI (se existir) =====
    $taxa_execucao_percent = 0.0;
    $taxa_fonte = 'nenhum_campo_codi';
    
    $campos_possiveis = [
        'percentualExecutado',
        'percentual_executado',
        'taxaExecucao',
        'taxa_execucao',
        'eficiencia',
        'porcentagem',
        'execucaoPercent',
        'percentualProducao'
    ];
    
    foreach ($campos_possiveis as $campo) {
        if (isset($ordem_codi[$campo])) {
            $taxa_execucao_percent = (float)$ordem_codi[$campo];
            $taxa_fonte = "codi.{$campo}";
            break;
        }
    }
    
    // ===== OPÇÃO 2: Se não encontrou na CODI, buscar nos schedules locais =====
    // (Isso é uma fallback enquanto você configura o endpoint correto da CODI)
    if ($taxa_execucao_percent === 0.0) {
        $stmt = $pdo->prepare("
            SELECT SUM(sch_quantidade) as total_realizado
            FROM sch_linhas
            WHERE prg_programa_id = ?
            AND DATE(sch_data_inicio) BETWEEN ? AND ?
        ");
        $stmt->execute([$prg_programa_id, $data_inicio, $data_fim]);
        $sched = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $realizado_local = $sched['total_realizado'] ? (float)$sched['total_realizado'] : 0;
        
        if ($planejado_quantidade > 0) {
            $taxa_execucao_percent = ($realizado_local / $planejado_quantidade) * 100;
            $taxa_fonte = "schedules_locais_periodo_{$data_inicio}_{$data_fim}";
        }
    }
    
    // ===== 3. REALIZADO: Calcular = PLANEJADO × TAXA =====
    $realizado_quantidade = $planejado_quantidade * ($taxa_execucao_percent / 100);
    
    // ===== RESPOSTA =====
    $response = [
        'op' => $op,
        'periodo' => [
            'inicio' => $data_inicio,
            'fim' => $data_fim
        ],
        'planejado' => [
            'quantidade' => $planejado_quantidade,
            'programa_id' => $prg_programa_id,
            'sku' => $programa['prg_sku']
        ],
        'codi' => [
            'ordem' => $ordem_codi['ordem'] ?? null,
            'status' => $ordem_codi['status'] ?? null,
            'quantidade' => $ordem_codi['quantidade'] ?? null,
            'nota' => 'Este endpoint não retorna percentual; use a taxa_fonte abaixo'
        ],
        'taxa_origem' => $taxa_fonte,
        'realizado' => [
            'quantidade' => round($realizado_quantidade, 2),
            'taxa_percent' => round($taxa_execucao_percent, 2),
            'calculo' => sprintf(
                '%s × %s%% = %s',
                $planejado_quantidade,
                round($taxa_execucao_percent, 2),
                round($realizado_quantidade, 2)
            )
        ],
        'resumo' => [
            'planejado' => $planejado_quantidade,
            'realizado' => round($realizado_quantidade, 2),
            'taxa_percent' => round($taxa_execucao_percent, 2),
            'desvio' => round($realizado_quantidade - $planejado_quantidade, 2)
        ],
        'campos_codi_retornados' => array_keys($ordem_codi),
        'debug_codi_resposta' => $ordem_codi
    ];
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'erro' => $e->getMessage(),
        'arquivo' => $e->getFile(),
        'linha' => $e->getLine()
    ]);
}
?>
