<?php
$pdo = new PDO(
    'mysql:host=127.0.0.1:3306;dbname=controlepcp_sandbox;charset=utf8mb4',
    'root',
    'k7m2y9u4'
);

echo "=== TABELAS DISPONÍVEIS ===\n";
$result = $pdo->query("SHOW TABLES");
$tables = $result->fetchAll(PDO::FETCH_COLUMN);
foreach ($tables as $t) {
    echo "  - $t\n";
}

echo "\n=== CAMPOS CODI_CALENDARIO ===\n";
$result = $pdo->query("DESCRIBE codi_calendario");
while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
    echo "  {$row['Field']} ({$row['Type']})\n";
}

echo "\n=== ESTRUTURA JSON DO CODI_CALENDARIO ===\n";
$result = $pdo->query("SELECT cal_dados_json FROM codi_calendario LIMIT 1");
if ($row = $result->fetch(PDO::FETCH_ASSOC)) {
    $json = json_decode($row['cal_dados_json'], true);
    if (is_array($json)) {
        echo "  Estrutura: " . implode(", ", array_keys($json)) . "\n";
        
        // Mostrar amostra de valores
        if (isset($json['operacoes'])) {
            echo "  Operações: " . json_encode(array_slice($json['operacoes'], 0, 2)) . "\n";
        }
    } else {
        echo "  JSON não é array\n";
    }
}

echo "\n=== PERGUNTAS PRO CRUZAMENTO ===\n";
echo "1. Você tem uma TABELA DE MAPEAMENTO entre:\n";
echo "   - SKU Planejado → SKU Realizado (CODI)?\n";
echo "   - OP Planejada → Items do CODI?\n";
echo "   - Data Planejada → Data Realizada?\n";
echo "\n2. Há um arquivo Excel/CSV com correspondências?\n";
echo "\n3. CODI tem informação de qual produto/SKU foi produzido?\n";
echo "   (Dentro do cal_dados_json?)\n";
