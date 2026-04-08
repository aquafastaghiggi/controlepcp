<?php
// Testar a API localmente
require 'src/Database/Connection.php';
use App\Database\Connection;

$pdo = Connection::get();

echo "=== TESTANDO QUERY DE CALENDÁRIO COM OP ===\n\n";

// Simular a query que o endpoint faz
$sql = "
    SELECT 
        c.cal_codigo_codi,
        c.cal_data,
        c.cal_hora_inicio,
        c.cal_hora_fim,
        c.cal_recurso_codi_id,
        c.cal_turno_codi,
        c.cal_id,
        r.cod_nome_recurso as recurso_nome
    FROM codi_calendario c
    LEFT JOIN codi_recursos r ON c.cal_recurso_codi_id = r.cod_codigo_codi
    ORDER BY c.cal_data DESC, c.cal_hora_inicio
    LIMIT 5
";

$stmt = $pdo->prepare($sql);
$stmt->execute([]);

$periodos = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Total de períodos: " . count($periodos) . "\n\n";

foreach ($periodos as $row) {
    echo "--- Período: " . $row['cal_data'] . " " . $row['cal_hora_inicio'] . " (Recurso ID: " . $row['cal_recurso_codi_id'] . ") ---\n";
    
    // Para cada período, buscar os items executados no mesmo recurso
    $subQuery = "
        SELECT perf_dados_json, perf_item_codi
        FROM codi_performance
        WHERE perf_recurso_codi_id = ?
        LIMIT 5
    ";
    
    $subStmt = $pdo->prepare($subQuery);
    $subStmt->execute([$row['cal_recurso_codi_id']]);
    
    $ops = [];
    $items = [];
    
    foreach ($subStmt->fetchAll(PDO::FETCH_ASSOC) as $perf) {
        if ($perf['perf_dados_json']) {
            $json = json_decode($perf['perf_dados_json'], true);
            if (isset($json['ordemProducao']) && $json['ordemProducao']) {
                $ops[] = $json['ordemProducao'];
            }
            if ($perf['perf_item_codi']) {
                $items[] = $perf['perf_item_codi'];
            }
        }
    }
    
    $op_principal = !empty($ops) ? reset($ops) : null;
    $item_principal = !empty($items) ? reset($items) : 0;
    
    echo "  Recurso: " . ($row['recurso_nome'] ?? 'N/A') . "\n";
    echo "  OP Principal: " . ($op_principal ?: 'N/A') . "\n";
    echo "  Item: " . $item_principal . "\n";
    echo "  Total OPs encontradas: " . count($ops) . "\n";
    echo "  Total Items encontrados: " . count($items) . "\n\n";
}
?>

