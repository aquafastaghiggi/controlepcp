<?php
// Teste rápido do endpoint históricos
error_reporting(E_ALL);
ini_set('display_errors', '1');

session_start();
$_SESSION['usuario_id'] = 1; // Simular autenticação

require_once __DIR__ . '/src/bootstrap.php';

use App\Database\Connection;

echo "🔍 Testando dados históricos direto do banco\n";
echo "=" . str_repeat("=", 80) . "\n\n";

try {
    $pdo = Connection::get();
    
    // Query igual ao API
    $sql = "
        SELECT 
            p.prg_id,
            p.prg_numero_op,
            l.lin_codigo,
            p.prg_eficiencia,
            s.sch_id,
            s.sch_sku,
            s.sch_duracao_minutos,
            s.sch_inicio_planejado,
            s.sch_inicio_producao,
            s.sch_fim_producao,
            TIMESTAMPDIFF(MINUTE, s.sch_inicio_producao, s.sch_fim_producao) as duracao_real_minutos,
            TIMESTAMPDIFF(MINUTE, s.sch_inicio_producao, s.sch_fim_producao) - s.sch_duracao_minutos as desvio_minutos
        FROM prg_programas p
        LEFT JOIN lin_linhas l ON l.lin_id = p.prg_linha_id
        LEFT JOIN sch_linhas s ON s.sch_programa_id = p.prg_id
        WHERE s.sch_fim_producao IS NOT NULL
        AND s.sch_inicio_producao IS NOT NULL
        AND s.sch_sku IS NOT NULL
        AND s.sch_duracao_minutos IS NOT NULL
        AND s.sch_duracao_minutos > 0
        ORDER BY s.sch_data_inicio DESC
        LIMIT 10
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $historicos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "✅ Total de registros encontrados: " . count($historicos) . "\n\n";
    
    if (count($historicos) > 0) {
        echo "📋 PRIMEIROS 10 REGISTROS:\n";
        echo str_repeat("-", 120) . "\n";
        printf("%-40s | %-10s | %-12s | %-12s | %-12s | %-10s\n", 
            "SKU - OP", "Duracao", "Planejado", "Real", "Desvio", "ID Sch");
        echo str_repeat("-", 120) . "\n";
        
        foreach ($historicos as $h) {
            $label = ($h['sch_sku'] ? substr($h['sch_sku'], 0, 20) : 'N/A') . " - " . (substr($h['prg_numero_op'], 0, 15) ?? 'N/A');
            printf("%-40s | %10s | %12s | %12s | %12s | %10d\n",
                $label,
                $h['sch_duracao_minutos'] . 'm',
                date('Y-m-d', strtotime($h['sch_inicio_planejado'] ?? '2026-01-01')),
                $h['duracao_real_minutos'] ?? '???',
                $h['desvio_minutos'] ?? '???',
                $h['sch_id']
            );
        }
        echo str_repeat("-", 120) . "\n";
    } else {
        echo "⚠️ Nenhum registro encontrado com dados completos\n";
        
        // Diagnóstico
        echo "\nExecutando diagnóstico...\n\n";
        
        $queries = [
            "Total de sch_linhas" => "SELECT COUNT(*) as cnt FROM sch_linhas",
            "Com sch_fim_producao" => "SELECT COUNT(*) as cnt FROM sch_linhas WHERE sch_fim_producao IS NOT NULL",
            "Com sch_inicio_producao" => "SELECT COUNT(*) as cnt FROM sch_linhas WHERE sch_inicio_producao IS NOT NULL",
            "Com ambos timestamps" => "SELECT COUNT(*) as cnt FROM sch_linhas WHERE sch_inicio_producao IS NOT NULL AND sch_fim_producao IS NOT NULL",
            "Com duracao > 0" => "SELECT COUNT(*) as cnt FROM sch_linhas WHERE sch_duracao_minutos IS NOT NULL AND sch_duracao_minutos > 0",
            "Com SKU" => "SELECT COUNT(*) as cnt FROM sch_linhas WHERE sch_sku IS NOT NULL",
            "Com TUDO junto" => "SELECT COUNT(*) as cnt FROM sch_linhas WHERE sch_inicio_producao IS NOT NULL AND sch_fim_producao IS NOT NULL AND sch_sku IS NOT NULL AND sch_duracao_minutos > 0",
        ];
        
        foreach ($queries as $label => $q) {
            $r = $pdo->query($q)->fetch(PDO::FETCH_ASSOC);
            printf("  %-30s: %d\n", $label, $r['cnt']);
        }
    }
    
} catch (Exception $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString();
}

echo "\n" . str_repeat("=", 80) . "\n";

