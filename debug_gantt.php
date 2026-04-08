<?php
require 'src/bootstrap.php';

use App\Repository\ProgramacaoRepository;

$repo = new ProgramacaoRepository();
$programacoes = $repo->getAllProgramacoes(3, 0);

echo "=== Test getAllProgramacoes ===\n";
var_dump($programacoes);

echo "\n=== Se houver primeiro item ===\n";
if (!empty($programacoes[0])) {
    echo "prg_numero_op: " . ($programacoes[0]['prg_numero_op'] ?? 'NULL') . "\n";
    echo "prg_id: " . ($programacoes[0]['prg_id'] ?? 'NULL') . "\n";
    echo "Chaves do array: " . implode(', ', array_keys($programacoes[0])) . "\n";
}
?>
