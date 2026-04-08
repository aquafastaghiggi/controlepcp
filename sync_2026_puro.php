<?php
/**
 * Script para sincronizar dados de 2026 do CODI NÃO PRECISA
 * Versão sem dependências de classe
 */

// Conexão direta
$pdo = new PDO(
    'mysql:host=127.0.0.1:3306;dbname=controlepcp_sandbox;charset=utf8mb4',
    'root',
    'k7m2y9u4'
);

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

echo "=== SINCRONIZANDO DADOS 2026 DO CODI ===\n\n";

// 1. LIMPAR DADOS ANTIGOS
echo "1. Limpando dados antigos (pré-2026)...\n";
try {
    $pdo->query('DELETE FROM codi_calendario WHERE YEAR(cal_data) < 2026');
    echo "   ✓ Dados antigos removidos\n\n";
} catch (Exception $e) {
    echo "   ✗ Erro: " . $e->getMessage() . "\n";
}

// 2. TESTAR CONEXÃO COM CODI
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
$curlErr = curl_error($ch);
curl_close($ch);

echo "   HTTP $httpCode\n";
if (!empty($curlErr)) {
    echo "   ✗ Erro cURL: $curlErr\n";
    exit(1);
}

if ($httpCode !== 200) {
    echo "   ✗ Resposta HTTP inválida\n";
    exit(1);
}

echo "   ✓ Conectado ao CODI\n\n";

// 3. Decodificar resposta
echo "3. Decodificando resposta...\n";
$response = @iconv('ISO-8859-1', 'UTF-8//IGNORE', $response);
$data = json_decode($response, true);

if (!$data) {
    echo "   ✗ JSON inválido\n";
    echo "   Primeira 200 chars: " . substr($response, 0, 200) . "\n";
    exit(1);
}

echo "   ✓ JSON decodificado\n\n";

// 4. IMPORTAR CALENDÁRIO 2026
echo "4. Importando calendário 2026...\n";
$calendarioTotal = 0;
$pagina = 1;
$maxPaginas = 50;

while ($pagina <= $maxPaginas && $calendarioTotal < 2000) {
    echo "   Página $pagina...";
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => "http://192.168.8.246:8080/action/ger/webservice/rest/calendarioFabril?page=$pagina",
        CURLOPT_USERPWD => 'Aghiggi:@Ag0351@',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        echo " (HTTP $httpCode)\n";
        break;
    }
    
    $response = @iconv('ISO-8859-1', 'UTF-8//IGNORE', $response);
    $items = json_decode($response, true);
    
    if (!$items || !is_array($items)) {
        echo " (vazio)\n";
        break;
    }
    
    $count = 0;
    foreach ($items as $item) {
        // Filtrar 2026
        $data_str = $item['calendarData'] ?? null;
        if (!$data_str || strpos($data_str, '2026') === false) {
            continue;
        }
        
        try {
            $stmt = $pdo->prepare('
                INSERT IGNORE INTO codi_calendario 
                (cal_codigo_codi, cal_data, cal_hora_inicio, cal_hora_fim, cal_recurso_codi_id, cal_grandeza_codi, cal_dados_json)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ');
            
            $data_obj = new DateTime($data_str);
            $hora_inicio = $item['horaInicio'] ?? '00:00:00';
            $hora_fim = $item['horaFim'] ?? '23:59:59';
            $recurso_id = $item['recurso']['codigoRecurso'] ?? null;
            $grandeza_id = $item['grandeza']['codigoGrandeza'] ?? null;
            
            $stmt->execute([
                $item['codigoCalendario'],
                $data_obj->format('Y-m-d'),
                $hora_inicio,
                $hora_fim,
                $recurso_id,
                $grandeza_id,
                json_encode($item, JSON_UNESCAPED_UNICODE)
            ]);
            
            $count++;
            $calendarioTotal++;
        } catch (Exception $e) {
            // Ignorar duplicatas
        }
    }
    
    echo " ($count)\n";
    
    if (count($items) < 20) {
        break;
    }
    
    $pagina++;
}

echo "   Total: $calendarioTotal\n\n";

// 5. RELATÓRIO FINAL
echo "=== RELATÓRIO FINAL ===\n";
$count = (int)$pdo->query('SELECT COUNT(*) FROM codi_calendario WHERE YEAR(cal_data) = 2026')->fetchColumn();
echo "Calendário 2026: $count registros\n";

$result = $pdo->query('
    SELECT MIN(cal_data) as data_min, MAX(cal_data) as data_max 
    FROM codi_calendario 
    WHERE YEAR(cal_data) = 2026
');
$dates = $result->fetch(PDO::FETCH_ASSOC);
if ($dates['data_min']) {
    echo "Período: " . $dates['data_min'] . " até " . $dates['data_max'] . "\n";
}

echo "\n✓ Sincronização concluída!\n";
?>
