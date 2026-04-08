<?php
/**
 * Script para sincronizar dados de 2026 do CODI
 * Versão simplificada sem namespace
 */

require_once __DIR__ . '/src/bootstrap.php';

$pdo = \App\Database\Connection::get();

echo "=== SINCRONIZANDO DADOS 2026 DO CODI ===\n\n";

// 1. LIMPAR DADOS ANTIGOS
echo "1. Limpando dados antigos (pré-2026)...\n";
try {
    $pdo->query('DELETE FROM codi_calendario WHERE YEAR(cal_data) < 2026');
    $count_deletados = $pdo->rowCount();
    echo "   ✓ $count_deletados registros deletados\n\n";
} catch (Exception $e) {
    echo "   ✗ Erro: " . $e->getMessage() . "\n";
}

// 2. TESTAR CONEXÃO
echo "2. Testando conexão com CODI...\n";
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => 'http://192.168.8.246:8080/action/ger/webservice/rest/calendarioFabril?page=1',
    CURLOPT_USERPWD => 'Aghiggi:@Ag0351@',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 10
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "   HTTP $httpCode\n";
if ($httpCode === 200) {
    echo "   ✓ Conectado ao CODI\n\n";
} else {
    echo "   ✗ Erro na conexão\n\n";
    exit(1);
}

// 3. Decodificar resposta
$response = @iconv('ISO-8859-1', 'UTF-8//IGNORE', $response);
$data = json_decode($response, true);

if (!$data) {
    echo "3. Erro ao decodificar JSON\n";
    exit(1);
}

echo "3. Importando Calendário Fabril 2026...\n";
$calendarioTotal = 0;

if ($data && is_array($data)) {
    // Determinar a chave correta (pode ser 'data' ou outra)
    $items = $data['data'] ?? $data;
    
    if (!is_array($items)) {
        echo "   Formato inesperado: " . gettype($items) . "\n";
        var_dump(array_keys((array)$data));
        exit(1);
    }
    
    foreach ($items as $item) {
        // Filtrar apenas 2026
        $data_str = $item['calendarData'] ?? $item['data'] ?? null;
        if (!$data_str || strpos($data_str, '2026') === false) {
            continue;
        }
        
        try {
            $stmt = $pdo->prepare('
                INSERT IGNORE INTO codi_calendario 
                (cal_codigo_codi, cal_data, cal_hora_inicio, cal_hora_fim, cal_recurso_codi_id, cal_turno_codi, cal_grandeza_codi, cal_dados_json)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ');
            
            $data_obj = new DateTime($data_str);
            $hora_inicio = $item['horaInicio'] ?? $item['hora_inicio'] ?? '00:00:00';
            $hora_fim = $item['horaFim'] ?? $item['hora_fim'] ?? '23:59:59';
            
            $recurso_id = null;
            if (isset($item['recurso'])) {
                $recurso_id = $item['recurso']['codigoRecurso'] ?? null;
            }
            
            $stmt->execute([
                $item['codigoCalendario'],
                $data_obj->format('Y-m-d'),
                $hora_inicio,
                $hora_fim,
                $recurso_id,
                $item['turno']['codigoTurno'] ?? null,
                $item['grandeza']['codigoGrandeza'] ?? null,
                json_encode($item, JSON_UNESCAPED_UNICODE)
            ]);
            
            $calendarioTotal++;
        } catch (Exception $e) {
            // Ignorar erros de insert duplicate
        }
    }
}

echo "   ✓ $calendarioTotal registros inseridos\n\n";

// 4. RELATÓRIO FINAL
echo "=== RELATÓRIO FINAL ===\n";
$count = (int)$pdo->query('SELECT COUNT(*) FROM codi_calendario WHERE YEAR(cal_data) = 2026')->fetchColumn();
echo "Calendário 2026: $count registros\n";

$result = $pdo->query('
    SELECT MIN(cal_data) as data_min, MAX(cal_data) as data_max 
    FROM codi_calendario 
    WHERE YEAR(cal_data) = 2026
');
if ($result) {
    $dates = $result->fetch(\PDO::FETCH_ASSOC);
    echo "Período: " . ($dates['data_min'] ?? 'N/A') . " até " . ($dates['data_max'] ?? 'N/A') . "\n";
}

echo "\n✓ Sincronização concluída!\n";
?>
