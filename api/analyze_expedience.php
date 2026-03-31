<?php
header('Content-Type: text/html; charset=utf-8');
echo "<pre>";

try {
    $pdo = new PDO(
        "mysql:host=localhost;port=3306;dbname=controlepcp_sandbox;charset=utf8mb4",
        'root',
        'k7m2y9u4',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    echo "=== ANÁLISE DE DIAS COM EXPEDIENTE NA LINHA 2 ===\n\n";
    
    // 1. Puxar calendário
    $sql = "SELECT cal_id FROM cal_calendarios WHERE cal_linha_id = 2";
    $result = $pdo->query($sql);
    $calRow = $result->fetch(PDO::FETCH_ASSOC);
    $calendarId = $calRow['cal_id'];
    
    // 2. Puxar intervalos com seus dias
    $sql = "SELECT 
              cal_id,
              cal_inicio,
              cal_fim
            FROM cal_intervalos
            WHERE cal_calendario_id = ?";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$calendarId]);
    $intervals = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Montar mapa de intervalos com seus dias úteis
    $intervalDays = [];
    foreach ($intervals as $iv) {
        $sql = "SELECT diu_dia_peq FROM cal_dias_uteis WHERE diu_intervalo_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$iv['cal_id']]);
        $days = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $intervalDays[$iv['cal_id']] = [
            'inicio' => $iv['cal_inicio'],
            'fim' => $iv['cal_fim'],
            'dias' => $days,
        ];
    }
    
    // 3. Puxar feriados
    $sql = "SELECT cal_data FROM cal_feriados WHERE cal_calendario_id = ? AND cal_data >= '2026-03-27' AND cal_data <= '2026-04-10'";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$calendarId]);
    $feriadosArray = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $feriados = array_flip($feriadosArray);
    
    echo "📅 TURNOS CONFIGURADOS:\n";
    echo str_repeat("=", 100) . "\n";
    foreach ($intervalDays as $id => $iv) {
        $dayNames = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sab'];
        $daysStr = implode(', ', array_map(fn($d) => $dayNames[$d], $iv['dias']));
        printf("  %s - %s (dias: %s)\n", $iv['inicio'], $iv['fim'], $daysStr);
    }
    
    echo "\n📍 FERIADOS:\n";
    echo str_repeat("=", 100) . "\n";
    if (empty($feriados)) {
        echo "  (nenhum configurado)\n";
    } else {
        foreach (array_keys($feriados) as $f) {
            echo "  $f\n";
        }
    }
    
    // 4. Analisar cada dia
    echo "\n📊 DIAS DO PERÍODO (27/03 - 10/04):\n";
    echo str_repeat("=", 100) . "\n";
    
    $startDate = new DateTime('2026-03-27');
    $endDate = new DateTime('2026-04-10');
    $daysWithShifts = [];
    
    $currentDate = clone $startDate;
    while ($currentDate <= $endDate) {
        $dateStr = $currentDate->format('Y-m-d');
        $dayOfWeek = intval($currentDate->format('w'));
        if ($dayOfWeek === 0) $dayOfWeek = 7; // Converter domingo de 0 para 7
        $displayDate = $currentDate->format('d/m');
        $dayNames = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sab'];
        $dayName = $dayNames[intval($currentDate->format('w'))];
        
        // Verificar se é feriado
        $isFeriado = isset($feriados[$dateStr]);
        
        // Verificar se há turno nesse dia
        $hasTurno = false;
        $turnosNoDia = [];
        foreach ($intervalDays as $id => $iv) {
            if (in_array($dayOfWeek, $iv['dias'])) {
                $hasTurno = true;
                $turnosNoDia[] = $iv['inicio'] . '-' . $iv['fim'];
            }
        }
        
        // Determinar if tem expediente
        $temExpediente = $hasTurno && !$isFeriado;
        
        if ($temExpediente) {
            $daysWithShifts[] = $dateStr;
            echo sprintf("✓ %s (%s) - TEM EXPEDIENTE\n", $displayDate, $dayName);
        } else {
            $reasons = [];
            if (!$hasTurno) $reasons[] = "sem turno";
            if ($isFeriado) $reasons[] = "feriado";
            echo sprintf("✗ %s (%s) - SEM EXPEDIENTE (%s)\n", $displayDate, $dayName, implode(", ", $reasons));
        }
        
        $currentDate->modify('+1 day');
    }
    
    // 5. Cruzar com operações
    echo "\n" . str_repeat("=", 100) . "\n";
    echo "✅ DIAS COM EXPEDIENTE QUE TÊM OPERAÇÕES:\n";
    echo str_repeat("=", 100) . "\n";
    
    $sql = "SELECT DISTINCT DATE(sch_data_inicio) as data_op
            FROM sch_linhas 
            WHERE DATE(sch_data_inicio) >= '2026-03-27'
            AND DATE(sch_data_inicio) <= '2026-04-10'
            ORDER BY DATE(sch_data_inicio)";
    
    $result = $pdo->query($sql);
    $operationDates = $result->fetchAll(PDO::FETCH_ASSOC);
    
    $finalDays = [];
    foreach ($operationDates as $op) {
        $opDate = $op['data_op'];
        if (in_array($opDate, $daysWithShifts)) {
            $dateObj = new DateTime($opDate);
            $formattedDate = $dateObj->format('d/m');
            $finalDays[] = $formattedDate;
        }
    }
    
    if (empty($finalDays)) {
        echo "Nenhum dia.\n";
    } else {
        echo implode(", ", $finalDays) . "\n\n";
        echo sprintf("TOTAL: %d dias\n", count($finalDays));
    }
    
} catch (Exception $e) {
    echo "❌ ERRO: " . $e->getMessage() . "\n";
}

echo "</pre>";
?>
