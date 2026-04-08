<?php
/**
 * Script para sincronizar dados de 2026 do CODI
 * Busca apenas dados do período 2026-01-01 até hoje
 */

require_once __DIR__ . '/src/bootstrap.php';

$pdo = \App\Database\Connection::get();
$client = new \App\Codi\CodiClient();

echo "=== SINCRONIZANDO DADOS 2026 DO CODI ===\n\n";

// 1. LIMPAR DADOS ANTIGOS
echo "1. Limpando dados antigos (pré-2026)...\n";
$pdo->query('DELETE FROM codi_calendario WHERE YEAR(cal_data) < 2026');
$pdo->query('DELETE FROM codi_performance WHERE perf_codigo_codi NOT IN (SELECT cal_codigo_codi FROM codi_calendario)');
echo "   ✓ Dados antigos removidos\n\n";

// 2. SINCRONIZAR CALENDÁRIO FABRIL (2026)
echo "2. Sincronizando Calendário Fabril 2026...\n";
$totalCalendario = 0;
$pagina = 1;
$maxPaginas = 100;

while ($pagina <= $maxPaginas) {
    echo "   Página $pagina...";
    
    try {
        $calendario = $client->getCalendarioFabril($pagina);
        
        if (empty($calendario)) {
            echo " (vazio)\n";
            break;
        }
        
        $count = 0;
        foreach ($calendario as $item) {
            // Filtrar apenas 2026
            $data = $item['calendarData'] ?? null;
            if (!$data || strpos($data, '2026') === false) {
                continue;
            }
            
            // Inserir ou atualizar
            $stmt = $pdo->prepare('
                INSERT INTO codi_calendario 
                (cal_codigo_codi, cal_data, cal_hora_inicio, cal_hora_fim, cal_recurso_codi_id, cal_turno_codi, cal_grandeza_codi, cal_dados_json)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                    cal_data = VALUES(cal_data),
                    cal_hora_inicio = VALUES(cal_hora_inicio),
                    cal_hora_fim = VALUES(cal_hora_fim)
            ');
            
            $dataObj = new DateTime($data);
            $horaInicio = $item['horaInicio'] ?? '00:00:00';
            $horaFim = $item['horaFim'] ?? '23:59:59';
            
            $stmt->execute([
                $item['codigoCalendario'],
                $dataObj->format('Y-m-d'),
                $horaInicio,
                $horaFim,
                $item['recurso']['codigoRecurso'] ?? null,
                $item['turno']['codigoTurno'] ?? null,
                $item['grandeza']['codigoGrandeza'] ?? null,
                json_encode($item, JSON_UNESCAPED_UNICODE)
            ]);
            
            $count++;
            $totalCalendario++;
        }
        
        echo " ($count inseridos)\n";
        
        if (count($calendario) < 20) {
            break;
        }
        
        $pagina++;
        
    } catch (\Exception $e) {
        echo " ERRO: " . $e->getMessage() . "\n";
        break;
    }
}

echo "   Total calendário 2026: $totalCalendario\n\n";

// 3. SINCRONIZAR PERFORMANCE (2026)
echo "3. Sincronizando Performance 2026...\n";
$totalPerformance = 0;
$pagina = 1;
$maxPaginas = 100;

while ($pagina <= $maxPaginas) {
    echo "   Página $pagina...";
    
    try {
        $performance = $client->getPerformance($pagina);
        
        if (empty($performance)) {
            echo " (vazio)\n";
            break;
        }
        
        $count = 0;
        foreach ($performance as $item) {
            // Tentar filtrar por year (pode não estar disponível)
            // Vamos inserir tudo e depois verificar
            
            $stmt = $pdo->prepare('
                INSERT INTO codi_performance 
                (perf_codigo_codi, perf_recurso_codi_id, perf_item_codi, perf_ordem_producao, perf_dados_json)
                VALUES (?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                    perf_dados_json = VALUES(perf_dados_json)
            ');
            
            $stmt->execute([
                $item['codigoPerformance'],
                $item['grandeza']['recurso']['codigoRecurso'] ?? null,
                $item['item']['codigoItem'] ?? null,
                $item['ordemProducao'] ?? null,
                json_encode($item, JSON_UNESCAPED_UNICODE)
            ]);
            
            $count++;
            $totalPerformance++;
        }
        
        echo " ($count inseridos)\n";
        
        if (count($performance) < 20) {
            break;
        }
        
        $pagina++;
        
    } catch (\Exception $e) {
        echo " ERRO: " . $e->getMessage() . "\n";
        break;
    }
}

echo "   Total performance: $totalPerformance\n\n";

// 4. SINCRONIZAR RECURSOS
echo "4. Sincronizando Recursos...\n";
try {
    $recursos = $client->getRecursos(1);
    
    $count = 0;
    foreach ($recursos as $recurso) {
        $stmt = $pdo->prepare('
            INSERT INTO codi_recursos 
            (cod_codigo_codi, cod_nome_recurso, cod_ativo, cod_dados_json)
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
                cod_nome_recurso = VALUES(cod_nome_recurso),
                cod_ativo = VALUES(cod_ativo)
        ');
        
        $stmt->execute([
            $recurso['codigoRecurso'],
            $recurso['nomeRecurso'],
            1,
            json_encode($recurso, JSON_UNESCAPED_UNICODE)
        ]);
        
        $count++;
    }
    
    echo "   ✓ $count recursos sincronizados\n\n";
    
} catch (\Exception $e) {
    echo "   ERRO: " . $e->getMessage() . "\n\n";
}

// 5. RELATÓRIO FINAL
echo "=== RELATÓRIO FINAL ===\n";
$calendarioCount = (int)$pdo->query('SELECT COUNT(*) FROM codi_calendario WHERE YEAR(cal_data) = 2026')->fetchColumn();
$performanceCount = (int)$pdo->query('SELECT COUNT(*) FROM codi_performance')->fetchColumn();
$recursosCount = (int)$pdo->query('SELECT COUNT(*) FROM codi_recursos')->fetchColumn();

echo "Calendário 2026: $calendarioCount registros\n";
echo "Performance: $performanceCount registros\n";
echo "Recursos: $recursosCount registros\n";

// Datas
$result = $pdo->query('
    SELECT MIN(cal_data) as data_min, MAX(cal_data) as data_max 
    FROM codi_calendario 
    WHERE YEAR(cal_data) = 2026
');
$dates = $result->fetch(\PDO::FETCH_ASSOC);
echo "\nPeríodo: " . $dates['data_min'] . " até " . $dates['data_max'] . "\n";

echo "\n✓ Sincronização concluída!\n";
?>
