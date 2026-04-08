<?php
/**
 * Teste da API de Sincronização CODI
 * Simula uma chamada POST ao endpoint
 */

// Setup environment
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['CONTENT_TYPE'] = 'application/json';

// Criar um stream fake para php://input
$json_data = '{"action":"sync_yesterday"}';

// Usar stream_context_create para interceptar php://input
stream_wrapper_register('php-input', 'PHPInputWrapper');

class PHPInputWrapper {
    private $data = '';
    private $position = 0;
    
    public function stream_open($path, $mode, $options, &$opened_path) {
        global $json_data;
        $this->data = $json_data;
        $this->position = 0;
        return true;
    }
    
    public function stream_read($count) {
        $substr = substr($this->data, $this->position, $count);
        $this->position += strlen($substr);
        return $substr;
    }
    
    public function stream_eof() {
        return $this->position >= strlen($this->data);
    }
}

// Interceptar php://input
if (function_exists('stream_wrapper_register')) {
    stream_wrapper_register('php-input-override', 'PHPInputWrapper');
}

// Melhor: criar um mock direto
require __DIR__ . '/src/bootstrap.php';

use App\Database\Connection;

// Output JSON
header('Content-Type: application/json');

// Mock a entrada
$input = json_decode($json_data, true);

// Executar a lógica
try {
    $pdo = Connection::get();
    
    if (!is_array($input) || !isset($input['action'])) {
        throw new Exception('Ação não especificada');
    }
    
    $action = $input['action'];
    
    if ($action === 'sync_yesterday') {
        $today = date('Y-m-d');
        
        $checkStmt = $pdo->prepare(
            'SELECT COUNT(*) FROM realizado_2026_excel WHERE DATE(imported_at) = ?'
        );
        $checkStmt->execute([$today]);
        $countToday = (int)$checkStmt->fetchColumn();
        
        if ($countToday > 0) {
            echo json_encode([
                'success' => false,
                'message' => "Já foi sincronizado hoje ($countToday registros inseridos). Próxima sincronização disponível amanhã.",
                'alreadySynced' => true,
                'recordsToday' => $countToday
            ]);
            exit;
        }
        
        $pythonScript = __DIR__ . '/sync_codi_yesterday.py';
        
        if (!file_exists($pythonScript)) {
            throw new Exception("Script Python não encontrado: $pythonScript");
        }
        
        $output = [];
        $returnCode = 0;
        $command = escapeshellcmd("python \"$pythonScript\"");
        exec($command . " 2>&1", $output, $returnCode);
        
        $outputText = implode("\n", $output);
        
        if ($returnCode !== 0) {
            throw new Exception("Erro ao executar script Python (código: $returnCode):\n$outputText");
        }
        
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
