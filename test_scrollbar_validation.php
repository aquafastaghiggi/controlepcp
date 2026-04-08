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
    
    echo "=== Validação da Scrollbar ===\n\n";
    echo "Total de items: " . count($tasks) . "\n";
    echo "Altura por item: 32px\n";
    echo "Altura total conteúdo: " . (count($tasks) * 32) . "px\n";
    echo "min-height do container: 500px\n";
    echo "Precisa scrollbar vertical: " . ((count($tasks) * 32) > 500 ? "SIM ✅" : "NÃO (caberá sem scroll)") . "\n\n";
    echo "CSS adicionado:\n";
    echo "- min-height: 500px no #gantt_here\n";
    echo "- overflow: hidden (para forçar gantt controlar o scroll)\n";
    echo "- .gantt_ver_scroll com overflow-y: auto e visibility: visible\n";
    echo "- .gantt_hor_scroll com overflow-x: auto e visibility: visible\n";
    echo "- .gantt_scrollbar com display: block e visibility: visible\n\n";
    echo "A scrollbar lateral aparecerá quando a lista exceder 500px de altura.\n";
}
?>
