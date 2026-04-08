<?php
// Buscar informações de finalização da OP 201055

try {
    $pdo = new PDO('mysql:host=localhost;dbname=controlepcp_sandbox', 'root', 'k7m2y9u4');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "=== BUSCA DE FINALIZAÇÃO - OP 201055 ===\n\n";
    
    // 1. Dados principais da OP
    echo "1. DADOS PRINCIPAIS DA OP 201055:\n";
    $stmt = $pdo->prepare("SELECT prg_id, prg_numero_op, prg_status, prg_base_inicio, prg_data_consulta, prg_atualizado_em FROM prg_programas WHERE prg_numero_op = '201055'");
    $stmt->execute();
    $prog = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($prog) {
        echo json_encode($prog, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
        $prg_id = $prog['prg_id'];
        
        // 2. Linhas de produção associadas
        echo "2. LINHAS DE PRODUÇÃO (sch_linhas para prg_id=$prg_id):\n";
        $stmt = $pdo->prepare("SELECT 
            sch_id, sch_sequencia, sch_tipo, sch_descricao, sch_quantidade,
            sch_inicio_planejado, sch_inicio_producao, sch_fim_producao, sch_status,
            sch_atualizado_em
        FROM sch_linhas 
        WHERE sch_programa_id = ? 
        ORDER BY sch_sequencia");
        $stmt->execute([$prg_id]);
        $linhas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "Total de linhas: " . count($linhas) . "\n\n";
        
        foreach ($linhas as $i => $linha) {
            echo "  Linha " . ($i+1) . ":\n";
            foreach ($linha as $k => $v) {
                echo "    $k: " . ($v ?? 'NULL') . "\n";
            }
            echo "\n";
        }
        
        // 3. Resumo de finalização
        echo "3. ANÁLISE DE FINALIZAÇÃO:\n";
        echo "  Status geral (prg_status): " . $prog['prg_status'] . "\n";
        echo "  Última atualização: " . $prog['prg_atualizado_em'] . "\n";
        
        // Encontrar última data de fim_producao
        $fim_datas = array_filter(array_map(fn($l) => $l['sch_fim_producao'], $linhas));
        if (count($fim_datas) > 0) {
            $ultima_fim = max($fim_datas);
            echo "  Última data de FIM de produção: " . $ultima_fim . "\n";
        } else {
            echo "  Nenhuma data de fim de produção registrada\n";
        }
        
        // Verificar se todas as linhas têm fim_producao
        $linhas_com_fim = count(array_filter($linhas, fn($l) => !empty($l['sch_fim_producao'])));
        echo "  Linhas com fim de produção: $linhas_com_fim / " . count($linhas) . "\n";
        
    } else {
        echo "OP 201055 NÃO ENCONTRADA na tabela prg_programas!\n";
    }
    
} catch (Exception $e) {
    echo "ERRO: " . $e->getMessage() . "\n";
}
?>
