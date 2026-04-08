<?php
/**
 * Estratégia: Usar dados locais para entender a estrutura
 * O usuário já tem dados no banco local (prg_itens, sch_linhas, etc)
 * Vamos mapear isso para o que a CODI precisa fornecer
 */

// Usar banco local com PDO
$db_host = '127.0.0.1';
$db_user = 'root';
$db_pass = 'k7m2y9u4';
$db_name = 'controlepcp_sandbox';

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("❌ Erro de conexão: " . $e->getMessage());
}

echo "=== ANÁLISE DE DADOS LOCAIS ===\n\n";

// Ver qual OP temos
echo "1️⃣ OPs no banco local (prg_itens):\n\n";

$query = "SELECT 
    prg_itens_op,
    prg_quantidade,
    COUNT(*) as itens
FROM prg_itens
GROUP BY prg_itens_op
ORDER BY prg_itens_op DESC
LIMIT 10";

try {
    $stmt = $pdo->query($query);
    $ops_locais = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($ops_locais as $op) {
        echo "OP: " . $op['prg_itens_op'] . " | Planejado: " . $op['prg_quantidade'] . " | Itens: " . $op['itens'] . "\n";
    }
    
    if (count($ops_locais) > 0) {
        $op_teste = $ops_locais[0]['prg_itens_op'];
        $planejado = $ops_locais[0]['prg_quantidade'];
        
        echo "\n✓ Usando OP teste: $op_teste (Planejado: $planejado)\n";
        echo "\n2️⃣ Dados de execução local (sch_linhas) para OP $op_teste:\n\n";
        
        // Ver schedules desta OP
        $query_sch = "SELECT 
            sch_data,
            SUM(sch_quantidade) as total_dia
        FROM sch_linhas
        WHERE sch_itens_op = ?
        GROUP BY sch_data
        ORDER BY sch_data";
        
        $stmt = $pdo->prepare($query_sch);
        $stmt->execute([$op_teste]);
        $schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $total_schedules = 0;
        foreach ($schedules as $sch) {
            echo "Data: " . $sch['sch_data'] . " | Quantidade: " . $sch['total_dia'] . "\n";
            $total_schedules += floatval($sch['total_dia']);
        }
        
        echo "\n📊 RESUMO:\n";
        echo "Planejado: $planejado\n";
        echo "Realizado (sch_linhas): $total_schedules\n";
        
        if ($planejado > 0) {
            $percentual = ($total_schedules / $planejado) * 100;
            echo "Taxa de execução: " . number_format($percentual, 2) . "%\n";
        }
    }
    
} catch (Exception $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
}

?>
