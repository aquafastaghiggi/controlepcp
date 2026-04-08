<?php
require 'src/Database/Connection.php';
use App\Database\Connection;

$pdo = Connection::get();

echo "=== VERIFICAR ESTRUTURA DO JSON DE PERFORMANCE ===\n";
$result = $pdo->query('SELECT perf_dados_json FROM codi_performance LIMIT 1');
$row = $result->fetch(PDO::FETCH_ASSOC);

if ($row) {
    $json = json_decode($row['perf_dados_json'], true);
    echo json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    
    echo "\n=== CHAVES DISPONÍVEIS ===\n";
    echo implode(", ", array_keys($json)) . "\n";
    
    if (isset($json['order'])) {
        echo "\n=== DADOS DO 'order' ===\n";
        echo json_encode($json['order'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    }
    
    if (isset($json['produçao'])) {
        echo "\n=== DADOS DO 'produçao' ===\n";
        echo json_encode($json['produçao'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    }
} else {
    echo "Nenhum registro encontrado\n";
}

echo "\n=== AMOSTRA DE 5 REGISTROS (ESTRUTURA) ===\n";
$result = $pdo->query('SELECT perf_codigo_codi, perf_dados_json FROM codi_performance LIMIT 5');

foreach ($result->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $json = json_decode($row['perf_dados_json'], true);
    echo "\n[ID " . $row['perf_codigo_codi'] . "]:\n";
    
    // Procurar por campos que possam conter OP
    foreach ($json as $key => $value) {
        if (is_string($value) && (strpos(strtolower($key), 'order') !== false || strpos(strtolower($key), 'producao') !== false || strpos(strtolower($key), 'op') !== false)) {
            echo "  $key: $value\n";
        }
    }
    
    // Se tem um array 'order', mostrar conteúdo
    if (isset($json['order']) && is_array($json['order'])) {
        echo "  Chaves em 'order': " . implode(", ", array_keys($json['order'])) . "\n";
    }
}
?>
