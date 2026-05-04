<?php
/**
 * API de Sincronização CODI
 * Controla a sincronização diária de dados do CODI para realizado_2026_excel
 */

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

use App\Database\Connection;

header('Content-Type: application/json');

function ensureSyncStatusTable(PDO $pdo): void
{
    $pdo->exec(
        "
        CREATE TABLE IF NOT EXISTS codi_sync_status (
            sync_key VARCHAR(32) NOT NULL,
            sync_date DATE NULL,
            is_running TINYINT(1) NOT NULL DEFAULT 0,
            stage_code VARCHAR(64) NOT NULL DEFAULT 'idle',
            stage_label VARCHAR(120) NOT NULL DEFAULT 'Idle',
            stage_detail TEXT NULL,
            stage_index INT NOT NULL DEFAULT 0,
            stage_total INT NOT NULL DEFAULT 0,
            backend VARCHAR(16) NULL,
            started_at DATETIME NULL,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            finished_at DATETIME NULL,
            last_error TEXT NULL,
            records_today INT NOT NULL DEFAULT 0,
            last_sync_at DATETIME NULL,
            PRIMARY KEY (sync_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        "
    );
}

function upsertSyncStatus(PDO $pdo, array $data): void
{
    ensureSyncStatusTable($pdo);

    $sql = "
        INSERT INTO codi_sync_status (
            sync_key, sync_date, is_running, stage_code, stage_label, stage_detail,
            stage_index, stage_total, backend, started_at, updated_at, finished_at,
            last_error, records_today, last_sync_at
        ) VALUES (
            :sync_key, :sync_date, :is_running, :stage_code, :stage_label, :stage_detail,
            :stage_index, :stage_total, :backend, :started_at, NOW(), :finished_at,
            :last_error, :records_today, :last_sync_at
        )
        ON DUPLICATE KEY UPDATE
            sync_date = VALUES(sync_date),
            is_running = VALUES(is_running),
            stage_code = VALUES(stage_code),
            stage_label = VALUES(stage_label),
            stage_detail = VALUES(stage_detail),
            stage_index = VALUES(stage_index),
            stage_total = VALUES(stage_total),
            backend = VALUES(backend),
            started_at = COALESCE(codi_sync_status.started_at, VALUES(started_at)),
            updated_at = NOW(),
            finished_at = VALUES(finished_at),
            last_error = VALUES(last_error),
            records_today = VALUES(records_today),
            last_sync_at = VALUES(last_sync_at)
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        'sync_key' => $data['sync_key'] ?? 'codi',
        'sync_date' => $data['sync_date'] ?? date('Y-m-d'),
        'is_running' => !empty($data['is_running']) ? 1 : 0,
        'stage_code' => (string) ($data['stage_code'] ?? 'idle'),
        'stage_label' => (string) ($data['stage_label'] ?? 'Idle'),
        'stage_detail' => $data['stage_detail'] ?? null,
        'stage_index' => (int) ($data['stage_index'] ?? 0),
        'stage_total' => (int) ($data['stage_total'] ?? 0),
        'backend' => $data['backend'] ?? null,
        'started_at' => $data['started_at'] ?? null,
        'finished_at' => $data['finished_at'] ?? null,
        'last_error' => $data['last_error'] ?? null,
        'records_today' => (int) ($data['records_today'] ?? 0),
        'last_sync_at' => $data['last_sync_at'] ?? null,
    ]);
}

function readSyncStatus(PDO $pdo): array
{
    ensureSyncStatusTable($pdo);

    $stmt = $pdo->query("SELECT * FROM codi_sync_status WHERE sync_key = 'codi' LIMIT 1");
    $row = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : false;

    return is_array($row) ? $row : [];
}

