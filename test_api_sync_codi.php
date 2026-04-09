#!/usr/bin/env php
<?php
// Teste da API de sincronização CODI

echo "=== TESTE DA API DE SINCRONIZAÇÃO CODI ===\n\n";

// Preparar POST data
$postData = json_encode([
    'action' => 'sync_yesterday',
    'force' => true
]);

// Simular request POST
$opts = stream_context_create([
    'http' => [
        'method' => 'POST',
        'header' => 'Content-Type: application/json' . "\r\n",
        'content' => $postData,
    ]
]);

echo "1️⃣  Enviando POST para api/sync_codi.php...\n";
echo "   Dados: " . $postData . "\n\n";

$response = @file_get_contents('http://localhost:8081/api/sync_codi.php', false, $opts);

if ($response === false) {
    echo "❌ Erro ao conectar à API\n";
    echo "   Tentando localhost:8000 (padrão XAMPP)\n";
    $response = @file_get_contents('http://localhost:8000/api/sync_codi.php', false, $opts);
    
    if ($response === false) {
        echo "❌ Não conseguiu conectar. Testando diretamente.\n\n";
        // Testar direto
        require __DIR__ . '/src/bootstrap.php';
        
        use App\Database\Connection;
        
        header('Content-Type: application/json');
        
        try {
            $pdo = Connection::get();
            echo "✅ Conexão com banco: OK\n";
            
            $stmt = $pdo->query('SELECT COUNT(*) FROM realizado_2026_excel');
            $count = $stmt->fetchColumn();
            echo "   Registros em realizado_2026_excel: $count\n";
            
        } catch (Exception $e) {
            echo "❌ Erro: " . $e->getMessage() . "\n";
        }
        exit;
    }
}

echo "2️⃣  Resposta recebida:\n";
echo "   Status: " . strlen($response) . " bytes\n";

// Validar JSON
$decoded = json_decode($response, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    echo "❌ Erro ao decodificar JSON: " . json_last_error_msg() . "\n";
    echo "   Resposta bruta:\n" . substr($response, 0, 500) . "\n";
} else {
    echo "✅ JSON válido\n\n";
    echo "Resultado:\n";
    echo "  success: " . ($decoded['success'] ? 'true' : 'false') . "\n";
    echo "  message: " . $decoded['message'] . "\n";
    
    if (isset($decoded['recordsInserted'])) {
        echo "  registros inseridos: " . $decoded['recordsInserted'] . "\n";
    }
}
?>
