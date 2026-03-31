<?php
header('Content-Type: application/json; charset=utf-8');

try {
    $pdo = new PDO(
        "mysql:host=localhost;port=3306;dbname=controlepcp_sandbox;charset=utf8mb4",
        'root',
        'k7m2y9u4',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    // Puxar calendários da linha 2
    $sql = "SELECT 
              cal_id,
              cal_linha_id,
              cal_nome
            FROM cal_calendarios
            WHERE cal_linha_id = 2";
    
    $result = $pdo->query($sql);
    $calendar = $result->fetch(PDO::FETCH_ASSOC);
    
    $data = [
        'calendar' => $calendar,
        'intervals' => [],
        'dias_uteis' => [],
        'feriados' => [],
    ];
    
    if ($calendar) {
        // Puxar intervalos do calendário
        $sql = "SELECT 
                  cal_id,
                  cal_inicio,
                  cal_fim
                FROM cal_intervalos
                WHERE cal_calendario_id = ?";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$calendar['cal_id']]);
        $data['intervals'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Puxar dias úteis associados aos intervalos
        $sql = "SELECT 
                  diu_intervalo_id,
                  diu_dia_peq
                FROM cal_dias_uteis
                WHERE diu_intervalo_id IN (
                  SELECT cal_id FROM cal_intervalos 
                  WHERE cal_calendario_id = ?
                )
                ORDER BY diu_intervalo_id, diu_dia_peq";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$calendar['cal_id']]);
        $data['dias_uteis'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Puxar feriados
    $sql = "SELECT 
              cal_data,
              cal_nome
            FROM cal_feriados
            WHERE cal_data >= '2026-03-27'
            AND cal_data <= '2026-04-10'
            ORDER BY cal_data";
    
    $result = $pdo->query($sql);
    $data['feriados'] = $result->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
