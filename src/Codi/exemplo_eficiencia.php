<?php
/**
 * FASE 4 - EXEMPLOS DE USO: EficienciaCalculator
 * 
 * Demonstra como utilizar a calculadora de eficiência
 * para cruzar dados de programações com performance real do CODI
 */

require_once __DIR__ . '/../../bootstrap.php';

use Codi\EficienciaCalculator;
use Codi\CodiSyncService;

$db = \Src\Database\Connection::getInstance();

// ============================================================================
// EXEMPLO 1: Cálculo básico de eficiência para período
// ============================================================================
echo "EXEMPLO 1: Cálculo Completo do Período\n";
echo str_repeat("=", 60) . "\n\n";

$calculadora = new EficienciaCalculator($db);
$calculadora->setLogging(true);

$resultado = $calculadora->calcularEficienciaCompleta(
    '2026-04-01',
    '2026-04-06'
);

echo "Status: " . ($resultado['sucesso'] ? '✓ Sucesso' : '✗ Falha') . "\n";
echo "Períodos processados: " . $resultado['periodosProcessados'] . "\n";
echo "Desvios calculados: " . $resultado['desviosCalculados'] . "\n";
echo "Erros encontrados: " . count($resultado['erros']) . "\n\n";

// ============================================================================
// EXEMPLO 2: Filtrar por recurso específico
// ============================================================================
echo "EXEMPLO 2: Cálculo Filtrado por Recurso\n";
echo str_repeat("=", 60) . "\n\n";

$resultado = $calculadora->calcularEficienciaCompleta(
    '2026-04-01',
    '2026-04-06',
    [
        'recurso_id' => 1,  // Apenas recurso 1
        'eficiencia_critica' => 70,
        'eficiencia_aviso' => 85,
        'oee_critica' => 50,
        'oee_aviso' => 75
    ]
);

