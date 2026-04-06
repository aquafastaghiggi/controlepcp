<?php
/**
 * debug_simple.php - Teste super simples
 * URL: http://192.168.8.123:8081/api/debug_simple.php
 */

echo "API Debug Simple\n";
echo "================\n\n";

echo "1. PHP Version: " . PHP_VERSION . "\n";
echo "2. Current Dir: " . getcwd() . "\n";
echo "3. __DIR__: " . __DIR__ . "\n";

echo "\n4. Testando conexão MySQLi direta:\n";
$mysqli = new mysqli('localhost', 'root', '', 'controlepcp');

if ($mysqli->connect_error) {
    echo "❌ Conexão MySQLi falhou: " . $mysqli->connect_error . "\n";
} else {
    echo "✅ Conexão MySQLi OK\n";
    
    $result = $mysqli->query("SELECT COUNT(*) as cnt FROM prg_programas");
    if ($result) {
        $row = $result->fetch_assoc();
        echo "   prg_programas: " . $row['cnt'] . " registros\n";
    }
    
    $result2 = $mysqli->query("SELECT COUNT(*) as cnt FROM sch_linhas");
    if ($result2) {
        $row2 = $result2->fetch_assoc();
        echo "   sch_linhas: " . $row2['cnt'] . " registros\n";
    }
}

echo "\n5. Checando arquivos:\n";
$files = [
    'bootstrap' => __DIR__ . '/../src/bootstrap.php',
    'Connection' => __DIR__ . '/../src/Database/Connection.php',
    'ProgramacaoRepository' => __DIR__ . '/../src/Repository/ProgramacaoRepository.php',
];

foreach ($files as $name => $path) {
    echo "   $name: " . (file_exists($path) ? '✅ EXISTS' : '❌ NOT FOUND') . "\n";
}

echo "\nDone!\n";
?>
