<?php
header('Content-Type: text/plain; charset=utf-8');

try {
    $pdo = new PDO(
        'mysql:host=127.0.0.1;port=3306;dbname=controlepcp_sandbox',
        'root',
        '',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    $sql = "SELECT DISTINCT 
              DATE(sch_data_inicio) as data_op,
              sch_sequencia,
              sch_data_inicio,
              sch_hora_inicio,
              sch_fim_producao
            FROM schedules 
            WHERE DATE(sch_data_inicio) >= '2026-03-27'
            AND DATE(sch_data_inicio) <= '2026-04-08'
            ORDER BY DATE(sch_data_inicio), sch_hora_inicio";
    
    $stmt = $pdo->query($sql);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "OPERAÇÕES ENCONTRADAS:\n";
    echo str_repeat("=", 80) . "\n";
    foreach ($rows as $row) {
        printf("%-15s | %s %s -> %s\n", 
            $row['sch_sequencia'],
            $row['sch_data_inicio'],
            $row['sch_hora_inicio'],
            $row['sch_fim_producao']
        );
    }
    
    echo "\n" . str_repeat("=", 80) . "\n";
    echo "DIAS ÚNICOS COM OPERAÇÕES:\n";
    
    $uniqueDates = array_unique(array_map(function($r) { 
        return $r['data_op']; 
    }, $rows), SORT_STRING);
    
    sort($uniqueDates);
    echo implode(", ", $uniqueDates);
    
} catch (Exception $e) {
    echo "ERRO: " . $e->getMessage() . "\n";
    echo get_class($e) . "\n";
}
?>