echo "Resultado:\n";
echo json_encode($resultado, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";

// ============================================================================
// EXEMPLO 3: Consultar logs de cálculo
// ============================================================================
echo "EXEMPLO 3: Logs de Cálculo\n";
echo str_repeat("=", 60) . "\n\n";

$logs = $calculadora->getLogs();
echo "Total de logs: " . count($logs) . "\n\n";

foreach ($logs as $log) {
    $nivel_icon = match($log['nivel']) {
        'ERROR' => '✗',
        'WARNING' => '⚠',
        'INFO' => 'ℹ',
        default => '•'
    };
    
    echo "[{$log['timestamp']}] {$nivel_icon} {$log['nivel']}: {$log['mensagem']}\n";
}

echo "\n";

// ============================================================================
// EXEMPLO 4: Sincronizar dados CODI antes de calcular
// ============================================================================
echo "EXEMPLO 4: Sincronizar + Calcular\n";
echo str_repeat("=", 60) . "\n\n";

try {
    // Primeiro, sincronizar dados do CODI
    $sync = new CodiSyncService($db);
    $sync->setLogging(true);
    
    echo "Sincronizando dados de performance do CODI...\n";
    $syncResult = $sync->syncPerformance();
    echo "Performance sincronizada: {$syncResult} registros\n\n";
    
    // Depois calcular eficiência
    echo "Calculando eficiência...\n";
    $resultado = $calculadora->calcularEficienciaCompleta(
        '2026-04-05',
        '2026-04-06'
    );
    
    echo "Cálculo concluído com sucesso!\n";
    echo "Detalhes:\n";
    echo json_encode($resultado, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";

} catch (Exception $e) {
    echo "Erro: " . $e->getMessage() . "\n";
}

echo "\n";

// ============================================================================
// EXEMPLO 5: Consultar eficiência calculada no BD
// ============================================================================
echo "EXEMPLO 5: Consultar Eficiência no Banco\n";
echo str_repeat("=", 60) . "\n\n";

try {
    $stmt = $db->prepare("
        SELECT 
            id,
            programacao_id,
            recurso_id,
            taxa_eficiencia,
            taxa_performance,
            oee,
            status_geral,
            data_medicao
        FROM cdi_eficiencia_medicao
        ORDER BY data_medicao DESC
        LIMIT 10
    ");
    $stmt->execute();
    $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Últimos 10 registros de eficiência:\n\n";
    foreach ($resultados as $row) {
        echo "ID: {$row['id']} | Prog: {$row['programacao_id']} | Recurso: {$row['recurso_id']}\n";
        echo "  Eficiência: {$row['taxa_eficiencia']}% | Performance: {$row['taxa_performance']}% | OEE: {$row['oee']}%\n";
        echo "  Status: {$row['status_geral']} | Data: {$row['data_medicao']}\n\n";
    }
    
} catch (Exception $e) {
    echo "Erro ao consultar: " . $e->getMessage() . "\n";
}

echo "\n";

// ============================================================================
// EXEMPLO 6: Limites customizados
// ============================================================================
echo "EXEMPLO 6: Usando Limites Customizados (Rigoroso)\n";
echo str_repeat("=", 60) . "\n\n";

$resultado = $calculadora->calcularEficienciaCompleta(
    '2026-04-01',
    '2026-04-06',
    [
        'eficiencia_critica' => 90,      // Muito rigoroso
        'eficiencia_aviso' => 95,
        'oee_critica' => 80,
        'oee_aviso' => 90,
        'atraso_dias_critico' => 1,      // Crítico com apenas 1 dia
        'atraso_dias_aviso' => 0.5       // Aviso com 12 horas
    ]
);

echo "Resultado com critérios rigorosos:\n";
echo json_encode($resultado, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";

// ============================================================================
// EXEMPLO 7: Desabilitar logging (modo silencioso)
// ============================================================================
echo "EXEMPLO 7: Modo Silencioso\n";
echo str_repeat("=", 60) . "\n\n";

$calculadora->setLogging(false);
$resultado = $calculadora->calcularEficienciaCompleta(
    '2026-04-01',
    '2026-04-06'
);

$logs = $calculadora->getLogs();
echo "Logs gerados em modo silencioso: " . count($logs) . "\n";
echo "Resultado: " . ($resultado['sucesso'] ? 'Sucesso' : 'Falha') . "\n\n";

// ============================================================================
// EXEMPLO 8: Buscar ineficiências críticas
// ============================================================================
echo "EXEMPLO 8: Identificar Ineficiências Críticas\n";
echo str_repeat("=", 60) . "\n\n";

try {
    $stmt = $db->prepare("
        SELECT 
            id,
            programacao_id,
            recurso_id,
            taxa_eficiencia,
            desvio_quantidade,
            desvio_dias,
            status_geral,
            data_medicao
        FROM cdi_eficiencia_medicao
        WHERE status_geral = 'critico'
        ORDER BY data_medicao DESC
        LIMIT 5
    ");
    $stmt->execute();
    $criticos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($criticos) > 0) {
        echo "Encontrados " . count($criticos) . " registros críticos:\n\n";
        foreach ($criticos as $row) {
            echo "📊 Medição ID: {$row['id']}\n";
            echo "   Programação: {$row['programacao_id']} | Recurso: {$row['recurso_id']}\n";
            echo "   Eficiência: {$row['taxa_eficiencia']}%\n";
            echo "   Desvio Quantidade: {$row['desvio_quantidade']} unidades\n";
            echo "   Desvio Prazo: {$row['desvio_dias']} dias\n";
            echo "   Data: {$row['data_medicao']}\n\n";
        }
    } else {
        echo "✓ Nenhum registro crítico encontrado.\n\n";
    }
    
} catch (Exception $e) {
    echo "Erro: " . $e->getMessage() . "\n";
}

echo "FIM DOS EXEMPLOS\n";
echo str_repeat("=", 60) . "\n";
