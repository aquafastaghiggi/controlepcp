<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$pdo = new PDO('mysql:host=127.0.0.1:3306;dbname=controlepcp_sandbox;charset=utf8mb4','root','k7m2y9u4');

echo "=== PROCURANDO 3734.0 ===\n\n";

// Primeiro, ver estrutura da tabela
echo "Colunas de codi_calendario:\n";
$result = $pdo->query("DESCRIBE codi_calendario");
$cols = $result->fetchAll(PDO::FETCH_COLUMN);
echo "- " . implode("\n- ", $cols) . "\n\n";

// Procurar por 3734 em toda a tabela
echo "Procurando registros com quantidade próxima de 3734...\n";
$sql = "SELECT * FROM codi_calendario WHERE cale_quantidade > 3730 AND cale_quantidade < 3740 LIMIT 10";
$result = $pdo->query($sql);
$rows = $result->fetchAll(PDO::FETCH_ASSOC);
echo "Encontrados: " . count($rows) . " registros\n\n";

if (count($rows) > 0) {
    foreach ($rows as $row) {
        echo json_encode($row, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
    }
}

echo "\n=== FILTRAR POR OPERAÇÃO 201055 ===\n\n";

// Procurar por operação com 201055
$sql = "SELECT cale_id, cale_data, cale_quantidade, cale_operacao, cale_recurso 
        FROM codi_calendario 
        WHERE cale_operacao LIKE '%201055%' OR cale_operacao LIKE '%0201055%'
        ORDER BY cale_data";
$result = $pdo->query($sql);
$rows = $result->fetchAll(PDO::FETCH_ASSOC);
echo "OP 201055 em codi_calendario: " . count($rows) . " registros\n\n";

if (count($rows) > 0) {
    foreach ($rows as $row) {
        echo "ID: {$row['cale_id']} | Data: {$row['cale_data']} | Qtd: {$row['cale_quantidade']} | Op: {$row['cale_operacao']} | Recurso: {$row['cale_recurso']}\n";
    }
}
?>

