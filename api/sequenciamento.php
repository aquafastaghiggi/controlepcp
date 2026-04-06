<?php

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

use App\Auth\Auth;
use App\Repository\ProgramacaoRepository;

Auth::startSession();
Auth::requireLoginApi();

header('Content-Type: application/json; charset=utf-8');

$action = $_GET['action'] ?? 'listar';
$repo = new ProgramacaoRepository();

try {
    switch ($action) {
        case 'listar':
            handleListar($repo);
            break;

        case 'detalhe':
            handleDetalhe($repo);
            break;

        case 'gantt':
            handleGantt($repo);
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
        'erro' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}

/**
 * Listar programações que têm schedule (histórico)
 */
function handleListar(ProgramacaoRepository $repo): void
{
    $limit = (int) ($_GET['limit'] ?? 50);
    $page = (int) ($_GET['page'] ?? 1);
    $offset = ($page - 1) * $limit;

    // Buscar programações que têm schedule
    $pdo = $repo->getConnection();
    
    $stmt = $pdo->prepare("
        SELECT DISTINCT
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
        GROUP BY p.prg_id
        ORDER BY p.prg_criado_em DESC, p.prg_id DESC
        LIMIT :limit OFFSET :offset
    ");
    
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $programacoes = $stmt->fetchAll(PDO::FETCH_ASSOC);

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

    echo json_encode([
        'sucesso' => true,
        'data' => $data,
        'paginacao' => [
            'pagina' => $page,
            'limite' => $limit,
            'total' => count($data)
        ]
    ], JSON_UNESCAPED_UNICODE);
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
 * Formata minutos em HH:MM
 */
function formatDurationMinutes($minutes): string
{
    $minutes = (int) $minutes;
    $hours = (int) floor($minutes / 60);
    $mins = $minutes % 60;
    return sprintf('%02d:%02d', $hours, $mins);
}
