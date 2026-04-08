<?php
/**
 * Verificar status e histórico de 201055 no banco LOCAL
 */

$db = new PDO("mysql:host=127.0.0.1;dbname=controlepcp_sandbox;charset=utf8mb4", 'root', 'k7m2y9u4');

echo "=== ESTADO ATUAL DA ORDEM 201055 (BANCO LOCAL) ===\n\n";

// Verificar programa
$sql = "SELECT * FROM prg_programas WHERE prg_programa_op = '201055' LIMIT 1";
$prog = $db->query($sql)->fetch(PDO::FETCH_ASSOC);

if ($prog) {
    echo "📌 PROGRAMA:\n";
    echo "ID: " . $prog['prg_id'] . "\n";
    echo "OP: " . $prog['prg_programa_op'] . "\n";
    echo "Data Criação: " . ($prog['prg_data_criacao'] ?? '?') . "\n";
    echo "Data Conclusão: " . ($prog['prg_data_conclusao'] ?? '?') . "\n";
    echo "Status: " . ($prog['prg_status'] ?? '?') . "\n\n";
    
    // Verificar se tem schedules
    $sql2 = "SELECT COUNT(*) as total, 
                    MIN(sch_data_inicio) as data_inicio,
                    MAX(sch_data_inicio) as data_fim
             FROM sch_linhas WHERE sch_programa_id = " . $prog['prg_id'];
    $sched = $db->query($sql2)->fetch(PDO::FETCH_ASSOC);
    
    if ($sched['total'] > 0) {
        echo "📅 SCHEDULES:\n";
        echo "Total: " . $sched['total'] . "\n";
        echo "Primeira Data: " . $sched['data_inicio'] . "\n";
        echo "Última Data: " . $sched['data_fim'] . "\n";
    } else {
        echo "Sem schedules registrados\n";
    }
}

// Também buscar na tabela de sincronização CODI
echo "\n\n=== SINCRONIZAÇÃO CODI ===\n\n";

$sql3 = "SELECT * FROM codi_sincronizacao WHERE codi_mapeamento = '0201055' OR codi_mapeamento = '201055' LIMIT 5";
try {
    $syncs = $db->query($sql3)->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($syncs) > 0) {
        foreach ($syncs as $sync) {
            echo "Sincronização:\n";
            foreach ($sync as $k => $v) {
                echo "  $k: $v\n";
            }
            echo "\n";
        }
    } else {
        echo "Nenhuma sincronização encontrada\n";
    }
} catch (\Exception $e) {
    echo "Erro: " . $e->getMessage() . "\n";
}

?>
