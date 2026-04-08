<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');

echo "Iniciando...\n";

require_once __DIR__ . '/src/Database/Connection.php';

use App\Database\Connection;

echo "Conectando ao banco...\n";

try {
    $pdo = Connection::get();
    echo "✅ Conectado!\n\n";
    
    // Teste 1: Contar recursos
    $result = $pdo->query('SELECT COUNT(*) as total FROM codi_recursos');
    $recursos = $result->fetch(\PDO::FETCH_ASSOC);
    echo "Recursos: " . $recursos['total'] . "\n";
    
    // Teste 2: Listar recursos
    $result = $pdo->query('SELECT cod_id, cod_nome_recurso FROM codi_recursos LIMIT 3');
    $list = $result->fetchAll(\PDO::FETCH_ASSOC);
    
    echo "\nPrimeiros 3 recursos:\n";
    foreach ($list as $r) {
        echo "  - " . $r['cod_nome_recurso'] . "\n";
    }
    
    // Teste 3: Calendário
    $result = $pdo->query('SELECT COUNT(*) as total FROM codi_calendario');
    $cal = $result->fetch(\PDO::FETCH_ASSOC);
    echo "\nCalendário: " . $cal['total'] . " registros\n";
    
    // Teste 4: Performance
    $result = $pdo->query('SELECT COUNT(*) as total FROM codi_performance');
    $perf = $result->fetch(\PDO::FETCH_ASSOC);
    echo "Performance: " . $perf['total'] . " registros\n";
    
} catch (\Exception $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}
