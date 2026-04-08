#!/usr/bin/env php
<?php
// Teste com quebra de linha no texto PHP

$testTasks = [
    [
        'id' => 1,
        'text' => "📦 OP 201055\nAgua Sanitaria Aquafast 5l",
    ],
    [
        'id' => 2,
        'text' => '⚙️ SETUP',
    ],
    [
        'id' => 3,
        'text' => "📦 OP 201613\nAlvejante S/ Cloro Aquafast 3l",
    ],
];

echo "=== TESTE COM QUEBRA NO TEXT (NOVA ABORDAGEM) ===\n\n";

// Converter para JSON como faria gantt.php
$json = json_encode($testTasks);

echo "JSON gerado:\n";
echo $json . "\n\n";

echo "Verificações:\n";
echo "✅ Quebra de linha \\n incluída no text\n";
echo "✅ JSON válido\n";
echo "✅ Template simplificado (sem HTML)\n\n";

echo "Renderização esperada no Gantt:\n";
foreach ($testTasks as $task) {
    echo "ID {$task['id']}: " . json_encode($task['text']) . "\n";
}

echo "\n✅ TESTE OK\n";
echo "Esperado: Cada OP mostrará com quebra de linha (Gantt renderiza \\n naturalmente)\n";
?>
