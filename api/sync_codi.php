<?php
/**
 * API de Sincronização CODI
 * Controla a sincronização diária de dados do CODI para realizado_2026_excel
 */

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

use App\Database\Connection;

header('Content-Type: application/json');

try {
    $pdo = Connection::get();
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!is_array($input) || !isset($input['action'])) {
        throw new Exception('Ação não especificada');
    }
    
    $action = $input['action'];
    
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
        
        // Executar script Python para sincronizar
        $pythonScript = __DIR__ . '/../sync_codi_yesterday.py';
        $venvPython = __DIR__ . '/../.venv/Scripts/python.exe';
        
        if (!file_exists($pythonScript)) {
            throw new Exception("Script Python não encontrado: $pythonScript");
        }
        
        if (!file_exists($venvPython)) {
            $venvPython = 'python'; // Fallback para python global
        }
        
        // Executar com saída capturada
        $output = [];
        $returnCode = 0;
        $command = escapeshellcmd("\"$venvPython\" \"$pythonScript\"");
        exec($command . " 2>&1", $output, $returnCode);
        
        $outputText = implode("\n", $output);
        
        if ($returnCode !== 0) {
            throw new Exception("Erro ao executar script Python (código: $returnCode):\n$outputText");
        }
        
        // Contar registros inseridos
        $countStmt = $pdo->prepare(
            'SELECT COUNT(*) FROM realizado_2026_excel WHERE DATE(imported_at) = ?'
        );
        $countStmt->execute([$today]);
        $newRecords = (int)$countStmt->fetchColumn();
        
        echo json_encode([
            'success' => true,
            'message' => "Sincronização concluída! $newRecords registros inseridos de ontem.",
            'recordsInserted' => $newRecords,
            'syncTime' => date('d/m/Y H:i:s'),
            'scriptOutput' => trim($outputText)
        ]);
        
    } else {
        throw new Exception("Ação desconhecida: $action");
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'error' => true
    ]);
}
?>
