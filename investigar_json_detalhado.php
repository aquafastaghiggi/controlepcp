<?php
$pdo = new PDO(
    'mysql:host=127.0.0.1:3306;dbname=controlepcp_sandbox;charset=utf8mb4',
    'root',
    'k7m2y9u4'
);

echo "=== INVESTIGANDO JSON DO CODI EM DETALHES ===\n";
echo "Sua imagem mostrou essas informações:\n";
echo "  - Ordem: 0202026\n";
echo "  - Item: 2016081003\n";
echo "  - Descrição: ALVEJANTE S/ CLORO AQUAFAST...\n";
echo "  - Quantidade: 734.50 / 3,000.00\n";
echo "  - Produzida: 33.50\n";
echo "  - Previsão término: 21/04/26 23:26:52\n";

echo "\n=== PROCURANDO ESSES DADOS NO JSON ARMAZENADO ===\n";

// Pega alguns JSONs com mais detalhes
$result = $pdo->query("
    SELECT cal_id, cal_dados_json, cal_data 
    FROM codi_calendario 
    ORDER BY cal_id DESC 
    LIMIT 5
");

$count = 0;
while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
    $json = json_decode($row['cal_dados_json'], true);
    
    echo "\n--- Registro cal_id={$row['cal_id']} (Data: {$row['cal_data']}) ---\n";
    
    // Mostrar estrutura completa
    $indent = 0;
    function showArray($arr, &$indent) {
        foreach ($arr as $k => $v) {
            $spaces = str_repeat("  ", $indent);
            if (is_array($v)) {
                echo "$spaces[$k] => {\n";
                $indent++;
                showArray($v, $indent);
                $indent--;
                echo "$spaces}\n";
            } else {
                $val = is_string($v) ? substr($v, 0, 80) : $v;
                echo "$spaces[$k] => $val\n";
            }
        }
    }
    
    showArray($json, $indent);
    
    $count++;
}

echo "\n\n=== CHECKLIST DE CAMPOS PROCURADOS ===\n";
echo "Procurando por (case-insensitive):\n";
echo "  - 'ordem' ou 'ordemprod' ou 'op' → Ordem de Produção\n";
echo "  - 'item' ou 'sku' ou 'produto' → SKU/Código\n";
echo "  - 'descricao' → Descrição\n";
echo "  - 'quantidade' → Quantidade\n";
echo "  - 'previsao' → Previsão\n";

echo "\n=== BUSCANDO ESSES TERMOS NO JSON ===\n";
$result = $pdo->query("SELECT cal_dados_json FROM codi_calendario LIMIT 1");
$row = $result->fetch(PDO::FETCH_ASSOC);
if ($row) {
    $json = json_decode($row['cal_dados_json'], true);
    $jsonStr = json_encode($json);
    
    $terms = ['ordem', 'ordemprod', 'op', 'item', 'sku', 'produto', 'descricao', 'quantidade', 'previsao'];
    foreach ($terms as $term) {
        if (stripos($jsonStr, $term) !== false) {
            echo "  ✓ Encontrado: '$term'\n";
        }
    }
}
