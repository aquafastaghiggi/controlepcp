<?php

declare(strict_types=1);

require __DIR__ . '/src/bootstrap.php';

use App\Auth\Auth;
use App\Database\Connection;

Auth::startSession();
Auth::requireLogin();

header('Content-Type: text/plain; charset=utf-8');

$pdo = Connection::get();

// Criar dados de teste baseado na tabela que você mostrou
$testData = [
    [
        'prg_numero_op' => '201055',
        'sch_sequencia' => '24',
        'sch_tipo' => 'produção',
        'sch_sku' => '20010003',
        'sch_descricao' => 'Água Sanitária Aquafast 5l',
        'sch_quantidade' => 5.0,
        'sch_duracao_minutos' => 45,
        'sch_data_inicio' => '2026-03-27',
        'sch_hora_inicio' => '17:45',
        'sch_hora_fim' => '18:30',
    ],
    [
        'prg_numero_op' => '201055',
        'sch_sequencia' => null,
        'sch_tipo' => 'setup',
        'sch_sku' => '20010003',
        'sch_descricao' => 'Setup',
        'sch_quantidade' => null,
        'sch_duracao_minutos' => 30,
        'sch_data_inicio' => '2026-03-27',
        'sch_hora_inicio' => '18:30',
        'sch_hora_fim' => '19:00',
    ],
    [
        'prg_numero_op' => '201613',
        'sch_sequencia' => '25',
        'sch_tipo' => 'produção',
        'sch_sku' => '20160025',
        'sch_descricao' => 'Avelante 5l Croo Aquafast 3l',
        'sch_quantidade' => 2.5,
        'sch_duracao_minutos' => 130,
        'sch_data_inicio' => '2026-03-28',
        'sch_hora_inicio' => '07:09',
        'sch_hora_fim' => '09:19',
    ],
];

try {
    // Limpar dados antigos de teste (opcionalmente)
    // $pdo->exec("DELETE FROM sch_linhas WHERE sch_descricao = 'Setup' OR sch_descricao LIKE '%Aqua%'");

    // Inserir programa de teste se não existir
    $stmt = $pdo->prepare("
        INSERT IGNORE INTO prg_programas 
        (prg_numero_op, prg_linha_id, prg_base_inicio, prg_eficiencia, prg_status, prg_criado_em)
        VALUES (?, (SELECT lin_id FROM lin_linhas LIMIT 1), NOW(), 70.0, 'calculado', NOW())
    ");

    $prgIds = [];
    foreach ($testData as $data) {
        $stmt->execute([$data['prg_numero_op']]);
        $prgId = $pdo->lastInsertId() ?: 1; // Se já existe, usa ID 1
        $prgIds[$data['prg_numero_op']] = $prgId;
    }

    // Inserir linhas de schedule
    $insertStmt = $pdo->prepare("
        INSERT INTO sch_linhas
        (sch_programa_id, sch_sequencia, sch_tipo, sch_sku, sch_descricao, sch_quantidade, 
         sch_duracao_minutos, sch_data_inicio, sch_hora_inicio, sch_hora_fim, sch_criado_em)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");

    $count = 0;
    foreach ($testData as $data) {
        $prgId = $prgIds[$data['prg_numero_op']] ?? 1;
        $insertStmt->execute([
            $prgId,
            $data['sch_sequencia'],
            $data['sch_tipo'],
            $data['sch_sku'],
            $data['sch_descricao'],
            $data['sch_quantidade'],
            $data['sch_duracao_minutos'],
            $data['sch_data_inicio'],
            $data['sch_hora_inicio'],
            $data['sch_hora_fim'],
        ]);
        $count++;
    }

    echo "✓ Criados {$count} registros de teste em sch_linhas\n";
    echo "✓ Programação: 201055, 201613, etc\n";
    echo "Acesse: http://localhost/controlepcp_sandbox/sequenciamento.php\n";

} catch (Exception $e) {
    echo "✗ Erro ao criar dados: " . $e->getMessage() . "\n";
}
