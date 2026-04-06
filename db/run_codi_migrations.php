<?php
/**
 * Script de Execução de Migrações CODI
 * Lê codi_migrations.sql e executa no banco de dados
 */

// Suprimir output HTML
header('Content-Type: application/json');

try {
    // ========== INCLUDES ==========
    require_once __DIR__ . '/../src/bootstrap.php';
    
    if (!isset($pdo)) {
        throw new Exception('PDO não inicializado no bootstrap.php');
    }

    // ========== LEITURA DO SQL ==========
    $migration_file = __DIR__ . '/codi_migrations.sql';
    
    if (!file_exists($migration_file)) {
        throw new Exception("Arquivo de migrações não encontrado: {$migration_file}");
    }

    $sql_content = file_get_contents($migration_file);
    
    if (empty($sql_content)) {
        throw new Exception('Arquivo de migrações está vazio');
    }

    // ========== EXECUTAÇÃO ==========
    // Dividir por ponto-e-vírgula e executar cada statement
    $statements = array_filter(
        array_map('trim', explode(';', $sql_content)),
        function($stmt) {
            return !empty($stmt) && strpos($stmt, '--') !== 0;
        }
    );

    $executed = 0;
    $errors = [];

    foreach ($statements as $stmt) {
        // Ignorar comentários
        $lines = explode("\n", $stmt);
        $clean_stmt = implode("\n", array_filter($lines, function($line) {
            return strpos(trim($line), '--') !== 0;
        }));

        if (empty(trim($clean_stmt))) {
            continue;
        }

        try {
            $pdo->exec($clean_stmt);
            $executed++;
        } catch (PDOException $e) {
            $errors[] = [
                'statement' => substr($clean_stmt, 0, 100) . '...',
                'error' => $e->getMessage()
            ];
        }
    }

    // ========== RESPOSTA ==========
    $response = [
        'status' => count($errors) === 0 ? 'sucesso' : 'parcial',
        'mensagem' => count($errors) === 0 
            ? "✅ Migrações executadas com sucesso! ({$executed} statements)"
            : "⚠️ Algumas migrações falharam",
        'statements_executados' => $executed,
        'erros' => $errors,
        'timestamp' => date('Y-m-d H:i:s')
    ];

    // Verificar tabelas criadas
    if (count($errors) === 0) {
        $tables = [
            'cdi_configuracao',
            'cdi_eventos',
            'cdi_performance',
            'cdi_sincronizacao_log',
            'cdi_sku_mapping',
            'cdi_eficiencia_medicao',
            'cdi_eficiencia_historico',
            'cdi_resumo_diario'
        ];

        $response['tabelas_verificadas'] = [];

        foreach ($tables as $table) {
            try {
                $result = $pdo->query("DESCRIBE {$table}");
                $columns = $result->fetchAll(PDO::FETCH_COLUMN, 0);
                $response['tabelas_verificadas'][$table] = [
                    'existe' => true,
                    'colunas' => count($columns)
                ];
            } catch (PDOException $e) {
                $response['tabelas_verificadas'][$table] = [
                    'existe' => false,
                    'erro' => $e->getMessage()
                ];
            }
        }
    }

    http_response_code(count($errors) === 0 ? 200 : 206);
    echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'erro',
        'mensagem' => $e->getMessage(),
        'arquivo' => $e->getFile(),
        'linha' => $e->getLine()
    ], JSON_PRETTY_PRINT);
}
?>
