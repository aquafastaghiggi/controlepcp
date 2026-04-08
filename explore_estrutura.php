<?php
$pdo = new PDO(
    'mysql:host=127.0.0.1:3306;dbname=controlepcp_sandbox;charset=utf8mb4',
    'root',
    'k7m2y9u4'
);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$result = $db->query('SELECT * FROM sch_linhas LIMIT 1');
if ($result) {
    $row = $result->fetch_assoc();
    echo "Campos:\n";
    foreach ($row as $k => $v) {
        echo "  [$k] => " . (is_null($v) ? "NULL" : $v) . "\n";
    }
}

echo "\n\n=== CODI_CALENDARIO (REALIZADO) ===\n";
$result = $pdo->query('SELECT * FROM codi_calendario LIMIT 1');
if ($result) {
    $row = $result->fetch_assoc();
    echo "Campos:\n";
    foreach ($row as $k => $v) {
        echo "  [$k] => " . (is_null($v) ? "NULL" : $v) . "\n";
    }
}

echo "\n\n=== AMOSTRAS DE DADOS ===\n";

// Amostra de sch_linhas
echo "\nPlanejado (sch_linhas):\n";
$result = $pdo->query('SELECT sch_id, sch_data_inicio, sch_quantidade, sch_duracao_minutos, sch_descricao FROM sch_linhas LIMIT 3');
while ($row = $result->fetch_assoc()) {
    echo "  ID: {$row['sch_id']} | Data: {$row['sch_data_inicio']} | Qtd: {$row['sch_quantidade']} | Desc: {$row['sch_descricao']}\n";
}

// Amostra de codi_calendario
echo "\nRealizado (codi_calendario):\n";
$result = $pdo->query('SELECT cal_id, cal_data, cal_hora_inicio, cal_recurso_codi_id FROM codi_calendario LIMIT 3');
while ($row = $result->fetch_assoc()) {
    echo "  ID: {$row['cal_id']} | Data: {$row['cal_data']} | Hora: {$row['cal_hora_inicio']} | Recurso: {$row['cal_recurso_codi_id']}\n";
}
