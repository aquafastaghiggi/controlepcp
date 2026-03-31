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
    
    echo "=== ANALISANDO CALENDÁRIO E EXPEDIENTE DA LINHA 2 ===\n\n";
    
    // 1. Ver intervalos (turnos) configurados
    echo "1️⃣  INTERVALOS (TURNOS) CADASTRADOS:\n";
    echo str_repeat("=", 100) . "\n";
    
    $sql = "SELECT 
              cal_id,
              cal_intervalo_inicio as 'Início',
              cal_intervalo_fim as 'Fim',
              cal_dias as 'Dias da Semana'
            FROM cal_intervalos
            ORDER BY cal_intervalo_inicio";
    
    $result = $pdo->query($sql);
    $intervals = $result->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($intervals as $iv) {
        echo sprintf("Turno: %s até %s (dias semana: %s)\n", 
            $iv['Início'], 
            $iv['Fim'], 
            $iv['Dias da Semana']
        );
    }
    
    // 2. Ver feriados
    echo "\n2️⃣  FERIADOS CONFIG DO CALENDÁRIO:\n";
    echo str_repeat("=", 100) . "\n";
    
    $sql = "SELECT 
              cal_feriado_data as 'Data',
              cal_feriado_descricao as 'Descrição'
            FROM cal_feriados
            WHERE cal_feriado_data >= '2026-03-27'
            AND cal_feriado_data <= '2026-04-10'
            ORDER BY cal_feriado_data";
    
    $result = $pdo->query($sql);
    $holidays = $result->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($holidays)) {
        echo "Nenhum feriado configurado nesse período.\n";
    } else {
        foreach ($holidays as $h) {
            echo sprintf("%s - %s\n", $h['Data'], $h['Descrição']);
        }
    }
    
    // 3. Ver dias úteis
    echo "\n3️⃣  DIAS ÚTEIS CONFIGURADOS:\n";
    echo str_repeat("=", 100) . "\n";
    
    $sql = "SELECT 
              cal_dia_util_dia_semana as 'Dia da Semana',
              cal_dia_util_eh_util as 'É Útil?'
            FROM cal_dias_uteis
            ORDER BY cal_dia_util_dia_semana";
    
    $result = $pdo->query($sql);
    $workdays = $result->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($workdays as $wd) {
        $dayNames = ['Domingo', 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado'];
        $dayName = $dayNames[$wd['Dia da Semana']] ?? 'N/A';
        $isWorking = $wd['É Útil?'] ? 'SIM ✓' : 'NÃO ✗';
        echo sprintf("%s (%d): %s\n", $dayName, $wd['Dia da Semana'], $isWorking);
    }
    
    // 4. Calcular quais dias 27/03 a 10/04 têm expediente
    echo "\n4️⃣  VALIDAÇÃO: QUAIS DIAS TÊM EXPEDIENTE?\n";
    echo str_repeat("=", 100) . "\n";
    
    $startDate = new DateTime('2026-03-27');
    $endDate = new DateTime('2026-04-10');
    $daysWithShifts = [];
    
    // Mapa de dias com turnos
    foreach ($intervals as $iv) {
        $daysArray = json_decode($iv['Dias da Semana'], true) ?? [];
        foreach ($daysArray as $dayOfWeek) {
            if (!isset($daysWithShifts['weekdays'])) {
                $daysWithShifts['weekdays'] = [];
            }
            $daysWithShifts['weekdays'][$dayOfWeek] = true;
        }
    }
    
    // Verificar cada dia
    $expedientDays = [];
    $currentDate = clone $startDate;
    
    while ($currentDate <= $endDate) {
        $dateStr = $currentDate->format('Y-m-d');
        $dayOfWeek = $currentDate->format('w'); // 0=Sun, 1=Mon, etc
        $displayDate = $currentDate->format('d/m');
        $dayName = $currentDate->format('D');
        
        // Verificar se é feriado
        $isFeriado = false;
        foreach ($holidays as $h) {
            if ($h['Data'] === $dateStr) {
                $isFeriado = true;
                break;
            }
        }
        
        // Verificar se o dia da semana tem turno
        $hasShift = isset($daysWithShifts['weekdays'][$dayOfWeek]);
        
        // Verificar se é dia útil
        $isDiaUtil = false;
        foreach ($workdays as $wd) {
            if ($wd['Dia da Semana'] == $dayOfWeek) {
                $isDiaUtil = $wd['É Útil?'] ? true : false;
                break;
            }
        }
        
        // Determinar se tem expediente
        $temExpediente = $hasShift && !$isFeriado && $isDiaUtil;
        
        if ($temExpediente) {
            $expedientDays[] = $dateStr;
            echo sprintf("✓ %s (%s) - TEM EXPEDIENTE (turno: %s, útil: %s, feriado: %s)\n",
                $displayDate,
                $dayName,
                $hasShift ? 'SIM' : 'NÃO',
                $isDiaUtil ? 'SIM' : 'NÃO',
                $isFeriado ? 'SIM' : 'NÃO'
            );
        } else {
            $reasons = [];
            if (!$hasShift) $reasons[] = "sem turno";
            if (!$isDiaUtil) $reasons[] = "não útil";
            if ($isFeriado) $reasons[] = "feriado";
            
            echo sprintf("✗ %s (%s) - SEM EXPEDIENTE (%s)\n",
                $displayDate,
                $dayName,
                implode(", ", $reasons)
            );
        }
        
        $currentDate->modify('+1 day');
    }
    
    echo "\n" . str_repeat("=", 100) . "\n";
    echo "📅 DIAS COM EXPEDIENTE NESSE PERÍODO:\n";
    $formattedDays = [];
    foreach ($expedientDays as $d) {
        $dateObj = new DateTime($d);
        $formattedDays[] = $dateObj->format('d/m');
    }
    echo implode(", ", $formattedDays) . "\n\n";
    
    // 5. Agora cruzar com operações
    echo "5️⃣  CRUZANDO COM OPERAÇÕES:\n";
    echo str_repeat("=", 100) . "\n";
    
    $sql = "SELECT DISTINCT
              DATE(sch_data_inicio) as data_op
            FROM sch_linhas 
            WHERE DATE(sch_data_inicio) >= '2026-03-27'
            AND DATE(sch_data_inicio) <= '2026-04-10'
            ORDER BY DATE(sch_data_inicio)";
    
    $result = $pdo->query($sql);
    $operationDates = $result->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Datas com OPERAÇÕES no banco:\n";
    foreach ($operationDates as $op) {
        $dateObj = new DateTime($op['data_op']);
        $dateStr = $op['data_op'];
        $hasExpedient = in_array($dateStr, $expedientDays);
        $status = $hasExpedient ? '✓ TEM expediente' : '✗ SEM expediente';
        echo sprintf("  %s: %s\n", $dateObj->format('d/m'), $status);
    }
    
    echo "\n" . str_repeat("=", 100) . "\n";
    echo "✅ RESULTADO FINAL: DIAS QUE DEVEM APARECER NO GANTT LINHA 2\n";
    echo str_repeat("=", 100) . "\n\n";
    
    // Intersecção: dias com operações AND dias com expediente
    $finalDays = [];
    foreach ($expedientDays as $expDay) {
        foreach ($operationDates as $opDay) {
            if ($opDay['data_op'] === $expDay) {
                $finalDays[] = $expDay;
                break;
            }
        }
    }
    
    if (empty($finalDays)) {
        echo "Nenhum dia com expediente E operações.\n";
    } else {
        $formattedFinal = [];
        foreach ($finalDays as $d) {
            $dateObj = new DateTime($d);
            $formattedFinal[] = $dateObj->format('d/m');
        }
        echo implode(", ", $formattedFinal) . "\n\n";
        echo "Total: " . count($finalDays) . " dias\n";
    }
    
} catch (Exception $e) {
    echo "❌ ERRO: " . $e->getMessage() . "\n";
}

echo "</pre>";
?>
