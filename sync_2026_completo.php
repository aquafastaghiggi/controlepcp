<?php
/**
 * Sincronização COMPLETA de dados 2026 do CODI
 * Procura em todas as páginas e coleta dados de 2026
 */

$auth = 'Aghiggi:@Ag0351@';
$base_url = 'http://192.168.8.246:8080';

// Conexão direta
$pdo = new PDO(
    'mysql:host=127.0.0.1:3306;dbname=controlepcp_sandbox;charset=utf8mb4',
    'root',
    'k7m2y9u4'
);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

echo "=== SINCRONIZANDO DADOS 2026 DO CODI ===\n\n";

// LIMPAR dados
echo "1. Limpando dados antigos...\n";
$pdo->query('DELETE FROM codi_calendario WHERE YEAR(cal_data) < 2026');
echo "   ✓ Dados antigos removidos\n\n";

// SINCRONIZAR - procurar em múltiplas páginas
echo "2. Buscando dados de 2026 em todas as páginas...\n";

$total_inseridos = 0;
$paginas_verificadas = 0;

// Tentar páginas específicas onde achamos 2026
$pages_to_try = [2500, 2600, 2700, 2800, 2900, 3000, 3010, 3020, 3030, 3040, 3050, 3060, 3070, 3080, 3090, 3100];

foreach ($pages_to_try as $page) {
    echo "   Página $page...";
    
    $url = $base_url . "/action/ger/webservice/rest/calendarioFabril?page=$page&limit=100";
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_USERPWD, $auth);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $response = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($code !== 200) {
        echo " HTTP $code\n";
        continue;
    }
    
    $response = @iconv('ISO-8859-1', 'UTF-8//IGNORE', $response);
    $json = json_decode($response, true);
    
    if (!isset($json['data']) || !is_array($json['data'])) {
        echo " Sem dados\n";
        continue;
    }
    
    $items = $json['data'];
    $count = 0;
    
    foreach ($items as $item) {
        // Filtrar apenas 2026
        $data_str = $item['data'] ?? null;
        if (!$data_str || strpos($data_str, '2026') === false) {
            continue;
        }
        
        // Inserir
        try {
            $stmt = $pdo->prepare('
                INSERT IGNORE INTO codi_calendario 
                (cal_codigo_codi, cal_data, cal_hora_inicio, cal_hora_fim, cal_recurso_codi_id, cal_turno_codi, cal_grandeza_codi, cal_dados_json)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ');
            
            $recurso_id = $item['grandeza']['recurso']['codigoRecurso'] ?? null;
            $grandeza_id = $item['grandeza']['codigoGrandeza'] ?? null;
            $turno_id = $item['turno']['codigoTurno'] ?? null;
            
            $stmt->execute([
                $item['codigoCalendarioFabril'],
                $data_str,
                $item['horaInicio'] ?? '00:00:00',
                $item['horaFim'] ?? '23:59:59',
                $recurso_id,
                $turno_id,
                $grandeza_id,
                json_encode($item, JSON_UNESCAPED_UNICODE)
            ]);
            
            $count++;
            $total_inseridos++;
        } catch (Exception $e) {
            // Ignorar duplicatas
        }
    }
    
    echo " ($count)\n";
    $paginas_verificadas++;
}

echo "\n   Total: $total_inseridos de $paginas_verificadas páginas\n\n";

// VERIFICAR resultado
echo "3. Verificando dados sincronizados...\n";
$count = (int)$pdo->query('SELECT COUNT(*) FROM codi_calendario WHERE YEAR(cal_data) = 2026')->fetchColumn();
echo "   ✓ Calendário 2026: $count registros\n";

$result = $pdo->query('
    SELECT MIN(cal_data) as data_min, MAX(cal_data) as data_max,
           COUNT(DISTINCT cal_recurso_codi_id) as recursos
    FROM codi_calendario 
    WHERE YEAR(cal_data) = 2026
');
$stats = $result->fetch(PDO::FETCH_ASSOC);
echo "   Período: " . ($stats['data_min'] ?? 'N/A') . " até " . ($stats['data_max'] ?? 'N/A') . "\n";
echo "   Recursos: " . ($stats['recursos'] ?? 0) . "\n\n";

// AMOSTRA
echo "4. Amostra de dados:\n";
$result = $pdo->query('
    SELECT cal_data, r.cod_nome_recurso, cal_hora_inicio, cal_hora_fim
    FROM codi_calendario c
    LEFT JOIN codi_recursos r ON c.cal_recurso_codi_id = r.cod_codigo_codi
    WHERE YEAR(c.cal_data) = 2026
    ORDER BY cal_data DESC
    LIMIT 5
');

foreach ($result->fetchAll(PDO::FETCH_ASSOC) as $row) {
    echo "   " . $row['cal_data'] . " | " . ($row['cod_nome_recurso'] ?? 'N/A') . " | " . $row['cal_hora_inicio'] . "-" . $row['cal_hora_fim'] . "\n";
}

echo "\n✓ Sincronização concluída!\n";
?>
