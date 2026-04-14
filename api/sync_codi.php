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
    
    if ($action === 'sync_yesterday') {
        // Verificar se é sincronização forçada (manual do botão)
        $force = isset($input['force']) && $input['force'] === true;
        
        // Verificar se já sincronizou hoje (apenas se não for forçado)
        $today = date('Y-m-d');
        
        if (!$force) {
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

        $startedAt = date('Y-m-d H:i:s');
        upsertSyncStatus($pdo, [
            'sync_key' => 'codi',
            'sync_date' => $today,
            'is_running' => 1,
            'stage_code' => 'starting',
            'stage_label' => 'Iniciando',
            'stage_detail' => 'Preparando sincronização CODI.',
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

        // Tenta o script Python primeiro. Se o ambiente não tiver o interpretador
        // ou os pacotes, faz fallback para o sincronizador PHP do sandbox.
        $pythonScript = __DIR__ . '/../sync_codi_yesterday.py';
        $venvPython = __DIR__ . '/../.venv/Scripts/python.exe';

        if (file_exists($pythonScript) && file_exists($venvPython)) {
            $output = [];
            $command = escapeshellarg($venvPython) . ' ' . escapeshellarg($pythonScript);
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

            $start = (new DateTimeImmutable('today'))->modify('-150 days')->format('Y-m-d');
            $end = (new DateTimeImmutable('today'))->modify('-1 day')->format('Y-m-d');
            $phpBinary = defined('PHP_BINARY') && PHP_BINARY ? PHP_BINARY : 'php';
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
