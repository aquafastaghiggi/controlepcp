<?php
header('Content-Type: text/html; charset=utf-8');

try {
    $pdo = new PDO(
        "mysql:host=localhost;port=3306;dbname=controlepcp_sandbox;charset=utf8mb4",
        'root',
        'k7m2y9u4',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    echo "<pre>";
    echo "=== VERIFICANDO FERIADOS CADASTRADOS ===\n\n";
    
    $sql = "SELECT cal_data, cal_nome FROM cal_feriados ORDER BY cal_data";
    $result = $pdo->query($sql);
    $feriadosList = $result->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Total de feriados: " . count($feriadosList) . "\n\n";
    
    foreach ($feriadosList as $f) {
        echo sprintf("%s - %s\n", $f['cal_data'], $f['cal_nome']);
    }
    
    echo "\n" . str_repeat("=", 100) . "\n";
    echo "Procurando 2026-04-03:\n";
    
    $sql = "SELECT * FROM cal_feriados WHERE cal_data = '2026-04-03'";
    $result = $pdo->query($sql);
    $found = $result->fetch(PDO::FETCH_ASSOC);
    
    if ($found) {
        echo "✓ ENCONTRADO: " . json_encode($found) . "\n";
    } else {
        echo "✗ NÃO ENCONTRADO\n";
    }
    
    echo "</pre>";
    
} catch (Exception $e) {
    echo "Erro: " . $e->getMessage();
}
?>
