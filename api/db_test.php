<?php
header('Content-Type: text/html; charset=utf-8');
echo "<pre>";

// Tentar com diferentes configurações
$configs = [
    ['host' => 'localhost', 'user' => 'root', 'pass' => ''],
    ['host' => '127.0.0.1', 'user' => 'root', 'pass' => ''],
    ['host' => 'localhost', 'user' => '', 'pass' => ''],
];

$connected = false;
foreach ($configs as $cfg) {
    try {
        echo "Tentando: {$cfg['user']}@{$cfg['host']}\n";
        $pdo = @new PDO(
            "mysql:host={$cfg['host']};port=3306",
            $cfg['user'],
            $cfg['pass'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_SILENT]
        );
        
        if ($pdo) {
            echo "✓ CONECTADO!\n\n";
            
            // Tentar query simples
            $result = $pdo->query("SHOW DATABASES LIKE 'controlepcp%'");
            if ($result) {
                $dbs = $result->fetchAll(PDO::FETCH_ASSOC);
                echo "Bancos encontrados:\n";
                print_r($dbs);
            }
            
            // Conectar ao banco controlep_sandbox
            $pdo2 = new PDO(
                "mysql:host={$cfg['host']};port=3306;dbname=controlepcp_sandbox;charset=utf8mb4",
                $cfg['user'],
                $cfg['pass'],
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
            
            $sql = "SELECT COUNT(*) as total FROM schedules";
            $result = $pdo2->query($sql);
            $row = $result->fetch(PDO::FETCH_ASSOC);
            echo "\nTotal de registros em schedules: " . $row['total'] . "\n\n";
            
            // Puxar dados relevantes
            $sql = "SELECT 
                      sch_data_inicio, 
                      sch_hora_inicio, 
                      sch_fim_producao,
                      sch_sequencia,
                      sch_tipo
                    FROM schedules 
                    WHERE DATE(sch_data_inicio) >= '2026-03-27'
                    AND DATE(sch_data_inicio) <= '2026-04-08'
                    ORDER BY sch_data_inicio, sch_hora_inicio
                    LIMIT 100";
            
            $result = $pdo2->query($sql);
            $rows = $result->fetchAll(PDO::FETCH_ASSOC);
            
            echo "OPERAÇÕES ENCONTRADAS:\n";
            echo str_repeat("=", 100) . "\n";
            printf("%-20s | %-10s | %-20s | %-20s | %s\n", 
                "Sequência", "Tipo", "Início", "Fim", "Dias");
            echo str_repeat("=", 100) . "\n";
            
            foreach ($rows as $r) {
                $inicio = new DateTime($r['sch_data_inicio'] . ' ' . $r['sch_hora_inicio']);
                $fim = new DateTime($r['sch_fim_producao']);
                
                // Calcular dias envolvidos
                $dias = [];
                $date = clone $inicio;
                while ($date->format('Y-m-d') <= $fim->format('Y-m-d')) {
                    $dias[] = $date->format('d/m');
                    $date->modify('+1 day');
                }
                
                printf("%-20s | %-10s | %s | %s | %s\n",
                    $r['sch_sequencia'],
                    $r['sch_tipo'],
                    $r['sch_data_inicio'] . ' ' . $r['sch_hora_inicio'],
                    $r['sch_fim_producao'],
                    implode(", ", $dias)
                );
            }
            
            $connected = true;
            break;
        }
    } catch (Exception $e) {
        echo "✗ Erro: " . $e->getMessage() . "\n";
    }
}

if (!$connected) {
    echo "\nNão conseguiu conectar com nenhuma configuração.\n";
}

echo "</pre>";
?>
