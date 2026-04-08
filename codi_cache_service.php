<?php
/**
 * CODI Data Cache Service
 * 
 * Sincroniza dados CODI e os armazena em cache JSON
 * para integração com o SEQUENCIAMENTO
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once __DIR__ . '/src/Codi/CodiClient.php';

use Codi\CodiClient;

$action = $_GET['action'] ?? 'info';

try {
    
    if ($action === 'sync-all') {
        // 🔄 Sincronizar todos os dados
        
        echo "[LOG] Iniciando sincronização CODI\n\n";
        
        $codi = new CodiClient(
            'http://192.168.8.246:8080',
            'Aghiggi',
            '@Ag0351@'
        );
        
        $syncResult = [
            'timestamp' => date('Y-m-d H:i:s'),
            'status' => 'sucesso',
            'datasets' => []
        ];
        
        // 1️⃣ RECURSOS - Sincronizar TODO (pois são apenas 15)
        echo "[LOG] Sincronizando RECURSOS...\n";
        $recursos = $codi->getRecursos(['pageNumber' => 0, 'pageSize' => 100]);
        if ($recursos && isset($recursos['data'])) {
            $syncResult['datasets']['recursos'] = $recursos['data'];
            echo "[OK] " . count($recursos['data']) . " recursos sincronizados\n";
        }
        
        // 2️⃣ CALENDÁRIO - Sincronizar página por página (157k+ registros)
        echo "[LOG] Sincronizando CALENDÁRIO FABRIL (isso pode levar tempo)...\n";
        
        // Em primeiro acesso, pegar apenas 5 páginas como amostra
        $calendarioData = [];
        $pagestoFetch = 5;  // Reduzir para teste (157k / 100 = 1574 páginas)
        
        for ($i = 0; $i < $pagestoFetch; $i++) {
            $page = $codi->getCalendario(['pageNumber' => $i, 'pageSize' => 100]);
            if ($page && isset($page['data'])) {
                $calendarioData = array_merge($calendarioData, $page['data']);
                echo "[OK] Página " . ($i + 1) . " de " . ($pagestoFetch) . " (" . count($page['data']) . " registros)\n";
            }
        }
        
        $syncResult['datasets']['calendario'] = $calendarioData;
        $syncResult['datasets']['calendario_total_pages'] = 1574;
        
        // 3️⃣ PERFORMANCE - Sincronizar todo (apenas 5 páginas, 410 registros total)
        echo "[LOG] Sincronizando PERFORMANCE...\n";
        
        $perfData = [];
        for ($i = 0; $i < 5; $i++) {
            $perf = $codi->getPerformance(['pageNumber' => $i, 'pageSize' => 100]);
            if ($perf && isset($perf['data'])) {
                $perfData = array_merge($perfData, $perf['data']);
                echo "[OK] Página " . ($i + 1) . " de 5 (" . count($perf['data']) . " registros)\n";
                
                if ($i >= 4) break;  // Apenas 5 páginas
            }
        }
        
        $syncResult['datasets']['performance'] = $perfData;
        
        // Salvar em JSON
        $cacheDir = __DIR__ . '/cache';
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0755, true);
        }
        
        $cacheFile = $cacheDir . '/codi_cache.json';
        file_put_contents($cacheFile, json_encode($syncResult, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        
        echo "\n✅ Sincronização concluída!\n";
        echo "   Arquivo: $cacheFile\n";
        echo "   Tamanho: " . round(filesize($cacheFile) / 1024 / 1024, 2) . " MB\n";
        
        echo json_encode([
            'status' => 'sucesso',
            'message' => 'Dados sincronizados e salvos em cache',
            'cache_file' => $cacheFile,
            'sync_result' => $syncResult
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        
    } elseif ($action === 'get-cache') {
        // 📖 Retornar dados em cache
        
        $cacheFile = __DIR__ . '/cache/codi_cache.json';
        
        if (!file_exists($cacheFile)) {
            http_response_code(404);
            echo json_encode([
                'status' => 'erro',
                'message' => 'Cache não encontrado. Execute sync-all primeiro.'
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            die(1);
        }
        
        $cache = json_decode(file_get_contents($cacheFile), true);
        
        echo json_encode([
            'status' => 'sucesso',
            'cache' => $cache
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        
    } else {
        // ℹ️ Info
        
        echo "ℹ️ CODI Data Cache Service\n\n";
        echo "Ações:\n";
        echo "  ?action=sync-all  - Sincronizar dados do CODI\n";
        echo "  ?action=get-cache - Obter dados em cache\n";
        echo "  ?action=info      - Esta mensagem\n";
    }
    
} catch (\Exception $e) {
    http_response_code(500);
    echo "❌ ERRO: " . $e->getMessage() . "\n";
    if (isset($_GET['debug'])) {
        echo "\nTrace:\n" . $e->getTraceAsString();
    }
    die(1);
}
?>
