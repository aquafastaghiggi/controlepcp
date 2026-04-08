<?php
/**
 * Buscar pelo número 3734 em todos os lugares
 */

$db = new PDO("mysql:host=127.0.0.1;dbname=controlepcp_sandbox;charset=utf8mb4", 'root', 'k7m2y9u4');

$codi_url = 'http://192.168.8.246:8080';
$codi_user = 'Aghiggi';
$codi_pass = '@Ag0351@';

echo "=== BUSCA GLOBAL: 3734 ===\n\n";

// 1️⃣ Banco local
echo "1️⃣ BANCO LOCAL (controlepc_sandbox):\n";
echo str_repeat("-", 70) . "\n";

$tabelas = [
    'prg_itens' => ['prg_quantidade', 'prg_itens_op'],
    'sch_linhas' => ['sch_quantidade', 'sch_produzido_estimado'],
    'prd_produtos' => ['prd_estoque'],
];

foreach ($tabelas as $tabela => $colunas) {
    $encontrou = false;
    
    foreach ($colunas as $coluna) {
        $query = "SELECT * FROM $tabela WHERE $coluna = 3734 OR CAST($coluna AS CHAR) LIKE '%3734%'";
        
        try {
            $stmt = $db->query($query);
            $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (count($resultados) > 0) {
                if (!$encontrou) {
                    echo "\n✓ Encontrado em $tabela:\n";
                    $encontrou = true;
                }
                foreach ($resultados as $row) {
                    echo "  Coluna: $coluna = " . $row[$coluna] . "\n";
                    print_r($row);
                }
            }
        } catch (Exception $e) {
            // Ignorar erros
        }
    }
    
    if (!$encontrou) {
        echo "❌ Não encontrado em $tabela\n";
    }
}

// 2️⃣ CODI - Buscar OP 201055 especificamente
echo "\n\n2️⃣ CODI API:\n";
echo str_repeat("-", 70) . "\n";

$endpoints_codi = [
    'ordemProducao' => '/action/ger/webservice/rest/ordemProducao?ordem=201055',
    'relatorioEvento' => '/action/ger/webservice/rest/relatorioEvento?ordem=201055&pageSize=100',
    'relatorioEventoConsolidado' => '/action/ger/webservice/rest/relatorioEventoConsolidado?ordem=201055&pageSize=100',
];

foreach ($endpoints_codi as $nome => $endpoint) {
    echo "\nTestando: $nome\n";
    
    $url = $codi_url . $endpoint;
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_USERPWD, "$codi_user:$codi_pass");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    echo "HTTP: $http_code\n";
    
    if ($http_code == 200) {
        // Procurar por 3734 na resposta
        if (strpos($response, '3734') !== false) {
            echo "✅ ENCONTRADO 3734 nesta resposta!\n";
            echo "Extrato:\n";
            
            // Tentar extrair JSON para mostrar melhor
            $data = json_decode($response, true);
            if ($data) {
                echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
            } else {
                echo substr($response, max(0, strpos($response, '3734') - 100), 300) . "\n";
            }
        } else {
            echo "❌ 3734 não encontrado\n";
        }
    }
}

echo "\n\n" . str_repeat("=", 70) . "\n";
echo "✌️ BÚSCA CONCLUÍDA\n";

?>
