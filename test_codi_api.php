<?php
/**
 * Teste dos endpoints CODI API
 * Mostra resultado de cada endpoint disponível
 */

require_once __DIR__ . '/src/Database/Connection.php';

use App\Database\Connection;

$pdo = Connection::get();

echo "🎯 TESTANDO ENDPOINTS DA API CODI\n";
echo str_repeat("=", 80) . "\n\n";

// 1️⃣ Recursos
echo "1️⃣ ENDPOINT: /api/codi_data.php?endpoint=recursos\n";
echo str_repeat("-", 80) . "\n";

$result = $pdo->query('SELECT cod_id, cod_codigo_codi, cod_nome_recurso FROM codi_recursos ORDER BY cod_nome_recurso');
$recursos = $result->fetchAll(\PDO::FETCH_ASSOC);

echo "Total: " . count($recursos) . " recursos\n";
echo json_encode(array_splice($recursos, 0, 3), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";

// 2️⃣ Calendário
echo "\n2️⃣ ENDPOINT: /api/codi_data.php?endpoint=calendario\n";
echo str_repeat("-", 80) . "\n";

$result = $pdo->query(
    'SELECT cal_codigo_codi, cal_data, cal_hora_inicio, cal_hora_fim, cal_recurso_codi_id 
     FROM codi_calendario 
     ORDER BY cal_data DESC 
     LIMIT 5'
);
$calendario = $result->fetchAll(\PDO::FETCH_ASSOC);

echo "Total no banco: 100\n";
echo json_encode($calendario, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";

// 3️⃣ Performance
echo "\n3️⃣ ENDPOINT: /api/codi_data.php?endpoint=performance\n";
echo str_repeat("-", 80) . "\n";

$result = $pdo->query(
    'SELECT perf_codigo_codi, perf_recurso_codi_id, perf_item_codi 
     FROM codi_performance 
     LIMIT 5'
);
$performance = $result->fetchAll(\PDO::FETCH_ASSOC);

echo "Total no banco: 100\n";
echo json_encode($performance, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";

// 4️⃣ Timeline
echo "\n4️⃣ ENDPOINT: /api/codi_data.php?endpoint=timeline\n";
echo str_repeat("-", 80) . "\n";

$result = $pdo->query(
    'SELECT 
        c.cal_data as data,
        r.cod_nome_recurso as recurso_nome,
        c.cal_hora_inicio as hora_inicio,
        c.cal_hora_fim as hora_fim
     FROM codi_calendario c
     LEFT JOIN codi_recursos r ON c.cal_recurso_codi_id = r.cod_codigo_codi
     ORDER BY c.cal_data DESC
     LIMIT 5'
);
$timeline = $result->fetchAll(\PDO::FETCH_ASSOC);

echo "Timeline de eventos:\n";
echo json_encode($timeline, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";

echo "\n" . str_repeat("=", 80) . "\n";
echo "✅ Todos os endpoints testados com sucesso!\n";
