<?php
// Script para copiar a tabela realizado_2026_excel do sandbox para produção

$conn_sandbox = new PDO(
    'mysql:host=localhost;dbname=controlepcp_sandbox',
    'root',
    'k7m2y9u4'
);

$conn_prod = new PDO(
    'mysql:host=localhost;dbname=controlepcp',
    'root',
    'k7m2y9u4'
);

// 1. Verificar estrutura no sandbox
echo "=== Verificando tabela no SANDBOX ===\n";
$result = $conn_sandbox->query("SHOW CREATE TABLE realizado_2026_excel");
$row = $result->fetch(PDO::FETCH_ASSOC);
$createTableSQL = $row['Create Table'] ?? null;

if (!$createTableSQL) {
    echo "❌ Tabela não encontrada no sandbox\n";
    exit(1);
}

echo "✅ Tabela encontrada no sandbox\n";
echo $createTableSQL . "\n\n";

// 2. Tentar criar em produção
echo "=== Tentando criar tabela em PRODUÇÃO ===\n";

try {
    // Primeiro, verificar se já existe
    $result = $conn_prod->query("DESC realizado_2026_excel");
    echo "❌ Tabela JÁ existe em produção. Pulando criação.\n";
} catch (Exception $e) {
    // Tabela não existe, vamos criar
    echo "Tabela não existe em produção. Criando...\n";
    
    // Modificar o SQL para usar `controlepcp` ao invés de `controlepcp_sandbox`
    $createTableSQL = str_replace('`controlepcp_sandbox`', '`controlepcp`', $createTableSQL);
    
    try {
        $conn_prod->exec($createTableSQL);
        echo "✅ Tabela criada com sucesso em produção!\n";
    } catch (Exception $e) {
        echo "❌ Erro ao criar tabela: " . $e->getMessage() . "\n";
        exit(1);
    }
}

// 3. Copiar dados (opcional)
echo "\n=== Copiando dados ===\n";
$countSandbox = $conn_sandbox->query("SELECT COUNT(*) FROM realizado_2026_excel")->fetchColumn();
$countProd = $conn_prod->query("SELECT COUNT(*) FROM realizado_2026_excel")->fetchColumn();

echo "Sandbox: $countSandbox registros\n";
echo "Produção: $countProd registros\n";

if ($countProd == 0 && $countSandbox > 0) {
    echo "\nCopiando dados do sandbox para produção...\n";
    $rows = $conn_sandbox->query("SELECT * FROM realizado_2026_excel")->fetchAll(PDO::FETCH_ASSOC);
    
    $sql = "INSERT INTO realizado_2026_excel (data_evento, ordem_op, quantidade) VALUES (?, ?, ?)";
    $stmt = $conn_prod->prepare($sql);
    
    $copied = 0;
    foreach ($rows as $ro) {
        try {
            $stmt->execute([
                $ro['data_evento'],
                $ro['ordem_op'],
                $ro['quantidade']
            ]);
            $copied++;
        } catch (Exception $e) {
            // continue even if one fails
        }
    }
    
    echo "✅ $copied dados copiados!\n";
} elseif ($countProd > 0) {
    echo "Produção JÁ tem dados. Não copiando.\n";
}

echo "\n✅ Concluído!\n";
?>
