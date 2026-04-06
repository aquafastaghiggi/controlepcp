<?php
/**
 * debug_listar.php - Debug da ação listar com passos claros
 * URL: http://192.168.8.123:8081/api/debug_listar.php
 */

echo "<h1>Debug: action=listar</h1>";
echo "<pre>";

try {
    echo "1. Incluindo bootstrap...\n";
    require_once __DIR__ . '/../src/bootstrap.php';
    echo "   ✅ Bootstrap carregado\n\n";
    
    echo "2. Obtendo PDO direto com Connection::get()...\n";
    $pdo = \App\Database\Connection::get();
    echo "   ✅ PDO obtido\n\n";
    
    echo "3. Preparando SQL query com JOIN...\n";
    $sql = "
        SELECT 
            p.prg_id,
            p.prg_numero_op,
            p.prg_eficiencia,
            p.prg_status,
            p.prg_base_inicio,
            p.prg_criado_em,
            l.lin_codigo,
            COUNT(s.sch_id) as total_linhas
        FROM prg_programas p
        LEFT JOIN lin_linhas l ON l.lin_id = p.prg_linha_id
        LEFT JOIN sch_linhas s ON s.sch_programa_id = p.prg_id
        WHERE s.sch_id IS NOT NULL
        GROUP BY p.prg_id, p.prg_numero_op, p.prg_eficiencia, p.prg_status, p.prg_base_inicio, p.prg_criado_em, l.lin_codigo
        ORDER BY p.prg_criado_em DESC, p.prg_id DESC
        LIMIT 50
    ";
    echo "   ✅ SQL preparado\n\n";
    
    echo "4. Preparando statement...\n";
    $stmt = $pdo->prepare($sql);
    echo "   ✅ Statement preparado\n\n";
    
    echo "5. Executando query...\n";
    $stmt->execute();
    echo "   ✅ Query executada\n\n";
    
    echo "6. Fetchando resultados...\n";
    $programacoes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "   ✅ Resultados obtidos\n";
    echo "   Total de registros: " . count($programacoes) . "\n\n";
    
    if (count($programacoes) > 0) {
        echo "7. Primeiro registro:\n";
        echo json_encode($programacoes[0], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
    } else {
        echo "7. Nenhum registro encontrado (pode ser que não haja dados)\n\n";
    }
    
    echo "✅ SUCESSO! Query funcionou corretamente.\n";
    
} catch (Exception $e) {
    echo "\n\n❌ ERRO:\n";
    echo "Mensagem: " . $e->getMessage() . "\n";
    echo "Arquivo: " . $e->getFile() . "\n";
    echo "Linha: " . $e->getLine() . "\n";
    echo "Trace:\n" . $e->getTraceAsString() . "\n";
}

echo "</pre>";
?>
