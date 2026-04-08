<?php
/**
 * Script para explorar e trabalhar com dados CODI sincronizados
 * 
 * Mostra dados reais do banco de forma estruturada
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once __DIR__ . '/src/Database/Connection.php';

use App\Database\Connection;

$pdo = Connection::get();

echo "\n🔍 EXPLORADOR DE DADOS CODI\n";
echo str_repeat("=", 80) . "\n\n";

try {
    
    // 1️⃣ RECURSOS
    echo "1️⃣ RECURSOS (Máquinas/Linhas de Produção)\n";
    echo str_repeat("-", 80) . "\n";
    
    $result = $pdo->query('SELECT cod_id, cod_codigo_codi, cod_nome_recurso, cod_ativo FROM codi_recursos ORDER BY cod_nome_recurso');
    $recursos = $result->fetchAll(\PDO::FETCH_ASSOC);
    
    echo sprintf("%-3s | %-4s | %-25s | %s\n", "ID", "Cod", "Nome", "Ativo");
    echo str_repeat("-", 80) . "\n";
    
    foreach ($recursos as $r) {
        $ativo = $r['cod_ativo'] ? '✅' : '❌';
        echo sprintf("%-3d | %-4d | %-25s | %s\n", 
            $r['cod_id'], 
            $r['cod_codigo_codi'], 
            substr($r['cod_nome_recurso'], 0, 25),
            $ativo
        );
    }
    
    echo "\nTotal: " . count($recursos) . " recursos\n\n";
    
    
    // 2️⃣ CALENDÁRIO FABRIL (amostra)
    echo "2️⃣ CALENDÁRIO FABRIL (Primeiras 10 linhas)\n";
    echo str_repeat("-", 80) . "\n";
    
    $result = $pdo->query(
        'SELECT cal_codigo_codi, cal_data, cal_hora_inicio, cal_hora_fim, cal_recurso_codi_id 
         FROM codi_calendario 
         ORDER BY cal_data DESC, cal_hora_inicio DESC 
         LIMIT 10'
    );
    $calendario = $result->fetchAll(\PDO::FETCH_ASSOC);
    
    echo sprintf("%-8s | %-12s | %-10s | %-10s | %s\n", "Cod", "Data", "Início", "Fim", "Recurso");
    echo str_repeat("-", 80) . "\n";
    
    foreach ($calendario as $c) {
        echo sprintf("%-8d | %-12s | %-10s | %-10s | %d\n",
            $c['cal_codigo_codi'],
            $c['cal_data'],
            $c['cal_hora_inicio'],
            $c['cal_hora_fim'],
            $c['cal_recurso_codi_id'] ?? 'N/A'
        );
    }
    
    // Contar total
    $total = $pdo->query('SELECT COUNT(*) FROM codi_calendario')->fetchColumn();
    echo "\nTotal no banco: " . $total . " registros\n\n";
    
    
    // 3️⃣ PERFORMANCE (amostra)
    echo "3️⃣ PERFORMANCE (Primeiras 10 linhas)\n";
    echo str_repeat("-", 80) . "\n";
    
    $result = $pdo->query(
        'SELECT perf_codigo_codi, perf_recurso_codi_id, perf_item_codi, perf_ordem_producao 
         FROM codi_performance 
         LIMIT 10'
    );
    $performance = $result->fetchAll(\PDO::FETCH_ASSOC);
    
    echo sprintf("%-6s | %-8s | %-6s | %s\n", "Cod", "Recurso", "Item", "Ordem Produção");
    echo str_repeat("-", 80) . "\n";
    
    foreach ($performance as $p) {
        echo sprintf("%-6d | %-8s | %-6s | %s\n",
            $p['perf_codigo_codi'],
            $p['perf_recurso_codi_id'] ?? '-',
            $p['perf_item_codi'] ?? '-',
            $p['perf_ordem_producao'] ?? '-'
        );
    }
    
    // Contar total
    $total = $pdo->query('SELECT COUNT(*) FROM codi_performance')->fetchColumn();
    echo "\nTotal no banco: " . $total . " registros\n\n";
    
    
    // 4️⃣ ANÁLISES
    echo "4️⃣ ANÁLISES E AGREGAÇÕES\n";
    echo str_repeat("-", 80) . "\n";
    
    // Calendário por recurso
    echo "\n📊 Calendário por Recurso:\n";
    $result = $pdo->query(
        'SELECT cal_recurso_codi_id, COUNT(*) as quantidade 
         FROM codi_calendario 
         WHERE cal_recurso_codi_id IS NOT NULL
         GROUP BY cal_recurso_codi_id 
         ORDER BY quantidade DESC'
    );
    $porRecurso = $result->fetchAll(\PDO::FETCH_ASSOC);
    
    foreach ($porRecurso as $r) {
        echo sprintf("   Recurso %d: %d períodos de calendário\n", $r['cal_recurso_codi_id'], $r['quantidade']);
    }
    
    // Calendário por data
    echo "\n📅 Calendário por Data:\n";
    $result = $pdo->query(
        'SELECT cal_data, COUNT(*) as turnos 
         FROM codi_calendario 
         GROUP BY cal_data 
         ORDER BY cal_data DESC 
         LIMIT 5'
    );
    $porData = $result->fetchAll(\PDO::FETCH_ASSOC);
    
    foreach ($porData as $d) {
        echo sprintf("   %s: %d turnos\n", $d['cal_data'], $d['turnos']);
    }
    
    // Performance por recurso
    echo "\n⚙️  Performance por Recurso:\n";
    $result = $pdo->query(
        'SELECT perf_recurso_codi_id, COUNT(*) as execucoes 
         FROM codi_performance 
         WHERE perf_recurso_codi_id IS NOT NULL
         GROUP BY perf_recurso_codi_id 
         ORDER BY execucoes DESC'
    );
    $perfPorRecurso = $result->fetchAll(\PDO::FETCH_ASSOC);
    
    foreach ($perfPorRecurso as $p) {
        echo sprintf("   Recurso %d: %d execuções\n", $p['perf_recurso_codi_id'], $p['execucoes']);
    }
    
    
    // 5️⃣ EXEMPLO: Detalhe completo de um registro
    echo "\n\n5️⃣ EXEMPLO DE DADOS COMPLETOS\n";
    echo str_repeat("-", 80) . "\n";
    
    // Um calendário com dados JSON completos
    echo "\n📋 Calendário (com dados JSON):\n";
    $result = $pdo->query(
        'SELECT cal_codigo_codi, cal_data, cal_hora_inicio, cal_hora_fim, cal_dados_json 
         FROM codi_calendario 
         LIMIT 1'
    );
    $cal = $result->fetch(\PDO::FETCH_ASSOC);
    
    if ($cal) {
        echo "Código: " . $cal['cal_codigo_codi'] . "\n";
        echo "Data: " . $cal['cal_data'] . " de " . $cal['cal_hora_inicio'] . " a " . $cal['cal_hora_fim'] . "\n";
        
        $dados = json_decode($cal['cal_dados_json'], true);
        if ($dados) {
            echo "\nDados Completos (JSON):\n";
            echo json_encode($dados, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
    }
    
    // Um performance com dados JSON completos
    echo "\n\n📋 Performance (com dados JSON):\n";
    $result = $pdo->query(
        'SELECT perf_codigo_codi, perf_dados_json 
         FROM codi_performance 
         LIMIT 1'
    );
    $perf = $result->fetch(\PDO::FETCH_ASSOC);
    
    if ($perf) {
        echo "Código: " . $perf['perf_codigo_codi'] . "\n";
        
        $dados = json_decode($perf['perf_dados_json'], true);
        if ($dados) {
            echo "\nDados Completos (JSON):\n";
            echo json_encode($dados, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
    }
    
    echo "\n\n" . str_repeat("=", 80) . "\n";
    echo "✅ Exploração concluída!\n";
    
} catch (\Exception $e) {
    echo "❌ ERRO: " . $e->getMessage() . "\n";
    die(1);
}
