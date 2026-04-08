<?php
$pdo = new PDO('mysql:host=localhost;dbname=controlepcp_sandbox', 'root', 'k7m2y9u4');

echo "Colunas de sch_linhas:\n";
$cols = $pdo->query("DESCRIBE sch_linhas")->fetchAll(PDO::FETCH_ASSOC);
foreach ($cols as $col) {
    echo "  - {$col['Field']} ({$col['Type']})\n";
}

echo "\nColunas de prg_programas:\n";
$cols = $pdo->query("DESCRIBE prg_programas")->fetchAll(PDO::FETCH_ASSOC);
foreach ($cols as $col) {
    echo "  - {$col['Field']} ({$col['Type']})\n";
}

echo "\nColunas de prg_itens:\n";
$cols = $pdo->query("DESCRIBE prg_itens")->fetchAll(PDO::FETCH_ASSOC);
foreach ($cols as $col) {
    echo "  - {$col['Field']} ({$col['Type']})\n";
}
