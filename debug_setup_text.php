<?php
require 'src/bootstrap.php';

use App\Repository\ProgramacaoRepository;
use App\Database\Connection;

$repo = new ProgramacaoRepository();
$pdo = Connection::get();

$programacoes = $repo->getAllProgramacoes(1, 0);

if (!empty($programacoes)) {
    $selectedId = (int)$programacoes[0]['prg_id'];
    $schedule = $repo->getProgramacaoSchedule($selectedId);
    
    echo "=== Verificando texto dos SETUP ===\n\n";
    
    function getOpForSku($pdo, $programId, $sku) {
        $stmt = $pdo->prepare(
            'SELECT prg_itens_op FROM prg_itens WHERE prg_programa_id = :programId AND prg_sku = :sku LIMIT 1'
        );
        $stmt->execute(['programId' => $programId, 'sku' => $sku]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['prg_itens_op'] ?? 'S/OP';
    }
    
    $count = 0;
    foreach ($schedule as $row) {
        $start = $row['sch_inicio_producao'];
        $end = $row['sch_fim_producao'];
        
        if ($start && $end) {
            $isSetup = strtolower(trim($row['sch_tipo'] ?? '')) === 'setup';
            
            $op = 'S/OP';
            if (!$isSetup && $row['sch_sku'] && $selectedId) {
                $op = getOpForSku($pdo, $selectedId, $row['sch_sku']);
            }
            
            $text = ($isSetup ? "⚙️ SETUP" : "📦 OP " . $op);
            
            if ($isSetup) {
                echo "SETUP $count:\n";
                echo "  Text: '$text'\n";
                echo "  Contains 'SETUP': " . (strpos($text, 'SETUP') !== false ? 'YES' : 'NO') . "\n";
                echo "  Check: " . ($text && strpos($text, 'SETUP') !== false ? 'MATCH' : 'NO MATCH') . "\n\n";
                $count++;
            }
        }
    }
}
?>
