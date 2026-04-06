<?php
/**
 * test_db.php - Teste de conexão com banco de dados
 * URL: http://192.168.8.123:8081/api/test_db.php
 */

error_log('🔵 test_db.php iniciado');

// Desabilitar buffers para ver logs em tempo real
ob_start();

try {
    error_log('1️⃣ Iniciando...');
    
    error_log('2️⃣ Incluindo bootstrap...');
    require_once __DIR__ . '/../src/bootstrap.php';
    error_log('✅ Bootstrap carregado');
    
    // Tentar conectar
    error_log('3️⃣ Obtendo PDO via Connection::get()...');
    $pdo = \App\Database\Connection::get();
    error_log('✅ PDO obtido');
    
    // Teste simples SELECT
    error_log('4️⃣ Testando query simples...');
    $stmt = $pdo->query("SELECT 1 as ok");
    error_log('✅ Query executada');
    
    if ($stmt) {
        $row = $stmt->fetch();
        error_log('✅ Resultado: ' . json_encode($row));
    }
    
    // Contar prg_programas
    error_log('5️⃣ Contando prg_programas...');
    $stmt2 = $pdo->query("SELECT COUNT(*) as total FROM prg_programas");
    $row2 = $stmt2->fetch();
    error_log('✅ Total prg_programas: ' . $row2['total']);
    
    // Contar sch_linhas
    error_log('6️⃣ Contando sch_linhas...');
    $stmt3 = $pdo->query("SELECT COUNT(*) as total FROM sch_linhas");
    $row3 = $stmt3->fetch();
    error_log('✅ Total sch_linhas: ' . $row3['total']);
    
    // Resposta de sucesso
    http_response_code(200);
    echo json_encode([
        'sucesso' => true,
        'mensagem' => 'Conexão com banco OK',
        'conectado' => true,
        'banco' => [
            'prg_programas' => (int) $row2['total'],
            'sch_linhas' => (int) $row3['total']
        ]
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    error_log('🔴 ERRO: ' . $e->getMessage());
    error_log('📍 Em: ' . $e->getFile() . ':' . $e->getLine());
    error_log('🔍 Trace: ' . $e->getTraceAsString());
    
    http_response_code(500);
    echo json_encode([
        'sucesso' => false,
        'mensagem' => 'Erro ao conectar',
        'erro' => $e->getMessage(),
        'arquivo' => basename($e->getFile()),
        'linha' => $e->getLine()
    ], JSON_UNESCAPED_UNICODE);
}

ob_end_flush();
?>
