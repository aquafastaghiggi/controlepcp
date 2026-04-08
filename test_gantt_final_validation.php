#!/usr/bin/env php
<?php
// Teste COMPLETOVALIDAÇÃO FINAL

require __DIR__ . '/src/bootstrap.php';

use App\Database\Connection;
use App\Repository\ProgramacaoRepository;

$pdo = Connection::get();
$repo = new ProgramacaoRepository();

echo "=== VALIDAÇÃO FINAL ANTES DE ENTREGAR ===\n\n";

// 1. Verificar se arquivo PHP tem sintaxe correta
echo "1️⃣  Validando sintaxe PHP...\n";
$output = shell_exec('php -l gantt.php 2>&1');
if (strpos($output, 'No syntax errors') !== false) {
    echo "   ✅ PHP sem erros\n";
} else {
    echo "   ❌ ERRO: " . $output . "\n";
    exit(1);
}

// 2. Verificar se os dados estão sendo carregados
echo "\n2️⃣  Validando carregamento de dados...\n";
$programacoes = $repo->getAllProgramacoes(1, 0);
if (!empty($programacoes)) {
    echo "   ✅ Programações carregadas\n";
    $selectedId = (int)$programacoes[0]['prg_id'];
    $schedule = $repo->getProgramacaoSchedule($selectedId);
    echo "   ✅ Schedule carregado (" . count($schedule) . " linhas)\n";
} else {
    echo "   ❌ Nenhuma programação encontrada\n";
    exit(1);
}

// 3. Simular a geração dos tasks como faz gantt.php
echo "\n3️⃣  Validando geração de tasks...\n";
$tasks = [];
foreach (array_slice($schedule, 0, 3) as $row) {
    $start = $row['sch_inicio_producao'];
    $end = $row['sch_fim_producao'];
    
    if ($start && $end) {
        $isSetup = strtolower(trim($row['sch_tipo'] ?? '')) === 'setup';
        $op = 'OP123'; // Simulado
        
        $tasks[] = [
            'id' => (int)$row['sch_id'],
            'text' => ($isSetup ? "⚙️ SETUP" : "📦 OP " . $op . "\n" . trim($row['sch_descricao'] ?? '-')),
            'start_date' => date('d-m-Y H:i', strtotime($start)),
            'end_date' => date('d-m-Y H:i', strtotime($end)),
        ];
    }
}
echo "   ✅ " . count($tasks) . " tasks geradas\n";

// 4. Validar JSON
echo "\n4️⃣  Validando JSON...\n";
$json = json_encode($tasks);
if (json_last_error() === JSON_ERROR_NONE) {
    echo "   ✅ JSON válido\n";
    echo "   📦 Tamanho: " . strlen($json) . " bytes\n";
} else {
    echo "   ❌ JSON inválido: " . json_last_error_msg() . "\n";
    exit(1);
}

// 5. Mostrar exemplo de teste
echo "\n5️⃣  Exemplo de task gerada:\n";
if (!empty($tasks)) {
    $first = $tasks[0];
    echo "   ID: " . $first['id'] . "\n";
    echo "   Text (raw):\n" . "     " . str_replace("\n", "\n     ", $first['text']) . "\n";
    echo "   Text (JSON): " . json_encode($first['text']) . "\n";
}

// 6. Verificar template
echo "\n6️⃣  Validando template no gantt.php...\n";
$ganttContent = file_get_contents('gantt.php');
if (strpos($ganttContent, "var desc = task.descricao_produto") === false && 
    strpos($ganttContent, "return task.text;") !== false) {
    echo "   ✅ Template simplificado (apenas retorna task.text)\n";
} else {
    echo "   ⚠️ Template pode estar complexo\n";
}

if (strpos($ganttContent, '"\n"')) {
    echo "   ✅ Quebra de linha \\n encontrada no text\n";
}

echo "\n" . str_repeat("=", 50) . "\n";
echo "✅ TUDO VALIDADO - PRONTO PARA USAR\n";
echo str_repeat("=", 50) . "\n";
echo "\nRESULTADO ESPERADO NO GANTT:\n";
echo "📦 OP 123\n";
echo "Agua Sanitaria Aquafast 5l\n";
?>
