#!/usr/bin/env php
<?php
// Teste 3: Quebra de linha com tamanho reduzido

$testTasks = [
    [
        'id' => 1,
        'text' => '📦 OP 201055',
        'descricao_produto' => 'Agua Sanitaria Aquafast 5l',
    ],
    [
        'id' => 2,
        'text' => '📦 OP 201613',
        'descricao_produto' => 'Alvejante S/ Cloro Aquafast 3l',
    ]
];

echo "=== TESTE 3: QUEBRA DE LINHA + TEXTO PEQUENO ===\n\n";

// Opção 1: Usar \n (quebra de linha JavaScript)
echo "OPÇÃO 1: Com \\n (quebra de linha JavaScript):\n";
foreach ($testTasks as $task) {
    $desc = $task['descricao_produto'] ?? '-';
    $output = $task['text'] . "\n" . '<small style="color: #999; font-size: 8px;">' . $desc . '</small>';
    echo "  " . json_encode($output) . "\n";
}

echo "\nOPÇÃO 2: Com &#10; (quebra de linha HTML):\n";
foreach ($testTasks as $task) {
    $desc = $task['descricao_produto'] ?? '-';
    $output = $task['text'] . '&#10;' . '<small style="color: #999; font-size: 8px;">' . $desc . '</small>';
    echo "  " . json_encode($output) . "\n";
}

echo "\nOPÇÃO 3: Com tag <br> simples:\n";
foreach ($testTasks as $task) {
    $desc = $task['descricao_produto'] ?? '-';
    $output = $task['text'] . '<br/><small style="color: #999; font-size: 8px;">' . $desc . '</small>';
    echo "  " . json_encode($output) . "\n";
}

echo "\n✅ Testes de codificação completos\n";
?>
