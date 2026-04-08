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
    
    echo "=== Teste de Dados para Gantt ===\n\n";
    echo "Programação ID: $selectedId\n";
    echo "Total de linhas: " . count($schedule) . "\n\n";
    
    foreach ($schedule as $i => $row) {
        if ($i > 3) break; // Mostrar apenas as 3 primeiras
        
        echo "Linha " . ($i+1) . ":\n";
        echo "  sch_descricao: " . trim($row['sch_descricao'] ?? '-') . "\n";
        echo "  sch_tipo: " . trim($row['sch_tipo'] ?? '-') . "\n";
        echo "  sch_sku: " . trim($row['sch_sku'] ?? '-') . "\n";
        echo "  sch_quantidade: " . ($row['sch_quantidade'] ?? '-') . "\n";
        echo "\n";
    }
    
    // Agora testar a geração do JSON que vai para o Gantt
    echo "=== Testando Geração JSON para Gantt ===\n\n";
    
    $tasks = [];
    foreach ($schedule as $row) {
        $start = $row['sch_inicio_producao'];
        $end = $row['sch_fim_producao'];
        
        if ($start && $end) {
            $isSetup = strtolower(trim($row['sch_tipo'] ?? '')) === 'setup';
            
            $tasks[] = [
                'id' => (int)$row['sch_id'],
                'text' => ($isSetup ? "⚙️ SETUP" : "📦 OP TEST"),
                'descricao_produto' => trim($row['sch_descricao'] ?? '-'),
                'start_date' => date('d-m-Y H:i', strtotime($start)),
                'end_date' => date('d-m-Y H:i', strtotime($end)),
            ];
        }
    }
    
    echo "Primeiros 3 tasks gerados:\n";
    foreach (array_slice($tasks, 0, 3) as $i => $task) {
        echo "Task " . ($i+1) . ":\n";
        echo "  text: " . $task['text'] . "\n";
        echo "  descricao_produto: " . $task['descricao_produto'] . "\n";
        echo "\n";
    }
    
    echo "\n✅ JSON válido? " . (json_encode($tasks) ? "SIM" : "NÃO") . "\n";
}
?>