try {
    $pdo = Connection::get();
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!is_array($input) || !isset($input['action'])) {
        throw new Exception('Ação não especificada');
    }
    
    $action = $input['action'];

    if ($action === 'status') {
        $today = date('Y-m-d');
        $statusRow = readSyncStatus($pdo);
        $statusStage = (string) ($statusRow['stage_code'] ?? 'idle');
        $isRunning = (int) ($statusRow['is_running'] ?? 0) === 1;
        $recordsTodayStmt = $pdo->prepare(
            'SELECT COUNT(*) FROM realizado_2026_excel WHERE DATE(imported_at) = ?'
        );
        $recordsTodayStmt->execute([$today]);
        $recordsToday = (int) $recordsTodayStmt->fetchColumn();
        $lastSyncAt = $statusRow['last_sync_at'] ?? null;

        echo json_encode([
            'success' => true,
            'alreadySynced' => $recordsToday > 0,
            'isRunning' => $isRunning,
            'stageCode' => $statusStage,
            'stageLabel' => (string) ($statusRow['stage_label'] ?? ($isRunning ? 'Em andamento' : 'Idle')),
            'stageDetail' => (string) ($statusRow['stage_detail'] ?? ''),
            'stageIndex' => (int) ($statusRow['stage_index'] ?? 0),
            'stageTotal' => (int) ($statusRow['stage_total'] ?? 0),
            'backend' => (string) ($statusRow['backend'] ?? ''),
            'recordsToday' => $recordsToday,
            'lastSyncAt' => $lastSyncAt ?: null,
            'syncDate' => $today,
            'finishedAt' => $statusRow['finished_at'] ?? null,
            'lastError' => $statusRow['last_error'] ?? null,
        ]);
        exit;
    }
    
    if ($action === 'sync_yesterday' || $action === 'sync_today') {
        $includeToday = ($action === 'sync_today');
        // Verificar se é sincronização forçada (manual do botão)
        $force = isset($input['force']) && $input['force'] === true;
        
        // Verificar se já sincronizou hoje (apenas se não for forçado)
        $today = date('Y-m-d');
        
        if (!$force && !$includeToday) {
            $checkStmt = $pdo->prepare(
                'SELECT COUNT(*) FROM realizado_2026_excel WHERE DATE(imported_at) = ?'
            );
            $checkStmt->execute([$today]);
            $countToday = (int)$checkStmt->fetchColumn();

            // Se já tem sincronizações de hoje, avisar (exceto se force=true)
            if ($countToday > 0) {
                echo json_encode([
                    'success' => false,
                    'message' => "Já foi sincronizado hoje ($countToday registros inseridos). Próxima sincronização disponível amanhã.",
                    'alreadySynced' => true,
                    'recordsToday' => $countToday
                ]);
                exit;
            }
        }

        if (!$force && $includeToday) {
            $checkStmt = $pdo->prepare(
                'SELECT COUNT(*) FROM realizado_2026_excel WHERE data_evento = ?'
            );
            $checkStmt->execute([$today]);
            $countForToday = (int) $checkStmt->fetchColumn();

            if ($countForToday > 0) {
                echo json_encode([
                    'success' => false,
                    'message' => "JÃ¡ existe realizado para hoje ($today). Use force=true para re-sincronizar o dia atual.",
                    'alreadySynced' => true,
                    'recordsToday' => $countForToday
                ]);
                exit;
            }
        }

        $startedAt = date('Y-m-d H:i:s');
        upsertSyncStatus($pdo, [
            'sync_key' => 'codi',
            'sync_date' => $today,
            'is_running' => 1,
            'stage_code' => 'starting',
            'stage_label' => 'Iniciando',
            'stage_detail' => $includeToday ? 'Preparando sincronização CODI (incluindo hoje).' : 'Preparando sincronização CODI.',
            'stage_index' => 1,
            'stage_total' => 6,
            'backend' => 'php-api',
            'started_at' => $startedAt,
            'finished_at' => null,
            'last_error' => null,
            'records_today' => 0,
            'last_sync_at' => null,
        ]);
        
        $outputText = '';
        $returnCode = 0;
        $usedBackend = 'python';
        $start = (new DateTimeImmutable('today'))->modify('-35 days')->format('Y-m-d');
        $end = $includeToday
            ? (new DateTimeImmutable('today'))->format('Y-m-d')
            : (new DateTimeImmutable('today'))->modify('-1 day')->format('Y-m-d');

        // Tenta o script Python primeiro. Se o ambiente não tiver o interpretador
        // ou os pacotes, faz fallback para o sincronizador PHP do sandbox.
        $pythonScript = __DIR__ . '/../sync_codi_yesterday.py';
        $venvPython = __DIR__ . '/../.venv/Scripts/python.exe';

        if (file_exists($pythonScript) && file_exists($venvPython)) {
            $output = [];
            $command = escapeshellarg($venvPython) . ' ' . escapeshellarg($pythonScript);
            if ($includeToday) {
                $command .= ' --start=' . escapeshellarg($start) . ' --end=' . escapeshellarg($end);
            }
            exec($command . ' 2>&1', $output, $returnCode);
            $outputText = implode("\n", $output);
        } else {
            $returnCode = 1;
            $outputText = 'Ambiente Python indisponível no sandbox.';
        }

        if ($returnCode !== 0) {
            $usedBackend = 'php';
            $fallbackScript = __DIR__ . '/../tools/sync_codi_backfill.php';
            if (!file_exists($fallbackScript)) {
                throw new Exception("Script PHP de fallback não encontrado: $fallbackScript");
            }

            $phpBinary = defined('PHP_BINARY') && PHP_BINARY ? (string) PHP_BINARY : '';
            // Em runtime HTTP no Windows/Apache, PHP_BINARY pode apontar para o processo errado (ex.: httpd.exe).
            // Para o fallback funcionar via HTTP, garantimos que o executável seja realmente o php.exe.
            if ($phpBinary === '' || !preg_match('/\\\\php\\.exe$/i', $phpBinary) || !is_file($phpBinary)) {
                $xamppPhp = 'C:\\xampp\\php\\php.exe';
                $phpBinary = is_file($xamppPhp) ? $xamppPhp : 'php';
            }
            $output = [];
            $command = escapeshellarg($phpBinary) . ' ' . escapeshellarg($fallbackScript)
                . ' --start=' . escapeshellarg($start)
                . ' --end=' . escapeshellarg($end)
                . ' --op=20';
            exec($command . ' 2>&1', $output, $returnCode);
            $outputText = implode("\n", $output);

            if ($returnCode !== 0) {
                throw new Exception("Erro ao executar fallback PHP (código: $returnCode):\n$outputText");
            }
        }

        // Reconstruir realizado_2026_excel sempre a partir do bruto (realizado_2026_eventos)
        // da janela atual, garantindo que o agregado reflita somente a carga mais recente.
        try {
            $pdo->beginTransaction();
            $hasRawStmt = $pdo->prepare(
                'SELECT COUNT(*) FROM realizado_2026_eventos WHERE data_evento BETWEEN ? AND ? AND (COALESCE(setup_eventos_count, 0) > 0 OR COALESCE(quantidade, 0) > 0)'
            );
            $hasRawStmt->execute([$start, $end]);
            $rawCount = (int) $hasRawStmt->fetchColumn();
            if ($rawCount <= 0) {
                throw new Exception("Nenhum evento bruto encontrado em realizado_2026_eventos para {$start} a {$end}.");
            }
            // Não apagar o histórico inteiro: limpa somente a janela reprocessada.
            $stmtDelExcel = $pdo->prepare('DELETE FROM realizado_2026_excel WHERE data_evento BETWEEN :start AND :end');
            $stmtDelExcel->execute([
                'start' => $start,
                'end' => $end,
            ]);
            $stmtRebuild = $pdo->prepare(
                "
                INSERT INTO realizado_2026_excel
                    (data_evento, ordem_op, quantidade, inicio_evento, fim_evento, parada_nomeParada, setup_duracao_minutos, setup_eventos_count)
                SELECT
                    data_evento,
                    ordem_op,
                    SUM(COALESCE(quantidade, 0)) AS quantidade,
                    MIN(inicio_evento) AS inicio_evento,
                    MAX(fim_evento) AS fim_evento,
                    CASE
                        WHEN SUM(CASE WHEN UPPER(TRIM(COALESCE(parada_nomeParada, ''))) = 'TROCA DE KIT' THEN 1 ELSE 0 END) > 0 THEN 'TROCA DE KIT'
                        WHEN SUM(CASE WHEN UPPER(TRIM(COALESCE(parada_nomeParada, ''))) = 'TROCA DE LIQUIDO' THEN 1 ELSE 0 END) > 0 THEN 'TROCA DE LIQUIDO'
                        ELSE NULLIF(MAX(NULLIF(TRIM(COALESCE(parada_nomeParada, '')), '')), '')
                    END AS parada_nomeParada,
                    SUM(COALESCE(setup_duracao_minutos, 0)) AS setup_duracao_minutos,
                    SUM(COALESCE(setup_eventos_count, 0)) AS setup_eventos_count
                FROM realizado_2026_eventos
                WHERE data_evento BETWEEN :start AND :end
                  AND (COALESCE(setup_eventos_count, 0) > 0 OR COALESCE(quantidade, 0) > 0)
                GROUP BY data_evento, ordem_op
                "
            );
            $stmtRebuild->execute([
                'start' => $start,
                'end' => $end,
            ]);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw new Exception('Falha ao reconstruir realizado_2026_excel a partir de realizado_2026_eventos: ' . $e->getMessage(), 0, $e);
        }
        
        // Contar registros inseridos
        $countStmt = $pdo->prepare(
            'SELECT COUNT(*) FROM realizado_2026_excel WHERE DATE(imported_at) = ?'
        );
        $countStmt->execute([$today]);
        $newRecords = (int)$countStmt->fetchColumn();
        $lastSyncAt = date('Y-m-d H:i:s');
        upsertSyncStatus($pdo, [
            'sync_key' => 'codi',
            'sync_date' => $today,
            'is_running' => 0,
            'stage_code' => 'done',
            'stage_label' => 'Concluído',
            'stage_detail' => "Sincronização concluída via {$usedBackend}.",
            'stage_index' => 6,
            'stage_total' => 6,
            'backend' => $usedBackend,
            'started_at' => $startedAt,
            'finished_at' => $lastSyncAt,
            'last_error' => null,
            'records_today' => $newRecords,
            'last_sync_at' => $lastSyncAt,
        ]);
        
        echo json_encode([
            'success' => true,
            'message' => "Sincronização concluída via {$usedBackend}! $newRecords registros inseridos/atualizados.",
            'recordsInserted' => $newRecords,
            'syncTime' => date('d/m/Y H:i:s'),
            'scriptOutput' => trim($outputText)
        ]);
        
    } else {
        throw new Exception("Ação desconhecida: $action");
    }
    
} catch (Exception $e) {
    if (isset($pdo) && $pdo instanceof PDO) {
        try {
            upsertSyncStatus($pdo, [
                'sync_key' => 'codi',
                'sync_date' => date('Y-m-d'),
                'is_running' => 0,
                'stage_code' => 'error',
                'stage_label' => 'Erro',
                'stage_detail' => $e->getMessage(),
                'stage_index' => 0,
                'stage_total' => 6,
                'backend' => 'api',
                'started_at' => null,
                'finished_at' => date('Y-m-d H:i:s'),
                'last_error' => $e->getMessage(),
                'records_today' => 0,
                'last_sync_at' => null,
            ]);
        } catch (Throwable $inner) {
            // Se a tabela de status falhar, devolvemos o erro principal.
        }
    }
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'error' => true
    ]);
}
?>
