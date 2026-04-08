<?php
/**
 * Trabalha com dados CODI sincronizados
 * Mostra dados estruturados prontos para usar em frontend/reports
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once __DIR__ . '/src/Database/Connection.php';

use App\Database\Connection;

$pdo = Connection::get();

echo "🎯 TRABALHANDO COM DADOS CODI SINCRONIZADOS\n";
echo str_repeat("=", 80) . "\n\n";

try {
    
    // ===== 1. RECUPERAR E ESTRUTURAR DADOS =====
    
    echo "1️⃣ CARREGANDO RECURSOS\n\n";
    
    $stmt = $pdo->query('SELECT cod_id, cod_codigo_codi, cod_nome_recurso FROM codi_recursos ORDER BY cod_nome_recurso');
    $recursos = $stmt->fetchAll(\PDO::FETCH_ASSOC);
    
    echo "Recursos carregados: " . count($recursos) . "\n";
    foreach ($recursos as $r) {
        echo "  - [{$r['cod_id']}] {$r['cod_nome_recurso']}\n";
    }
    
    
    // ===== 2. CALENDÁRIO POR RECURSO =====
    
    echo "\n\n2️⃣ CALENDÁRIO FABRIL POR RECURSO\n";
    echo str_repeat("-", 80) . "\n\n";
    
    // Pegar o primeiro recurso como exemplo
    $recursoSelecionado = $recursos[0];
    $recursoId = $recursoSelecionado['cod_codigo_codi'];  // Usar o código CODI
    
    echo "Recurso selecionado: {$recursoSelecionado['cod_nome_recurso']}\n\n";
    
    $stmt = $pdo->prepare(
        'SELECT cal_codigo_codi, cal_data, cal_hora_inicio, cal_hora_fim, cal_turno_codi 
         FROM codi_calendario 
         WHERE cal_recurso_codi_id = ? 
         ORDER BY cal_data DESC, cal_hora_inicio 
         LIMIT 20'
    );
    $stmt->execute([$recursoId]);
    $calendario = $stmt->fetchAll(\PDO::FETCH_ASSOC);
    
    if (count($calendario) > 0) {
        echo "Períodos de calendario para este recurso:\n";
        foreach ($calendario as $cal) {
            $data = $cal['cal_data'];
            $inicio = $cal['cal_hora_inicio'];
            $fim = $cal['cal_hora_fim'];
            echo sprintf("  📅 %s | %s - %s\n", $data, $inicio, $fim);
        }
    } else {
        echo "Nenhum calendário encontrado para este recurso.\n";
    }
    
    
    // ===== 3. PERFORMANCE POR RECURSO =====
    
    echo "\n\n3️⃣ PERFORMANCE POR RECURSO\n";
    echo str_repeat("-", 80) . "\n\n";
    
    $stmt = $pdo->prepare(
        'SELECT perf_codigo_codi, perf_item_codi, perf_ordem_producao, perf_dados_json 
         FROM codi_performance 
         WHERE perf_recurso_codi_id = ? 
         LIMIT 10'
    );
    $stmt->execute([$recursoId]);
    $performance = $stmt->fetchAll(\PDO::FETCH_ASSOC);
    
    if (count($performance) > 0) {
        echo "Performance data para este recurso:\n";
        foreach ($performance as $perf) {
            $dados = json_decode($perf['perf_dados_json'], true);
            $itemNome = $dados['item']['nomeItem'] ?? 'N/A';
            echo sprintf("  ⚙️  Item: %s\n", $itemNome);
        }
    } else {
        echo "Nenhum performance data encontrado para este recurso.\n";
    }
    
    
    // ===== 4. FUNÇÃO: DADOS EM ARRAY ESTRUTURADO =====
    
    echo "\n\n4️⃣ DADOS EM FORMATO ESTRUTURADO (para usar no frontend)\n";
    echo str_repeat("-", 80) . "\n\n";
    
    $dadosEstruturados = [
        'recursos' => [],
        'calendario' => [],
        'performance' => []
    ];
    
    // Carregar todos os recursos
    $stmt = $pdo->query('SELECT cod_id, cod_codigo_codi, cod_nome_recurso FROM codi_recursos');
    foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $r) {
        $dadosEstruturados['recursos'][] = [
            'id' => $r['cod_id'],
            'codigo_codi' => $r['cod_codigo_codi'],
            'nome' => $r['cod_nome_recurso']
        ];
    }
    
    // Carregar calendário (primeiros 50 para exemplo)
    $stmt = $pdo->query(
        'SELECT cal_codigo_codi, cal_data, cal_hora_inicio, cal_hora_fim, cal_recurso_codi_id, cal_dados_json 
         FROM codi_calendario 
         LIMIT 50'
    );
    foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $cal) {
        $dados = json_decode($cal['cal_dados_json'], true);
        $dadosEstruturados['calendario'][] = [
            'codigo' => $cal['cal_codigo_codi'],
            'data' => $cal['cal_data'],
            'hora_inicio' => $cal['cal_hora_inicio'],
            'hora_fim' => $cal['cal_hora_fim'],
            'recurso_id' => $cal['cal_recurso_codi_id'],
            'turno' => $dados['turno']['nomeTurno'] ?? null,
            'grandeza' => $dados['grandeza']['nomeGrandeza'] ?? null
        ];
    }
    
    // Carregar performance (primeiros 50 para exemplo)
    $stmt = $pdo->query(
        'SELECT perf_codigo_codi, perf_recurso_codi_id, perf_item_codi, perf_ordem_producao, perf_dados_json 
         FROM codi_performance 
         LIMIT 50'
    );
    foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $perf) {
        $dados = json_decode($perf['perf_dados_json'], true);
        $dadosEstruturados['performance'][] = [
            'codigo' => $perf['perf_codigo_codi'],
            'recurso_id' => $perf['perf_recurso_codi_id'],
            'item_codigo' => $dados['item']['codItem'] ?? null,
            'item_nome' => $dados['item']['nomeItem'] ?? null,
            'ordem_producao' => $perf['perf_ordem_producao'],
            'grandeza' => $dados['grandeza']['nomeGrandeza'] ?? null
        ];
    }
    
    // Exibir estatísticas
    echo "Estrutura carregada:\n";
    echo "  • Recursos: " . count($dadosEstruturados['recursos']) . "\n";
    echo "  • Calendário: " . count($dadosEstruturados['calendario']) . "\n";
    echo "  • Performance: " . count($dadosEstruturados['performance']) . "\n";
    
    
    // ===== 5. EXEMPLO: EXPORTAR COMO JSON =====
    
    echo "\n\n5️⃣ EXPORTANDO PARA JSON (para frontend)\n";
    echo str_repeat("-", 80) . "\n\n";
    
    $jsonFile = __DIR__ . '/codi_dados_estruturados.json';
    file_put_contents($jsonFile, json_encode($dadosEstruturados, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    
    echo "Arquivo criado: $jsonFile\n";
    echo "Tamanho: " . round(filesize($jsonFile) / 1024, 2) . " KB\n";
    
    
    // ===== 6. FAZER QUERY COM JOIN =====
    
    echo "\n\n6️⃣ QUERY COM JOIN (Calendário + Recurso)\n";
    echo str_repeat("-", 80) . "\n\n";
    
    $stmt = $pdo->query(
        'SELECT 
            c.cal_data,
            c.cal_hora_inicio,
            c.cal_hora_fim,
            r.cod_nome_recurso,
            c.cal_turno_codi
         FROM codi_calendario c
         LEFT JOIN codi_recursos r ON c.cal_recurso_codi_id = r.cod_codigo_codi
         LIMIT 10'
    );
    
    $joinResult = $stmt->fetchAll(\PDO::FETCH_ASSOC);
    
    echo "Resultado da query com JOIN:\n";
    foreach ($joinResult as $row) {
        echo sprintf("  📅 %s - Recurso: %s - Turno: %d\n", 
            $row['cal_data'], 
            $row['cod_nome_recurso'] ?? 'N/A',
            $row['cal_turno_codi'] ?? 0
        );
    }
    
    
    // ===== 7. AGREGAÇÕES ÚTEIS =====
    
    echo "\n\n7️⃣ AGREGAÇÕES ÚTEIS\n";
    echo str_repeat("-", 80) . "\n\n";
    
    // Total de horas por recurso
    echo "Total de períodos de calendario por recurso:\n";
    $stmt = $pdo->query(
        'SELECT r.cod_nome_recurso, COUNT(c.cal_id) as total_periodos
         FROM codi_recursos r
         LEFT JOIN codi_calendario c ON r.cod_codigo_codi = c.cal_recurso_codi_id
         GROUP BY r.cod_id, r.cod_nome_recurso
         ORDER BY total_periodos DESC
         LIMIT 10'
    );
    
    foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
        echo sprintf("  • %s: %d períodos\n", $row['cod_nome_recurso'], $row['total_periodos']);
    }
    
    // Total de execuções por item
    echo "\nTotalExecutacoes por item:\n";
    $stmt = $pdo->query(
        'SELECT perf_item_codi, COUNT(*) as total
         FROM codi_performance
         WHERE perf_item_codi IS NOT NULL
         GROUP BY perf_item_codi
         ORDER BY total DESC
         LIMIT 5'
    );
    
    foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
        echo sprintf("  • Item %d: %d execuções\n", $row['perf_item_codi'], $row['total']);
    }
    
    
    echo "\n\n" . str_repeat("=", 80) . "\n";
    echo "✅ Análise concluída!\n\n";
    
} catch (\Exception $e) {
    echo "❌ ERRO: " . $e->getMessage() . "\n";
    if (php_sapi_name() !== 'cli') {
        echo "\nTrace: " . $e->getTraceAsString();
    }
}
?>
