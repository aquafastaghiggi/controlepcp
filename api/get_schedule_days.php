<?php
header('Content-Type: text/html; charset=utf-8');
echo "<pre>";

try {
    // Conectar com a senha fornecida
    $pdo = new PDO(
        "mysql:host=localhost;port=3306;dbname=controlepcp_sandbox;charset=utf8mb4",
        'root',
        'k7m2y9u4',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    echo "✓ CONECTADO AO BANCO!\n\n";
    
    // Puxar operações (conforme anexo do PDF)
    $sql = "SELECT 
              sch_data_inicio, 
              sch_hora_inicio, 
              sch_fim_producao,
              sch_sequencia,
              sch_tipo
            FROM sch_linhas 
            WHERE DATE(sch_data_inicio) >= '2026-03-27'
            AND DATE(sch_data_inicio) <= '2026-04-08'
            ORDER BY sch_data_inicio, sch_hora_inicio
            LIMIT 200";
    
    $result = $pdo->query($sql);
    $rows = $result->fetchAll(PDO::FETCH_ASSOC);
    
    echo "OPERAÇÕES ENCONTRADAS (conforme lógica do PDF):\n";
    echo str_repeat("=", 130) . "\n";
    printf("%-15s | %-10s | %-30s | %-30s | %s\n", 
        "Sequência", "Tipo", "Início", "Fim", "Dias Envolvidos");
    echo str_repeat("=", 130) . "\n";
    
    $allDays = [];
    foreach ($rows as $r) {
        if (!$r['sch_data_inicio'] || !$r['sch_fim_producao']) continue;
        
        $inicio = new DateTime($r['sch_data_inicio'] . ' ' . ($r['sch_hora_inicio'] ?? '00:00'));
        $fim = new DateTime($r['sch_fim_producao']);
        
        // Calcular dias envolvidos (mesmo que parcial)
        $dias = [];
        $date = clone $inicio;
        while ($date->format('Y-m-d') <= $fim->format('Y-m-d')) {
            $dateStr = $date->format('d/m');
            $dias[] = $dateStr;
            $allDays[$date->format('Y-m-d')] = true;
            $date->modify('+1 day');
        }
        
        printf("%-15s | %-10s | %s | %s | %s\n",
            $r['sch_sequencia'] ?? 'N/A',
            $r['sch_tipo'],
            $r['sch_data_inicio'] . ' ' . ($r['sch_hora_inicio'] ?? ''),
            $r['sch_fim_producao'],
            implode(", ", $dias)
        );
    }
    
    echo "\n" . str_repeat("=", 130) . "\n";
    echo "✓ RESULTADO: DIAS ÚNICOS QUE DEVEM APARECER NO GANTT\n";
    echo str_repeat("=", 130) . "\n\n";
    
    $daysFormatted = [];
    foreach (array_keys($allDays) as $day) {
        $dateObj = new DateTime($day);
        $daysFormatted[] = $dateObj->format('d/m');
    }
    sort($daysFormatted);
    
    echo implode(", ", $daysFormatted) . "\n\n";
    
    echo "📊 RESUMO:\n";
    echo "  • Dias com operações: " . count($allDays) . "\n";
    echo "  • Total de registros: " . count($rows) . "\n";
    echo "\n✅ Dados prontos para implementação no Gantt!\n";
    
} catch (Exception $e) {
    echo "✗ ERRO: " . $e->getMessage() . "\n";
    echo "Classe: " . get_class($e) . "\n";
}

echo "</pre>";
?>
