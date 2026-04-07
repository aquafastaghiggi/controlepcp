<?php

declare(strict_types=1);

// Error handling
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

require __DIR__ . '/../src/bootstrap.php';

use App\Auth\Auth;
use App\Repository\ProgramacaoRepository;

header('Content-Type: application/json; charset=utf-8');

// Verificar autenticação antes de tudo
$action = $_GET['action'] ?? 'listar';

// Status: sem autenticação
if ($action === 'status' || $action === 'ping') {
    echo json_encode([
        'sucesso' => true,
        'status' => 'API Online',
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Debug e listar: sem autenticação para teste
if ($action === 'debug' || $action === 'listar') {
    // Tentar autenticar mas não falhar se não conseguir
    try {
        Auth::startSession();
        if (!isset($_SESSION['user_id'])) {
            // Session não autenticada, mas continua mesmo assim (modo teste)
            error_log('Sequenciamento API: chamada sem autenticação válida');
        }
    } catch (Exception $ignored) {
        // Ignora erro de autenticação para teste
    }
} else {
    // Outras ações PRECISAM de autenticação
    try {
        Auth::startSession();
        Auth::requireLoginApi();
    } catch (Exception $authErr) {
        http_response_code(401);
        echo json_encode([
            'sucesso' => false,
            'erro' => 'Não autenticado: ' . $authErr->getMessage()
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

try {
    $repo = new ProgramacaoRepository();

    switch ($action) {
        case 'status':
            echo json_encode([
                'sucesso' => true,
                'status' => 'API Online',
                'timestamp' => date('Y-m-d H:i:s')
            ], JSON_UNESCAPED_UNICODE);
            break;
            
        case 'debug':
            echo json_encode([
                'sucesso' => true,
                'mensagem' => 'API OK',
                'timestamp' => date('Y-m-d H:i:s'),
                'php_version' => PHP_VERSION,
                'user' => $_SESSION['user_id'] ?? 'não autenticado',
            ], JSON_UNESCAPED_UNICODE);
            break;

        case 'listar':
            handleListar();
            break;

        case 'detalhe':
            handleDetalhe($repo);
            break;

        case 'gantt':
            handleGantt($repo);
            break;

        case 'timeline':
            handleTimeline();
            break;

        case 'diagnostico':
            handleDiagnostico();
            break;

        case 'historicos':
            handleHistoricos();
            break;

        default:
            http_response_code(400);
            echo json_encode([
                'sucesso' => false,
                'erro' => 'Action inválida: ' . $action
            ], JSON_UNESCAPED_UNICODE);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'sucesso' => false,
        'erro' => $e->getMessage(),
        'debug' => [
            'file' => basename($e->getFile()),
            'line' => $e->getLine(),
            'action' => $action,
        ]
    ], JSON_UNESCAPED_UNICODE);
    error_log('Sequenciamento API Error: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());
}

/**
 * Listar programações que têm schedule (histórico)
 */
function handleListar(): void
{
    error_log('🔵 handleListar() iniciado');
    
    $limit = (int) ($_GET['limit'] ?? 50);
    $page = (int) ($_GET['page'] ?? 1);
    $offset = ($page - 1) * $limit;

    error_log("📊 Parâmetros: limit=$limit, page=$page, offset=$offset");

    // Buscar programações que têm schedule
    try {
        error_log('🟡 Obtendo conexão PDO...');
        $pdo = \App\Database\Connection::get();
        error_log('✅ PDO obtido com sucesso');
        
        // Query com JOIN entre programações e schedule
        $sql = "
            SELECT 
                p.prg_id,
                p.prg_numero_op,
                p.prg_eficiencia,
                p.prg_status,
                p.prg_base_inicio,
                p.prg_criado_em,
                l.lin_codigo,
                COUNT(s.sch_id) as total_linhas
            FROM prg_programas p
            LEFT JOIN lin_linhas l ON l.lin_id = p.prg_linha_id
            LEFT JOIN sch_linhas s ON s.sch_programa_id = p.prg_id
            WHERE s.sch_id IS NOT NULL
            GROUP BY p.prg_id, p.prg_numero_op, p.prg_eficiencia, p.prg_status, p.prg_base_inicio, p.prg_criado_em, l.lin_codigo
            ORDER BY p.prg_criado_em DESC, p.prg_id DESC
            LIMIT :limit OFFSET :offset
        ";
        
        error_log('🟡 Preparando statement...');
        $stmt = $pdo->prepare($sql);
        error_log('✅ Statement preparado');
        
        error_log('🟡 Vinculando parâmetros...');
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        error_log('✅ Parâmetros vinculados');
        
        error_log('🟡 Executando query...');
        $stmt->execute();
        error_log('✅ Query executada');
        
        $programacoes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        error_log('✅ Dados obtidos: ' . count($programacoes) . ' programações');

        $data = array_map(fn($p) => [
            'id' => (int) $p['prg_id'],
            'numero_op' => $p['prg_numero_op'] ?? 'OP Sem Número',
            'linha' => $p['lin_codigo'] ?? 'N/A',
            'eficiencia' => (float) ($p['prg_eficiencia'] ?? 0),
            'status' => $p['prg_status'] ?? 'pendente',
            'base_inicio' => $p['prg_base_inicio'] ?? null,
            'criado_em' => $p['prg_criado_em'] ?? null,
            'total_linhas' => (int) ($p['total_linhas'] ?? 0),
        ], $programacoes);

        error_log('✅ Dados formatados, enviando resposta JSON');

        echo json_encode([
            'sucesso' => true,
            'data' => $data,
            'paginacao' => [
                'pagina' => $page,
                'limite' => $limit,
                'total' => count($data)
            ]
        ], JSON_UNESCAPED_UNICODE);
        
    } catch (Exception $e) {
        error_log('🔴 Exceção em handleListar: ' . $e->getMessage());
        error_log('📍 Arquivo: ' . $e->getFile() . ':' . $e->getLine());
        error_log('🔍 Trace: ' . $e->getTraceAsString());
        throw new Exception('Erro ao buscar programações: ' . $e->getMessage());
    }
}

/**
 * Detalhes de uma programação com schedule completo
 */
function handleDetalhe(ProgramacaoRepository $repo): void
{
    $id = (int) ($_GET['id'] ?? 0);
    if ($id <= 0) {
        http_response_code(400);
        echo json_encode([
            'sucesso' => false,
            'erro' => 'ID inválido'
        ], JSON_UNESCAPED_UNICODE);
        return;
    }

    $programacao = $repo->getProgramacaoById($id);
    if (!$programacao) {
        http_response_code(404);
        echo json_encode([
            'sucesso' => false,
            'erro' => 'Programação não encontrada'
        ], JSON_UNESCAPED_UNICODE);
        return;
    }

    $itens = $repo->getProgramacaoItens($id);
    $schedule = $repo->getProgramacaoSchedule($id);

    echo json_encode([
        'sucesso' => true,
        'programacao' => [
            'id' => (int) $programacao['prg_id'],
            'numero_op' => $programacao['prg_numero_op'] ?? null,
            'linha' => $programacao['lin_codigo'] ?? 'N/A',
            'eficiencia' => (float) ($programacao['prg_eficiencia'] ?? 0),
            'status' => $programacao['prg_status'] ?? 'pendente',
            'base_inicio' => $programacao['prg_base_inicio'] ?? null,
        ],
        'itens' => $itens,
        'schedule' => $schedule
    ], JSON_UNESCAPED_UNICODE);
}

/**
 * Formata dados para Frappe Gantt
 * Transforma o schedule em tasks para o gráfico
 */
function handleGantt(ProgramacaoRepository $repo): void
{
    $id = (int) ($_GET['id'] ?? 0);
    if ($id <= 0) {
        http_response_code(400);
        echo json_encode([
            'sucesso' => false,
            'erro' => 'ID inválido'
        ], JSON_UNESCAPED_UNICODE);
        return;
    }

    $programacao = $repo->getProgramacaoById($id);
    if (!$programacao) {
        http_response_code(404);
        echo json_encode([
            'sucesso' => false,
            'erro' => 'Programação não encontrada'
        ], JSON_UNESCAPED_UNICODE);
        return;
    }

    $schedule = $repo->getProgramacaoSchedule($id);
    
    // Agrupar schedule por recurso (se disponível) ou criar um único grupo
    $recursoMap = [];
    $tasks = [];
    $taskId = 1;

    foreach ((array) $schedule as $row) {
        $recurso = trim($row['sch_recurso'] ?? $row['sch_operador'] ?? 'Padrão');
        $tipo = strtolower(trim($row['sch_tipo'] ?? 'produção'));
        
        // Parse dates
        $dataInicio = $row['sch_data_inicio'] ?? null;
        $horaInicio = $row['sch_hora_inicio'] ?? '00:00';
        $horaFim = $row['sch_hora_fim'] ?? '00:00';
        $fimProducao = $row['sch_fim_producao'] ?? null;

        // Montar datetime de início
        if ($dataInicio && $horaInicio) {
            $dataInicio = is_string($dataInicio) ? substr($dataInicio, 0, 10) : date('Y-m-d', strtotime((string) $dataInicio));
            $startDateTime = "{$dataInicio}T{$horaInicio}";
        } else {
            continue; // Skip se não tem data válida
        }

        // Montar datetime de fim
        $endDateTime = null;
        if ($fimProducao) {
            // Se vem com datetime completo
            if (strpos((string) $fimProducao, 'T') !== false || strpos((string) $fimProducao, ' ') !== false) {
                $endDateTime = str_replace(' ', 'T', (string) $fimProducao);
                $endDateTime = substr($endDateTime, 0, 16); // Formato HH:MM
            } else {
                // Só tem data, usa hora fim
                $dataFim = substr((string) $fimProducao, 0, 10);
                $endDateTime = "{$dataFim}T{$horaFim}";
            }
        } else {
            // Calcular usando duração
            $duracao = (int) ($row['sch_duracao_minutos'] ?? 0);
            if ($duracao > 0) {
                try {
                    $start = new DateTime($startDateTime);
                    $start->modify("+{$duracao} minutes");
                    $endDateTime = $start->format('Y-m-dTH:i');
                } catch (Exception $e) {
                    $endDateTime = $startDateTime;
                }
            } else {
                $endDateTime = $startDateTime;
            }
        }

        // Cores por tipo
        $colorMap = [
            'setup' => '#EA580C',      // Laranja
            'produção' => '#3B82F6',   // Azul
            'pausa' => '#F8B4D1',      // Rosa
            'manutencao' => '#8B5CF6', // Purple
        ];
        $color = $colorMap[$tipo] ?? '#3B82F6';

        // Label da task
        $skuLabel = $row['sch_sku'] ?? 'N/A';
        $seqLabel = $row['sch_sequencia'] ?? '';
        $label = match ($tipo) {
            'setup' => "Setup - {$skuLabel}",
            'produção' => "{$seqLabel} - {$skuLabel}",
            'pausa' => "Pausa",
            default => $row['sch_descricao'] ?? $tipo,
        };

        // Info para tooltip
        $tooltip = [
            'Seq' => $row['sch_sequencia'] ?? 'N/A',
            'SKU' => $skuLabel,
            'Descrição' => $row['sch_descricao'] ?? '',
            'Qtd' => $row['sch_quantidade'] ?? 'N/A',
            'Duração' => $row['sch_duracao_minutos'] ? formatDurationMinutes($row['sch_duracao_minutos']) : 'N/A',
            'Tipo' => ucfirst($tipo),
        ];

        $tasks[] = [
            'id' => "task-{$taskId}",
            'name' => $label,
            'start' => $startDateTime,
            'end' => $endDateTime,
            'progress' => 100,
            'dependencies' => $taskId > 1 ? "task-" . ($taskId - 1) : '',
            'recurso' => $recurso,
            'tipo' => $tipo,
            'color' => $color,
            'custom_class' => "task-{$tipo}",
            'tooltip' => $tooltip,
        ];

        if (!isset($recursoMap[$recurso])) {
            $recursoMap[$recurso] = count($recursoMap) + 1;
        }

        $taskId++;
    }

    echo json_encode([
        'sucesso' => true,
        'programacao' => [
            'id' => (int) $programacao['prg_id'],
            'numero_op' => $programacao['prg_numero_op'] ?? null,
            'linha' => $programacao['lin_codigo'] ?? 'N/A',
            'eficiencia' => (float) ($programacao['prg_eficiencia'] ?? 0),
        ],
        'tasks' => $tasks,
        'recursos' => array_keys($recursoMap),
    ], JSON_UNESCAPED_UNICODE);
}

/**
 * Retorna dados formatados para timeline com período
 * Parâmetros: periodo=24h|12h|8h|4h|tudo (default: 24h)
 */
function handleTimeline(): void
{
    error_log('🔵 handleTimeline() iniciado');
    
    $periodo = $_GET['periodo'] ?? '24h';
    $prgId = (int) ($_GET['prg_id'] ?? 0);
    
    try {
        $pdo = \App\Database\Connection::get();
        
        // Determinar datas baseado no período
        $agora = new DateTime('now', new DateTimeZone('America/Sao_Paulo'));
        $dataIni = clone $agora;
        $dataFim = clone $agora;
        
        switch ($periodo) {
            case '4h':
                $dataFim->modify('+4 hours');
                break;
            case '8h':
                $dataFim->modify('+8 hours');
                break;
            case '12h':
                $dataFim->modify('+12 hours');
                break;
            case '24h':
                $dataFim->modify('+24 hours');
                break;
            case 'tudo':
                $dataIni->modify('-7 days');
                $dataFim->modify('+7 days');
                break;
            default:
                $dataFim->modify('+24 hours');
        }
        
        error_log('📅 Período: ' . $dataIni->format('Y-m-d H:i') . ' a ' . $dataFim->format('Y-m-d H:i'));
        
        // Query: buscar todas as programações com seus schedules
        $sql = "
            SELECT DISTINCT
                p.prg_id,
                p.prg_numero_op,
                l.lin_codigo,
                p.prg_eficiencia,
                s.sch_id,
                s.sch_sequencia,
                s.sch_tipo,
                s.sch_sku,
                s.sch_duracao_minutos,
                s.sch_data_inicio,
                s.sch_hora_inicio,
                s.sch_hora_fim
            FROM prg_programas p
            LEFT JOIN lin_linhas l ON l.lin_id = p.prg_linha_id
            LEFT JOIN sch_linhas s ON s.sch_programa_id = p.prg_id
            WHERE s.sch_id IS NOT NULL
            ORDER BY p.prg_criado_em DESC, s.sch_sequencia ASC
            LIMIT 50
        ";
        
        error_log('🟡 Executando query...');
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        error_log('✅ Query executada, ' . count($rows) . ' linhas retornadas');
        
        // Agrupar por programação
        $programacoes = [];
        foreach ($rows as $row) {
            $id = (int) $row['prg_id'];
            if (!isset($programacoes[$id])) {
                $programacoes[$id] = [
                    'id' => $id,
                    'numero_op' => $row['prg_numero_op'] ?? 'OP Sem Número',
                    'linha' => $row['lin_codigo'] ?? 'N/A',
                    'eficiencia' => (float) $row['prg_eficiencia'],
                    'linhas' => []
                ];
            }
            
            if ($row['sch_id']) {
                $programacoes[$id]['linhas'][] = [
                    'id' => (int) $row['sch_id'],
                    'sequencia' => (int) $row['sch_sequencia'],
                    'tipo' => $row['sch_tipo'],
                    'sku' => $row['sch_sku'],
                    'duracao_minutos' => (int) $row['sch_duracao_minutos'],
                    'data_inicio' => $row['sch_data_inicio'],
                    'hora_inicio' => $row['sch_hora_inicio'],
                    'hora_fim' => $row['sch_hora_fim']
                ];
            }
        }
        
        error_log('✅ Agrupando por programação: ' . count($programacoes) . ' programações');
        
        echo json_encode([
            'sucesso' => true,
            'periodo' => $periodo,
            'data_ini' => $dataIni->format('Y-m-d H:i:s'),
            'data_fim' => $dataFim->format('Y-m-d H:i:s'),
            'programacoes' => array_values($programacoes)
        ], JSON_UNESCAPED_UNICODE);
        
    } catch (Exception $e) {
        error_log('🔴 Erro em handleTimeline: ' . $e->getMessage());
        throw $e;
    }
}

/**
 * Formata minutos em HH:MM
 */
function formatDurationMinutes($minutes): string
{
    $minutes = (int) $minutes;
    $hours = (int) floor($minutes / 60);
    $mins = $minutes % 60;
    return sprintf('%02d:%02d', $hours, $mins);
}

/**
 * ETAPA 1.1 - DIAGNÓSTICO DE HISTÓRICOS
 * Analisa dados históricos: contas executadas vs planejadas
 */
function handleDiagnostico(): void
{
    error_log('🔵 handleDiagnostico() iniciado');
    
    try {
        $pdo = \App\Database\Connection::get();
        
        // Total de linhas de schedule
        $sql_total = "SELECT COUNT(*) as total FROM sch_linhas WHERE sch_id IS NOT NULL";
        $stmt = $pdo->prepare($sql_total);
        $stmt->execute();
        $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
        error_log("📊 Total linhas: $total");
        
        // Quantas foram executadas (têm fim_producao)
        $sql_exec = "SELECT COUNT(*) as executadas FROM sch_linhas WHERE sch_fim_producao IS NOT NULL";
        $stmt = $pdo->prepare($sql_exec);
        $stmt->execute();
        $executadas = $stmt->fetch(PDO::FETCH_ASSOC)['executadas'] ?? 0;
        error_log("✅ Executadas: $executadas");
        
        // Datas min/max
        $sql_datas = "
            SELECT 
                MIN(sch_data_inicio) as data_min,
                MAX(sch_data_inicio) as data_max,
                COUNT(DISTINCT sch_programa_id) as programas,
                COUNT(DISTINCT sch_sku) as skus
            FROM sch_linhas
            WHERE sch_data_inicio IS NOT NULL
        ";
        $stmt = $pdo->prepare($sql_datas);
        $stmt->execute();
        $datas = $stmt->fetch(PDO::FETCH_ASSOC);
        error_log("📅 Período: {$datas['data_min']} a {$datas['data_max']}");
        
        // Distribuição por tipo
        $sql_tipo = "
            SELECT 
                sch_tipo,
                COUNT(*) as qtd,
                COUNT(CASE WHEN sch_fim_producao IS NOT NULL THEN 1 END) as executadas
            FROM sch_linhas
            WHERE sch_tipo IS NOT NULL
            GROUP BY sch_tipo
        ";
        $stmt = $pdo->prepare($sql_tipo);
        $stmt->execute();
        $tipos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        error_log("🔹 Distribuição por tipo: " . json_encode($tipos));
        
        // Desvio médio dos executados
        $sql_desvio = "
            SELECT 
                AVG(TIMESTAMPDIFF(MINUTE, sch_inicio_planejado, sch_fim_producao)) as desvio_medio_minutos,
                COUNT(*) as com_desvio
            FROM sch_linhas
            WHERE sch_inicio_planejado IS NOT NULL 
            AND sch_fim_producao IS NOT NULL
        ";
        $stmt = $pdo->prepare($sql_desvio);
        $stmt->execute();
        $desvio = $stmt->fetch(PDO::FETCH_ASSOC);
        error_log("📊 Desvio médio: {$desvio['desvio_medio_minutos']} min");
        
        echo json_encode([
            'sucesso' => true,
            'diagnostico' => [
                'total_linhas' => (int) $total,
                'executadas' => (int) $executadas,
                'planejadas' => (int) ($total - $executadas),
                'percentual_executadas' => $total > 0 ? round(($executadas / $total) * 100, 2) : 0,
                'periodo' => [
                    'data_inicio' => $datas['data_min'],
                    'data_fim' => $datas['data_max'],
                    'programacoes' => (int) $datas['programas'],
                    'skus_unicos' => (int) $datas['skus']
                ],
                'por_tipo' => array_map(fn($t) => [
                    'tipo' => $t['sch_tipo'],
                    'total' => (int) $t['qtd'],
                    'executadas' => (int) $t['executadas'],
                    'percentual' => round((($t['executadas'] ?? 0) / ($t['qtd'] ?? 1)) * 100, 2)
                ], $tipos),
                'desvio' => [
                    'media_minutos' => round((float) ($desvio['desvio_medio_minutos'] ?? 0), 2),
                    'registros_com_desvio' => (int) ($desvio['com_desvio'] ?? 0)
                ],
                'timestamp' => date('Y-m-d H:i:s')
            ]
        ], JSON_UNESCAPED_UNICODE);
        
    } catch (Exception $e) {
        error_log('🔴 Erro em handleDiagnostico: ' . $e->getMessage());
        throw $e;
    }
}

/**
 * ETAPA 1.2 - HISTÓRICOS COM DESVIOS
 * Retorna dados históricos executados com comparação planejado vs realizado
 * Parâmetro: periodo=7d (últimos 7 dias), data_inicio, data_fim
 */
function handleHistoricos(): void
{
    error_log('🔵 handleHistoricos() iniciado');
    
    $periodo = $_GET['periodo'] ?? '7d';
    $dataInicio = $_GET['data_inicio'] ?? null;
    $dataFim = $_GET['data_fim'] ?? null;
    $prgId = (int) ($_GET['prg_id'] ?? 0);
    
    try {
        $pdo = \App\Database\Connection::get();
        $agora = new DateTime('now', new DateTimeZone('America/Sao_Paulo'));
        
        // Determinar datas
        $dataSql = clone $agora;
        
        if (!$dataInicio || !$dataFim) {
            // Usar período
            if ($periodo === '7d') {
                $dataSql->modify('-7 days');
                $dataInicio = $dataSql->format('Y-m-d');
                $dataFim = $agora->format('Y-m-d');
            } elseif ($periodo === '30d') {
                $dataSql->modify('-30 days');
                $dataInicio = $dataSql->format('Y-m-d');
                $dataFim = $agora->format('Y-m-d');
            }
        }
        
        error_log("📅 Período: $dataInicio a $dataFim");
        
        // Query: históricos enriquecida com cálculos
        $sql = "
            SELECT 
                p.prg_id,
                p.prg_numero_op,
                l.lin_codigo,
                p.prg_eficiencia,
                s.sch_id,
                s.sch_sequencia,
                s.sch_tipo,
                s.sch_sku,
                s.sch_descricao,
                s.sch_quantidade,
                s.sch_taxa_por_hora,
                s.sch_duracao_minutos,
                s.sch_sku_anterior,
                s.sch_data_inicio,
                s.sch_hora_inicio,
                s.sch_hora_fim,
                s.sch_inicio_planejado,
                s.sch_inicio_producao,
                s.sch_fim_producao,
                s.sch_produzido_estimado,
                s.sch_status,
                TIMESTAMPDIFF(MINUTE, s.sch_inicio_planejado, s.sch_fim_producao) as duracao_real_minutos,
                TIMESTAMPDIFF(MINUTE, s.sch_inicio_planejado, s.sch_fim_producao) - s.sch_duracao_minutos as desvio_minutos,
                ROUND((TIMESTAMPDIFF(MINUTE, s.sch_inicio_planejado, s.sch_fim_producao) - s.sch_duracao_minutos) / s.sch_duracao_minutos * 100, 2) as desvio_percentual
            FROM prg_programas p
            LEFT JOIN lin_linhas l ON l.lin_id = p.prg_linha_id
            LEFT JOIN sch_linhas s ON s.sch_programa_id = p.prg_id
            WHERE s.sch_fim_producao IS NOT NULL
            AND s.sch_sku IS NOT NULL
            AND DATE(s.sch_data_inicio) >= :data_inicio
            AND DATE(s.sch_data_inicio) <= :data_fim
        ";
        
        if ($prgId > 0) {
            $sql .= " AND p.prg_id = :prg_id";
        }
        
        $sql .= " ORDER BY s.sch_data_inicio DESC, s.sch_sequencia ASC";
        
        error_log('🟡 Preparando query...');
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':data_inicio', $dataInicio);
        $stmt->bindValue(':data_fim', $dataFim);
        if ($prgId > 0) {
            $stmt->bindValue(':prg_id', $prgId, PDO::PARAM_INT);
        }
        
        error_log('🟡 Executando query...');
        $stmt->execute();
        $historicos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        error_log('✅ Históricos carregados: ' . count($historicos));
        
        // Calcular resumo
        $totalExec = count($historicos);
        $noProzo = 0;
        $atrasado = 0;
        $desvioCumulativo = 0;
        
        foreach ($historicos as $h) {
            $desvio = (float) ($h['desvio_percentual'] ?? 0);
            $desvioCumulativo += abs($desvio);
            
            if ($desvio <= 5 && $desvio >= -5) {
                $noProzo++;
            } else {
                $atrasado++;
            }
        }
        
        $resumo = [
            'periodo' => $periodo,
            'data_inicio' => $dataInicio,
            'data_fim' => $dataFim,
            'total_executados' => $totalExec,
            'no_praze_pct' => $totalExec > 0 ? round(($noProzo / $totalExec) * 100, 2) : 0,
            'atrasados_pct' => $totalExec > 0 ? round(($atrasado / $totalExec) * 100, 2) : 0,
            'desvio_medio_pct' => $totalExec > 0 ? round($desvioCumulativo / $totalExec, 2) : 0
        ];
        
        // Formatar dados
        $dados = array_map(fn($h) => [
            'prg_id' => (int) $h['prg_id'],
            'numero_op' => $h['prg_numero_op'] ?? 'OP Sem Número',
            'linha' => $h['lin_codigo'] ?? 'N/A',
            'eficiencia_prg' => (float) $h['prg_eficiencia'],
            'sch_id' => (int) $h['sch_id'],
            'sequencia' => (int) $h['sch_sequencia'],
            'tipo' => $h['sch_tipo'],
            'sku' => $h['sch_sku'],
            'descricao' => $h['sch_descricao'],
            'quantidade_planejada' => (float) ($h['sch_quantidade'] ?? 0),
            'quantidade_produzida' => (float) ($h['sch_produzido_estimado'] ?? 0),
            'duracao_planejada_minutos' => (int) $h['sch_duracao_minutos'],
            'duracao_real_minutos' => (int) ($h['duracao_real_minutos'] ?? 0),
            'desvio_minutos' => (float) ($h['desvio_minutos'] ?? 0),
            'desvio_percentual' => (float) ($h['desvio_percentual'] ?? 0),
            'data_execucao' => $h['sch_data_inicio'],
            'hora_inicio_planejada' => $h['sch_hora_inicio'],
            'hora_fim_planejada' => $h['sch_hora_fim'],
            'hora_inicio_real' => $h['sch_inicio_producao'] ? substr($h['sch_inicio_producao'], 11, 5) : null,
            'hora_fim_real' => $h['sch_fim_producao'] ? substr($h['sch_fim_producao'], 11, 5) : null,
            'status_execucao' => $h['sch_status'],
            'pontual' => (float) ($h['desvio_percentual'] ?? 0) <= 5,
            'sku_anterior' => $h['sch_sku_anterior']
        ], $historicos);
        
        echo json_encode([
            'sucesso' => true,
            'resumo' => $resumo,
            'historicos' => $dados
        ], JSON_UNESCAPED_UNICODE);
        
    } catch (Exception $e) {
        error_log('🔴 Erro em handleHistoricos: ' . $e->getMessage());
        throw $e;
    }
}
