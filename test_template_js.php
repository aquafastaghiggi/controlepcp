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
    
    function getOpForSku($pdo, $programId, $sku) {
        $stmt = $pdo->prepare(
            'SELECT prg_itens_op FROM prg_itens WHERE prg_programa_id = :programId AND prg_sku = :sku LIMIT 1'
        );
        $stmt->execute(['programId' => $programId, 'sku' => $sku]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['prg_itens_op'] ?? 'S/OP';
    }
    
    $tasks = [];
    foreach ($schedule as $row) {
        $start = $row['sch_inicio_producao'];
        $end = $row['sch_fim_producao'];
        
        if ($start && $end) {
            $isSetup = strtolower(trim($row['sch_tipo'] ?? '')) === 'setup';
            
            $op = 'S/OP';
            if (!$isSetup && $row['sch_sku'] && $selectedId) {
                $op = getOpForSku($pdo, $selectedId, $row['sch_sku']);
            }
            
            $tasks[] = [
                'id' => (int)$row['sch_id'],
                'text' => ($isSetup ? "⚙️ SETUP" : "📦 OP " . $op),
                'start_date' => date('d-m-Y H:i', strtotime($start)),
                'end_date' => date('d-m-Y H:i', strtotime($end)),
                'color' => $isSetup ? '#e67e22' : '#3498db',
                'progress' => 1,
                'open' => true,
                'sku' => $row['sch_sku'] ?: '-',
                'tipo' => $row['sch_tipo']
            ];
        }
    }
    
    echo "=== Testando template JavaScript ===\n\n";
    echo "Primeiros 5 tasks com teste de indexOf:\n\n";
    
    foreach (array_slice($tasks, 0, 5) as $task) {
        $hasSetup = strpos($task['text'], 'SETUP') !== false;
        echo "Text: {$task['text']}\n";
        echo "indexOf('SETUP'): " . ($hasSetup ? 'FOUND (should align right)' : 'NOT FOUND (align left)') . "\n";
        echo "---\n";
    }
}
?>
