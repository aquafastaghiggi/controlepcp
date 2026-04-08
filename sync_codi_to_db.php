<?php
/**
 * Script de Sincronização CODI
 * 
 * Extrai dados da API CODI e os armazena no banco controlepcp_sandbox
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once __DIR__ . '/src/Database/Connection.php';
require_once __DIR__ . '/src/Codi/CodiClient.php';

use App\Database\Connection;
use Codi\CodiClient;

echo "🔄 SINCRONIZANDO DADOS CODI\n";
echo str_repeat("=", 80) . "\n\n";

try {
    $pdo = Connection::get();
    echo "✅ Conectado ao banco: controlepcp_sandbox\n\n";
    
    // Inicializar CODI
    $codi = new CodiClient(
        'http://192.168.8.246:8080',
        'Aghiggi',
        '@Ag0351@'
    );
    
    // 1️⃣ SINCRONIZAR RECURSOS
    echo "1️⃣ Sincronizando RECURSOS (máquinas/linhas)...\n";
    
    $recursos = $codi->getRecursos(['pageNumber' => 0, 'pageSize' => 100]);
    
    if ($recursos && isset($recursos['data'])) {
        $insertCount = 0;
        
        foreach ($recursos['data'] as $r) {
            try {
                $stmt = $pdo->prepare(
                    'INSERT INTO codi_recursos 
                    (cod_codigo_codi, cod_nome_recurso, cod_descricao, cod_ativo, cod_estabelecimento_codi, cod_coletor_codi, cod_dados_json)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE cod_sincronizado_em = NOW()'
                );
                
                $stmt->execute([
                    $r['codigoRecurso'],
                    $r['nomeRecurso'],
                    $r['descricao'] ?? '',
                    isset($r['ativo']) ? ($r['ativo'] ? 1 : 0) : 1,
                    $r['estabelecimento']['codigoEstabelecimento'] ?? null,
                    $r['coletor']['codigoColetor'] ?? null,
                    json_encode($r, JSON_UNESCAPED_UNICODE)
                ]);
                
                $insertCount++;
            } catch (\Exception $e) {
                echo "   ⚠️  Erro ao inserir recurso {$r['codigoRecurso']}: " . $e->getMessage() . "\n";
            }
        }
        
        echo "   ✅ $insertCount recursos sincronizados\n\n";
    } else {
        echo "   ❌ Erro ao buscar recursos\n\n";
    }
    
    // 2️⃣ SINCRONIZAR CALENDÁRIO FABRIL (primeiras 1000 linhas)
    echo "2️⃣ Sincronizando CALENDÁRIO FABRIL (primeiras 1000 registros)...\n";
    
    $calendarioCount = 0;
    
    for ($page = 0; $page < 10; $page++) {
        $calendar = $codi->getCalendario(['pageNumber' => $page, 'pageSize' => 100]);
        
        if (!$calendar || !isset($calendar['data']) || empty($calendar['data'])) {
            break;
        }
        
        foreach ($calendar['data'] as $c) {
            try {
                $stmt = $pdo->prepare(
                    'INSERT INTO codi_calendario
                    (cal_codigo_codi, cal_recurso_codi_id, cal_grandeza_codi, cal_turno_codi, cal_data, cal_hora_inicio, cal_hora_fim, cal_dados_json)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE cal_sincronizado_em = NOW()'
                );
                
                // Extrair dados aninhados com segurança
                $recursoId = $c['grandeza']['recurso']['codigoRecurso'] ?? null;
                $grandezaId = $c['grandeza']['codigoGrandeza'] ?? null;
                $turnoId = $c['turno']['codigoTurno'] ?? null;
                
                $stmt->execute([
                    $c['codigoCalendarioFabril'],
                    $recursoId,
                    $grandezaId,
                    $turnoId,
                    $c['data'],
                    substr($c['horaInicio'], 0, 8),
                    substr($c['horaFim'], 0, 8),
                    json_encode($c, JSON_UNESCAPED_UNICODE)
                ]);
                
                $calendarioCount++;
            } catch (\Exception $e) {
                // Skip
            }
        }
        
        echo "   → Página " . ($page + 1) . ": " . count($calendar['data']) . " registros\n";
    }
    
    echo "   ✅ $calendarioCount registros de calendário sincronizados\n\n";
    
    // 3️⃣ SINCRONIZAR PERFORMANCE (todas as 5 páginas)
    echo "3️⃣ Sincronizando PERFORMANCE (todos os registros)...\n";
    
    $performanceCount = 0;
    
    for ($page = 0; $page < 5; $page++) {
        $perf = $codi->getPerformance(['pageNumber' => $page, 'pageSize' => 100]);
        
        if (!$perf || !isset($perf['data']) || empty($perf['data'])) {
            break;
        }
        
        foreach ($perf['data'] as $p) {
            try {
                $stmt = $pdo->prepare(
                    'INSERT INTO codi_performance
                    (perf_codigo_codi, perf_recurso_codi_id, perf_item_codi, perf_ordem_producao, perf_dados_json)
                    VALUES (?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE perf_sincronizado_em = NOW()'
                );
                
                $recursoId = $p['grandeza']['recurso']['codigoRecurso'] ?? null;
                $itemId = $p['item']['codigoItem'] ?? null;
                $ordem = $p['ordemProducao'] ?? null;
                
                $stmt->execute([
                    $p['codigoPerformance'],
                    $recursoId,
                    $itemId,
                    $ordem,
                    json_encode($p, JSON_UNESCAPED_UNICODE)
                ]);
                
                $performanceCount++;
            } catch (\Exception $e) {
                // Skip
            }
        }
        
        echo "   → Página " . ($page + 1) . ": " . count($perf['data']) . " registros\n";
    }
    
    echo "   ✅ $performanceCount registros de performance sincronizados\n\n";
    
    // 4️⃣ RESUMO FINAL
    echo str_repeat("=", 80) . "\n";
    echo "✅ SINCRONIZAÇÃO CONCLUÍDA COM SUCESSO!\n\n";
    
    // Contar registros por tabela
    $tables = ['codi_recursos', 'codi_calendario', 'codi_performance'];
    
    foreach ($tables as $table) {
        $result = $pdo->query("SELECT COUNT(*) FROM $table");
        $count = $result->fetchColumn();
        echo "   $table: $count registros\n";
    }
    
    echo "\n";
    
} catch (\Exception $e) {
    echo "❌ ERRO: " . $e->getMessage() . "\n";
    if (isset($_GET['debug'])) {
        echo "\n" . $e->getTraceAsString();
    }
    die(1);
}
