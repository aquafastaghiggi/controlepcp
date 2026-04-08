<?php
declare(strict_types=1);

require __DIR__ . '/src/bootstrap.php';

use App\Database\Connection;
use App\Repository\ProgramacaoRepository;

$pdo = Connection::get();
$repo = new ProgramacaoRepository();

// Pegar primeira programação
$programacoes = $repo->getAllProgramacoes(1, 0);
if (!empty($programacoes)) {
    $selectedId = (int)$programacoes[0]['prg_id'];
    $schedule = $repo->getProgramacaoSchedule($selectedId);
    
    echo "=== TESTE NOVA ABORDAGEM: QUEBRA NO TEXT ===\n\n";
    
    $tasks = [];
    $counter = 0;
    foreach ($schedule as $row) {
        $start = $row['sch_inicio_producao'];
        $end = $row['sch_fim_producao'];
        
        if ($start && $end && $counter < 5) {
            $isSetup = strtolower(trim($row['sch_tipo'] ?? '')) === 'setup';
            $desc = trim($row['sch_descricao'] ?? '-');
            
            if ($isSetup) {
                $text = "⚙️ SETUP";
            } else {
                // Incluir descrição com quebra de linha no text
                $text = "📦 OP TEST\n" . $desc;
            }
            
            $tasks[] = [
                'id' => (int)$row['sch_id'],
                'text' => $text,
                'start_date' => date('d-m-Y H:i', strtotime($start)),
                'end_date' => date('d-m-Y H:i', strtotime($end)),
            ];
            
            $counter++;
        }
    }
    
    echo "Tasks geradas (5 primeiras):\n";
    foreach ($tasks as $i => $task) {
        echo ($i+1) . ".\n";
        echo "  text (raw): " . var_export($task['text'], true) . "\n";
        echo "  text (json): " . json_encode($task['text']) . "\n";
    }
    
    echo "\n✅ JSON Final seria:\n";
    $json = json_encode($tasks);
    echo substr($json, 0, 200) . "...\n";
}
?>
