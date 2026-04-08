<?php
/**
 * Operações Avançadas com Dados CODI
 * Exemplos práticos de transformação e análise
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once __DIR__ . '/src/Database/Connection.php';

use App\Database\Connection;

$pdo = Connection::get();

echo "\n🚀 OPERAÇÕES AVANÇADAS COM DADOS CODI\n";
echo str_repeat("=", 80) . "\n\n";

try {
    
    // ===== 1. AGREGAÇÃO: Distribuição de calendário por recurso =====
    echo "1️⃣ DISTRIBUIÇÃO DE CALENDÁRIO POR RECURSO\n";
    echo str_repeat("-", 80) . "\n\n";
    
    $stmt = $pdo->query(
        'SELECT 
            COALESCE(r.cod_nome_recurso, "SEM RECURSO") as recurso,
            COUNT(c.cal_id) as total_periodos,
            COUNT(DISTINCT c.cal_data) as dias_diferentes,
            MIN(c.cal_data) as primeira_data,
            MAX(c.cal_data) as ultima_data
         FROM codi_calendario c
         LEFT JOIN codi_recursos r ON c.cal_recurso_codi_id = r.cod_codigo_codi
         GROUP BY c.cal_recurso_codi_id
         ORDER BY total_periodos DESC'
    );
    
    $relatorio = $stmt->fetchAll(\PDO::FETCH_ASSOC);
    
    foreach ($relatorio as $linha) {
        echo sprintf("%-20s: %3d períodos | %2d dias | %s até %s\n",
            substr($linha['recurso'], 0, 20),
            $linha['total_periodos'],
            $linha['dias_diferentes'],
            $linha['primeira_data'],
            $linha['ultima_data']
        );
    }
    
    
    // ===== 2. CORRELAÇÃO: Calendário X Performance =====
    echo "\n\n2️⃣ CORRELAÇÃO: CALENDÁRIO × PERFORMANCE\n";
    echo str_repeat("-", 80) . "\n\n";
    
    $stmt = $pdo->query(
        'SELECT 
            r.cod_nome_recurso,
            COUNT(DISTINCT c.cal_id) as periodos_calendario,
            COUNT(DISTINCT p.perf_id) as execucoes_performance,
            ROUND(COUNT(DISTINCT p.perf_id) / COUNT(DISTINCT c.cal_id), 2) as exec_por_periodo
         FROM codi_calendario c
         LEFT JOIN codi_recursos r ON c.cal_recurso_codi_id = r.cod_codigo_codi
         LEFT JOIN codi_performance p ON p.perf_recurso_codi_id = r.cod_codigo_codi
         GROUP BY r.cod_id
         HAVING COUNT(DISTINCT c.cal_id) > 0
         ORDER BY exec_por_periodo DESC'
    );
    
    $correlacao = $stmt->fetchAll(\PDO::FETCH_ASSOC);
    
    echo "Recurso                    | Calendário | Performance | Taxa\n";
    echo str_repeat("-", 80) . "\n";
    foreach ($correlacao as $linha) {
        echo sprintf("%-26s | %10d | %11d | %s\n",
            substr($linha['cod_nome_recurso'], 0, 26),
            $linha['periodos_calendario'],
            $linha['execucoes_performance'],
            'N/A'
        );
    }
    
    
    // ===== 3. ANÁLISE TEMPORAL: Distribuição por mês =====
    echo "\n\n3️⃣ ANÁLISE TEMPORAL: CALENDÁRIO POR MÊS\n";
    echo str_repeat("-", 80) . "\n\n";
    
    $stmt = $pdo->query(
        'SELECT 
            DATE_FORMAT(cal_data, "%Y-%m") as mes,
            COUNT(*) as total,
            COUNT(DISTINCT cal_data) as dias,
            MIN(cal_hora_inicio) as primeiro_horario,
            MAX(cal_hora_fim) as ultimo_horario
         FROM codi_calendario
         GROUP BY DATE_FORMAT(cal_data, "%Y-%m")
         ORDER BY mes DESC'
    );
    
    $temporal = $stmt->fetchAll(\PDO::FETCH_ASSOC);
    
    foreach ($temporal as $linha) {
        echo sprintf("Mês: %s | %3d períodos | %2d dias | %s - %s\n",
            $linha['mes'],
            $linha['total'],
            $linha['dias'],
            $linha['primeiro_horario'],
            $linha['ultimo_horario']
        );
    }
    
    
    // ===== 4. DISTRIBUIÇÃO: Items mais executados =====
    echo "\n\n4️⃣ ITEMS MAIS EXECUTADOS\n";
    echo str_repeat("-", 80) . "\n\n";
    
    $stmt = $pdo->query(
        'SELECT 
            perf_item_codi as item_id,
            COUNT(*) as total_execucoes,
            COUNT(DISTINCT perf_recurso_codi_id) as recursos_diferentes
         FROM codi_performance
         WHERE perf_item_codi IS NOT NULL
         GROUP BY perf_item_codi
         ORDER BY total_execucoes DESC
         LIMIT 10'
    );
    
    $items = $stmt->fetchAll(\PDO::FETCH_ASSOC);
    
    foreach ($items as $item) {
        echo sprintf("Item %d: %2d execuções em %d recursos\n",
            $item['item_id'],
            $item['total_execucoes'],
            $item['recursos_diferentes']
        );
    }
    
    
    // ===== 5. CRIAR OBJETO ESTRUTURADO PARA RETORNO =====
    echo "\n\n5️⃣ OBJETO ESTRUTURADO PARA FRONTEND/API\n";
    echo str_repeat("-", 80) . "\n\n";
    
    $dadosEstruturados = new stdClass();
    
    // Metadata
    $dadosEstruturados->meta = [
        'data_geracao' => date('Y-m-d H:i:s'),
        'banco_dados' => 'controlepcp_sandbox',
        'versao' => '1.0'
    ];
    
    // Recursos
    $resourceStmt = $pdo->query('SELECT cod_id, cod_codigo_codi, cod_nome_recurso, cod_ativo FROM codi_recursos ORDER BY cod_nome_recurso');
    $dadosEstruturados->recursos = [];
    foreach ($resourceStmt->fetchAll(\PDO::FETCH_ASSOC) as $r) {
        $dadosEstruturados->recursos[] = [
            'id' => (int)$r['cod_id'],
            'codigo_codi' => (int)$r['cod_codigo_codi'],
            'nome' => $r['cod_nome_recurso'],
            'ativo' => (bool)$r['cod_ativo']
        ];
    }
    
    // Timeline (agregado para o frontend)
    $timelineStmt = $pdo->query(
        'SELECT 
            c.cal_codigo_codi as codigo,
            c.cal_data as data,
            c.cal_hora_inicio as hora_inicio,
            c.cal_hora_fim as hora_fim,
            c.cal_recurso_codi_id as recurso_id,
            r.cod_nome_recurso as recurso_nome
         FROM codi_calendario c
         LEFT JOIN codi_recursos r ON c.cal_recurso_codi_id = r.cod_codigo_codi
         ORDER BY c.cal_data DESC
         LIMIT 100'
    );
    
    $dadosEstruturados->timeline = [];
    foreach ($timelineStmt->fetchAll(\PDO::FETCH_ASSOC) as $t) {
        $dadosEstruturados->timeline[] = [
            'codigo' => (int)$t['codigo'],
            'data' => $t['data'],
            'inicio' => $t['hora_inicio'],
            'fim' => $t['hora_fim'],
            'recurso_id' => (int)$t['recurso_id'],
            'recurso_nome' => $t['recurso_nome']
        ];
    }
    
    // Performance
    $perfStmt = $pdo->query(
        'SELECT 
            perf_codigo_codi,
            perf_recurso_codi_id,
            perf_item_codi
         FROM codi_performance
         LIMIT 100'
    );
    
    $dadosEstruturados->performance = [];
    foreach ($perfStmt->fetchAll(\PDO::FETCH_ASSOC) as $p) {
        $dadosEstruturados->performance[] = [
            'codigo' => (int)$p['perf_codigo_codi'],
            'recurso_id' => $p['perf_recurso_codi_id'] ? (int)$p['perf_recurso_codi_id'] : null,
            'item_id' => $p['perf_item_codi'] ? (int)$p['perf_item_codi'] : null
        ];
    }
    
    // Salvar JSON estruturado
    $jsonFile = __DIR__ . '/dados_codi_estruturados.json';
    file_put_contents($jsonFile, json_encode($dadosEstruturados, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    
    echo "Estrutura salva em: $jsonFile\n";
    echo "Tamanho: " . round(filesize($jsonFile) / 1024, 2) . " KB\n\n";
    
    echo "Estrutura contém:\n";
    echo "  • Recursos: " . count($dadosEstruturados->recursos) . "\n";
    echo "  • Timeline: " . count($dadosEstruturados->timeline) . "\n";
    echo "  • Performance: " . count($dadosEstruturados->performance) . "\n";
    
    
    // ===== 6. DEMONSTRAÇÃO: Acessar dados via objeto =====
    echo "\n\n6️⃣ ACESSANDO DADOS VIA OBJETO PHP\n";
    echo str_repeat("-", 80) . "\n\n";
    
    // Pega todos os eventos do primeiro recurso
    $primoRecurso = $dadosEstruturados->recursos[0];
    $eventosPrimoRecurso = array_filter($dadosEstruturados->timeline, 
        fn($e) => $e['recurso_id'] == $primoRecurso['codigo_codi']
    );
    
    echo "Eventos do recurso '{$primoRecurso['nome']}':\n";
    foreach (array_slice($eventosPrimoRecurso, 0, 5) as $evento) {
        echo sprintf("  📅 %s de %s a %s\n", $evento['data'], $evento['inicio'], $evento['fim']);
    }
    
    echo "\n\n" . str_repeat("=", 80) . "\n";
    echo "✅ Todas as operações avançadas concluídas com sucesso!\n\n";
    
} catch (\Exception $e) {
    echo "❌ ERRO: " . $e->getMessage() . "\n";
}
?>
