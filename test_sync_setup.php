<?php
echo "Test 1: require bootstrap\n";
try {
    require_once __DIR__ . '/src/bootstrap.php';
    echo "✓ Bootstrap carregado\n";
} catch (Exception $e) {
    echo "✗ Erro: " . $e->getMessage() . "\n";
    exit(1);
}

echo "Test 2: Criar PDO connection\n";
try {
    $pdo = \App\Database\Connection::get();
    echo "✓ Conexão PDO ok\n";
} catch (Exception $e) {
    echo "✗ Erro: " . $e->getMessage() . "\n";
    exit(1);
}

echo "Test 3: Criar CodiClient\n";
try {
    $client = new \App\Codi\CodiClient();
    echo "✓ CodiClient ok\n";
} catch (Exception $e) {
    echo "✗ Erro: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\nTodos testes passaram!\n";
?>
