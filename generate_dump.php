<?php
require 'src/bootstrap.php';

use App\Database\Connection;

try {
    $pdo = Connection::get();
    
    // Executar mysqldump via PHP
    $timestamp = date('Ymd_His');
    $dumpFile = __DIR__ . "/dump_controlepcp_{$timestamp}.sql";
    
    // Obter informações da conexão
    $dsn = getenv('DB_DSN') ?: 'mysql:host=localhost;dbname=controlepcp';
    
    // Extrair host e dbname do DSN
    preg_match('/host=([^;]+)/', $dsn, $hostMatch);
    preg_match('/dbname=([^;]+)/', $dsn, $dbMatch);
    
    $host = $hostMatch[1] ?? 'localhost';
    $db = $dbMatch[1] ?? 'controlepcp';
    $user = getenv('DB_USER') ?: 'root';
    $pass = getenv('DB_PASS') ?: '';
    
    // Comando mysqldump
    if (empty($pass)) {
        $cmd = "\"C:\\xampp\\mysql\\bin\\mysqldump.exe\" -h{$host} -u{$user} --default-character-set=utf8mb4 {$db} > \"{$dumpFile}\"";
    } else {
        $cmd = "\"C:\\xampp\\mysql\\bin\\mysqldump.exe\" -h{$host} -u{$user} -p{$pass} --default-character-set=utf8mb4 {$db} > \"{$dumpFile}\"";
    }
    
    echo "Gerando dump SQL...\n";
    echo "Host: $host | DB: $db | User: $user\n";
    
    $output = [];
    $returnCode = 0;
    exec($cmd, $output, $returnCode);
    
    if ($returnCode === 0 && file_exists($dumpFile)) {
        $size = filesize($dumpFile);
        echo "✅ Dump gerado com sucesso!\n";
        echo "Arquivo: $dumpFile\n";
        echo "Tamanho: " . number_format($size / 1024 / 1024, 2) . " MB\n";
    } else {
        echo "❌ Erro ao gerar dump. Código: $returnCode\n";
        echo "Output: " . implode("\n", $output) . "\n";
    }
} catch (Exception $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
}
?>
