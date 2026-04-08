<?php
require 'src/bootstrap.php';

use App\Repository\ProgramacaoRepository;

$repo = new ProgramacaoRepository();
$programacoes = $repo->getAllProgramacoes(1, 0);

if (!empty($programacoes)) {
    $selectedId = (int)$programacoes[0]['prg_id'];
    $schedule = $repo->getProgramacaoSchedule($selectedId);
    
    echo "=== Analisando sch_tipo para programa $selectedId ===\n\n";
    
    foreach ($schedule as $idx => $row) {
        echo "Item $idx:\n";
        echo "  sch_tipo: '" . ($row['sch_tipo'] ?? 'NULL') . "'(len:" . strlen($row['sch_tipo'] ?? '') . ")\n";
        echo "  sch_sku: " . ($row['sch_sku'] ?? 'NULL') . "\n";
        echo "  Bytes: " . bin2hex($row['sch_tipo'] ?? '') . "\n";
        echo "\n";
    }
}
?>
