<?php
require 'src/bootstrap.php';
use App\Database\Connection;

$pdo = Connection::get();

// Verificar se há dados em sch_linhas
echo "=== Dados em sch_linhas ===\n";
$stmt = $pdo->query("SELECT COUNT(*) as total FROM sch_linhas");
$result = $stmt->fetch();
echo "Total de registros: " . $result['total'] . "\n\n";

// Mostrar alguns registros
echo "=== Primeiros 5 registros ===\n";
$stmt = $pdo->query("
    SELECT 
        sch_id,
        sch_programa_id,
        sch_sequencia,
        sch_tipo,
        sch_sku,
        sch_descricao,
        sch_quantidade,
        sch_duracao_minutos,
        sch_data_inicio,
        sch_hora_inicio,
        sch_hora_fim,
        sch_fim_producao
    FROM sch_linhas
    LIMIT 5
");
$dados = $stmt->fetchAll();
foreach ($dados as $row) {
    echo json_encode($row, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
}

// Verificar programações
echo "\n=== Programações com schedule ===\n";
$stmt = $pdo->query("
    SELECT DISTINCT
        p.prg_id,
        p.prg_numero_op,
        COUNT(s.sch_id) as linhas
    FROM prg_programas p
    LEFT JOIN sch_linhas s ON s.sch_programa_id = p.prg_id
    WHERE s.sch_id IS NOT NULL
    GROUP BY p.prg_id
    LIMIT 5
");
$prgs = $stmt->fetchAll();
foreach ($prgs as $prg) {
    echo "OP: {$prg['prg_numero_op']}, Linhas: {$prg['linhas']}\n";
}
