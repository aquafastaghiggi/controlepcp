<?php
/**
 * Script para sincronizar dados do CODI
 * 
 * FASE 6 - Integração CODI: Extração de dados
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once __DIR__ . '/src/Codi/CodiClient.php';

use Codi\CodiClient;

echo "🔄 SINCRONIZANDO DADOS DO CODI\n";
echo str_repeat("=", 80) . "\n\n";

try {
    // Conectar ao CODI
    $codi = new CodiClient(
        'http://192.168.8.246:8080',
        'Aghiggi',
        '@Ag0351@'
    );
    
    // 1️⃣ RECURSOS (Máquinas/Linhas)
    echo "1️⃣ Buscando RECURSOS...\n";
    $resources = $codi->getRecursos(['pageNumber' => 0, 'pageSize' => 100]);
    
    if ($resources) {
        echo "   ✅ " . count($resources['data']) . " recursos encontrados\n";
        echo "      Total: " . $resources['totalCount'] . " | Páginas: " . $resources['totalPages'] . "\n";
        $firstResource = $resources['data'][0] ?? null;
        if ($firstResource) {
            echo "      Ex: " . $firstResource['nomeRecurso'] ?? 'N/A' . "\n";
        }
    } else {
        echo "   ❌ Erro ao buscar recursos\n";
        $resources = null;
    }
    echo "\n";
    
    // 2️⃣ CALENDÁRIO FABRIL
    echo "2️⃣ Buscando CALENDÁRIO FABRIL...\n";
    $calendar = $codi->getCalendario(['pageNumber' => 0, 'pageSize' => 100]);
    
    if ($calendar) {
        echo "   ✅ " . count($calendar['data']) . " registros encontrados\n";
        echo "      Total: " . $calendar['totalCount'] . " | Páginas: " . $calendar['totalPages'] . "\n";
        $firstCalendar = $calendar['data'][0] ?? null;
        if ($firstCalendar) {
            echo "      Ex: " . ($firstCalendar['data'] ?? 'N/A') . " " . ($firstCalendar['horaInicio'] ?? 'N/A') . "\n";
        }
    } else {
        echo "   ❌ Erro ao buscar calendário\n";
        $calendar = null;
    }
    echo "\n";
    
    // 3️⃣ PERFORMANCE
    echo "3️⃣ Buscando PERFORMANCE...\n";
    $performance = $codi->getPerformance(['pageNumber' => 0, 'pageSize' => 100]);
    
    if ($performance) {
        echo "   ✅ " . count($performance['data']) . " registros encontrados\n";
        echo "      Total: " . $performance['totalCount'] . " | Páginas: " . $performance['totalPages'] . "\n";
        $firstPerf = $performance['data'][0] ?? null;
        if ($firstPerf) {
            echo "      Ex: " . ($firstPerf['codigoPerformance'] ?? 'N/A') . "\n";
        }
    } else {
        echo "   ❌ Erro ao buscar performance\n";
        $performance = null;
    }
    echo "\n";
    
    // 4️⃣ SALVAR DADOS LOCALMENTE
    echo "4️⃣ Salvando dados localmente...\n";
    
    $exportData = [
        'timestamp' => date('Y-m-d H:i:s'),
        'servidor' => '192.168.8.246:8080',
        'status' => 'sucesso',
        'dados' => [
            'recursos' => $resources,
            'calendario_fabril' => $calendar,
            'performance' => $performance,
        ]
    ];
    
    $jsonPath = __DIR__ . '/codi_dados_exportados.json';
    file_put_contents($jsonPath, json_encode($exportData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    echo "   ✅ Dados salvos em: $jsonPath\n";
    
    // 5️⃣ RESUMO FINAL
    echo "\n" . str_repeat("=", 80) . "\n";
    echo "✅ SINCRONIZAÇÃO CONCLUÍDA COM SUCESSO!\n";
    echo "   • Recursos: " . ($resources ? count($resources['data']) . "/" . $resources['totalCount'] : "0") . "\n";
    echo "   • Calendário: " . ($calendar ? count($calendar['data']) . "/" . $calendar['totalCount'] : "0") . "\n";
    echo "   • Performance: " . ($performance ? count($performance['data']) . "/" . $performance['totalCount'] : "0") . "\n";
    echo "\n";
    
} catch (\Exception $e) {
    echo "❌ ERRO: " . $e->getMessage() . "\n";
    die(1);
}
