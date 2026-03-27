<?php

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

use App\Auth\Auth;
use App\Repository\ProgramacaoRepository;
use App\Support\DateTimeHelper;

Auth::startSession();
Auth::requireLoginApi();

header('Content-Type: application/json; charset=utf-8');

$method = $_SERVER['REQUEST_METHOD'];
$repo = new ProgramacaoRepository();

try {
    if ($method === 'GET') {
        handleGet($repo);
    } elseif ($method === 'POST') {
        handlePost($repo);
    } elseif ($method === 'PUT') {
        handlePut($repo);
    } elseif ($method === 'DELETE') {
        handleDelete($repo);
    } else {
        http_response_code(405);
        echo json_encode(['message' => 'Método não permitido'], JSON_UNESCAPED_UNICODE);
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}

function handleGet(ProgramacaoRepository $repo): void
{
    // Se tem O parâmetro 'op' na query, buscar por OP
    if (!empty($_GET['op'])) {
        $op = (string) $_GET['op'];
        $programacao = $repo->getProgramacaoByOp($op);

        if (!$programacao) {
            http_response_code(404);
            echo json_encode(['message' => 'Programação não encontrada'], JSON_UNESCAPED_UNICODE);
            return;
        }

        // Incluir itens e schedule
        $programacao['itens'] = $repo->getProgramacaoItens((int) $programacao['prg_id']);
        $programacao['schedule'] = $repo->getProgramacaoSchedule((int) $programacao['prg_id']);

        echo json_encode($programacao, JSON_UNESCAPED_UNICODE);
        return;
    }

    // Se tem 'id' na query, buscar por ID
    if (isset($_GET['id'])) {
        $rawId = (string) $_GET['id'];
        $filteredId = filter_var(trim($rawId), FILTER_VALIDATE_INT);
        if ($filteredId === false || $filteredId === null || $filteredId <= 0) {
            http_response_code(400);
            echo json_encode(['message' => 'ID inválido'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $id = (int) $filteredId;
        $programacao = $repo->getProgramacaoById($id);

        if (!$programacao) {
            http_response_code(404);
            echo json_encode(['message' => 'Programação não encontrada'], JSON_UNESCAPED_UNICODE);
            return;
        }

        // Incluir itens e schedule
        $programacao['itens'] = $repo->getProgramacaoItens($id);
        $programacao['schedule'] = $repo->getProgramacaoSchedule($id);

        echo json_encode($programacao, JSON_UNESCAPED_UNICODE);
        return;
    }

    // Buscar todas as programações com paginação
    $limit = (int) ($_GET['limit'] ?? 100);
    $page = (int) ($_GET['page'] ?? 1);
    $offset = ($page - 1) * $limit;

    $programacoes = $repo->getAllProgramacoes($limit, $offset);

    echo json_encode([
        'data' => $programacoes,
        'page' => $page,
        'limit' => $limit,
        'total' => count($programacoes),
    ], JSON_UNESCAPED_UNICODE);
}

function handlePost(ProgramacaoRepository $repo): void
{
    $payload = json_decode((string) file_get_contents('php://input'), true);

    if (!is_array($payload)) {
        http_response_code(400);
        echo json_encode(['message' => 'Payload inválido'], JSON_UNESCAPED_UNICODE);
        return;
    }

    $numeroOp = !empty($payload['prg_numero_op']) ? (string) $payload['prg_numero_op'] : null;
    $lineCode = (string) ($payload['lin_codigo'] ?? 'L2');
    $baseStartStr = (string) ($payload['prg_base_inicio'] ?? '');
    $queryDateTimeStr = (string) ($payload['prg_data_consulta'] ?? '');
    $efficiency = (float) ($payload['prg_eficiencia'] ?? 100);
    $status = (string) ($payload['prg_status'] ?? 'rascunho');

    if (!$baseStartStr) {
        http_response_code(422);
        echo json_encode(['message' => 'Informe a data/hora base (prg_base_inicio)'], JSON_UNESCAPED_UNICODE);
        return;
    }

    $baseStart = DateTimeHelper::fromLocalInput($baseStartStr);
    if (!$baseStart) {
        http_response_code(422);
        echo json_encode(['message' => 'Data/hora base inválida'], JSON_UNESCAPED_UNICODE);
        return;
    }

    $queryDateTime = null;
    if ($queryDateTimeStr) {
        $queryDateTime = DateTimeHelper::fromLocalInput($queryDateTimeStr);
    }

    $programId = $repo->createProgramacao($numeroOp, $lineCode, $baseStart, $queryDateTime, $efficiency, $status);

    http_response_code(201);
    echo json_encode([
        'prg_id' => $programId,
        'message' => 'Programação criada com sucesso',
    ], JSON_UNESCAPED_UNICODE);
}

function handlePut(ProgramacaoRepository $repo): void
{
    $payload = json_decode((string) file_get_contents('php://input'), true);

    if (!is_array($payload)) {
        http_response_code(400);
        echo json_encode(['message' => 'Payload inválido'], JSON_UNESCAPED_UNICODE);
        return;
    }

    $id = (int) ($payload['prg_id'] ?? 0);
    if (!$id) {
        http_response_code(422);
        echo json_encode(['message' => 'Informe o ID da programação (prg_id)'], JSON_UNESCAPED_UNICODE);
        return;
    }

    $existente = $repo->getProgramacaoById($id);
    if (!$existente) {
        http_response_code(404);
        echo json_encode(['message' => 'Programação não encontrada'], JSON_UNESCAPED_UNICODE);
        return;
    }

    $numeroOp = !empty($payload['prg_numero_op']) ? (string) $payload['prg_numero_op'] : null;

    $baseStart = null;
    if (!empty($payload['prg_base_inicio'])) {
        $baseStart = DateTimeHelper::fromLocalInput((string) $payload['prg_base_inicio']);
        if (!$baseStart) {
            http_response_code(422);
            echo json_encode(['message' => 'Data/hora base inválida'], JSON_UNESCAPED_UNICODE);
            return;
        }
    }

    $queryDateTime = null;
    if (!empty($payload['prg_data_consulta'])) {
        $queryDateTime = DateTimeHelper::fromLocalInput((string) $payload['prg_data_consulta']);
    }

    $efficiency = isset($payload['prg_eficiencia']) ? (float) $payload['prg_eficiencia'] : null;
    $status = !empty($payload['prg_status']) ? (string) $payload['prg_status'] : null;

    $repo->updateProgramacao($id, $numeroOp, $baseStart, $queryDateTime, $efficiency, $status);

    echo json_encode([
        'prg_id' => $id,
        'message' => 'Programação atualizada com sucesso',
    ], JSON_UNESCAPED_UNICODE);
}

function handleDelete(ProgramacaoRepository $repo): void
{
    $payload = json_decode((string) file_get_contents('php://input'), true);

    if (!is_array($payload)) {
        http_response_code(400);
        echo json_encode(['message' => 'Payload inválido'], JSON_UNESCAPED_UNICODE);
        return;
    }

    $id = (int) ($payload['prg_id'] ?? 0);
    if (!$id) {
        http_response_code(422);
        echo json_encode(['message' => 'Informe o ID da programação (prg_id)'], JSON_UNESCAPED_UNICODE);
        return;
    }

    $existente = $repo->getProgramacaoById($id);
    if (!$existente) {
        http_response_code(404);
        echo json_encode(['message' => 'Programação não encontrada'], JSON_UNESCAPED_UNICODE);
        return;
    }

    $repo->deleteProgramacao($id);

    echo json_encode([
        'prg_id' => $id,
        'message' => 'Programação deletada com sucesso',
    ], JSON_UNESCAPED_UNICODE);
}

