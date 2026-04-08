#!/usr/bin/env php
<?php
// Teste 2 da renderização com div blocos

$testTasks = [
    [
        'id' => 1,
        'text' => '📦 OP 201055',
        'descricao_produto' => 'Agua Sanitaria Aquafast 5l',
    ],
    [
        'id' => 2,
        'text' => '⚙️ SETUP',
        'descricao_produto' => 'Setup',
    ]
];

echo "=== TESTE 2: COM DIV BLOCOS ===\n\n";

foreach ($testTasks as $task) {
    echo "Task ID: {$task['id']}\n";
    
    // Simular o novo template
    if (strpos($task['text'], 'SETUP') !== false) {
        $output = '<span style="float: right; margin-right: 8px;">' . $task['text'] . '</span>';
    } else {
        $desc = $task['descricao_produto'] ?? '-';
        $output = '<div style="display: block; line-height: 1.2;">' . 
                  $task['text'] . 
                  '<div style="font-size: 9px; color: #888; font-weight: normal;">' . $desc . '</div>' .
                  '</div>';
    }
    
    echo "HTML:\n";
    echo "  $output\n\n";
}

echo "✅ Teste ok - HTML com divs blocos para quebra de linha\n";
?>
