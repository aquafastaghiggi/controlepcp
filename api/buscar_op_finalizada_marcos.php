<?php
// Verificar qual ordem está com status FINALIZADO

echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║        PROCURANDO ORDENS COM STATUS FINALIZADO                ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n\n";

$user = 'marcos.brun';
$pass = 'Eb035611!';

// Buscar a ordem com status FINALIZADO
$url = "http://192.168.8.246:8080/action/ger/webservice/rest/ordemProducao?status=FINALIZADO&pageSize=100";

echo "URL: $url\n";
echo "Usuário: $user\n\n";

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
curl_setopt($ch, CURLOPT_USERPWD, "$user:$pass");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "Status HTTP: $httpCode\n\n";

if ($httpCode == 200) {
    $response_utf8 = iconv('UTF-8', 'UTF-8//IGNORE', $response);
    $data = json_decode($response_utf8, true);
    
    if ($data && isset($data['totalCount'])) {
        echo "Total de ordens FINALIZADAS encontradas: " . $data['totalCount'] . "\n\n";
        
        if ($data['totalCount'] > 0 && isset($data['data']) && is_array($data['data'])) {
            foreach ($data['data'] as $i => $ordem) {
                echo "═══════════════════════════════════════════════════════════════\n";
                echo "ORDEM " . ($i + 1) . ":\n";
                echo "═══════════════════════════════════════════════════════════════\n";
                
                echo "Código CODI: " . ($ordem['codigoOrdemProducao'] ?? 'N/A') . "\n";
                echo "Número: " . ($ordem['ordem'] ?? 'N/A') . "\n";
                echo "Status: " . ($ordem['status'] ?? 'N/A') . "\n";
                echo "Produto: " . ($ordem['item']['nomeItem'] ?? 'N/A') . "\n";
                echo "Quantidade: " . ($ordem['quantidade'] ?? 'N/A') . " " . 
                     ($ordem['item']['unidadeMedida']['nomeUnidadeMedida'] ?? '') . "\n";
                echo "Última Alteração: " . ($ordem['ultimaAlteracao'] ?? 'N/A') . "\n";
                
                // Verificar se é a OP 201055
                if (($ordem['numero'] ?? null) == '0201055' || ($ordem['codigoOrdemProducao'] ?? null) == 23599) {
                    echo "\n✅ ESTA É A OP 201055!\n";
                }
                
                echo "\n";
            }
        }
    } else {
        echo "Falha ao decodificar resposta\n";
    }
} else {
    echo "ERRO HTTP $httpCode\n";
}

// Agora procurar especificamente pela 201055 listando tudo
echo "\n\n";
echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║     PROCURANDO OP 201055 EM TODAS AS ORDENS (paginado)       ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n\n";

$encontrada = false;

for ($page = 1; $page <= 5; $page++) {
    echo "Página $page...\n";
    
    $url = "http://192.168.8.246:8080/action/ger/webservice/rest/ordemProducao?pageSize=1000&page=$page";
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
    curl_setopt($ch, CURLOPT_USERPWD, "$user:$pass");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode != 200) {
        echo "  Erro HTTP $httpCode\n";
        break;
    }
    
    $response_utf8 = iconv('UTF-8', 'UTF-8//IGNORE', $response);
    $data = json_decode($response_utf8, true);
    
    if ($data && isset($data['data'])) {
        // Procurar pela 201055
        foreach ($data['data'] as $ordem) {
            if (($ordem['ordem'] ?? null) == '0201055' || 
                ($ordem['ordem'] ?? null) == '201055' ||
                (isset($ordem['codigoOrdemProducao']) && $ordem['codigoOrdemProducao'] == 23599)) {
                
                echo "\n✅ ENCONTRADA NA PÁGINA $page!\n";
                echo "═══════════════════════════════════════════════════════════════\n";
                echo "Código: " . ($ordem['codigoOrdemProducao'] ?? 'N/A') . "\n";
                echo "Número: " . ($ordem['ordem'] ?? 'N/A') . "\n";
                echo "Status: " . ($ordem['status'] ?? 'N/A') . "\n";
                echo "Quantidade: " . ($ordem['quantidade'] ?? 'N/A') . "\n";
                echo "Última Alteração: " . ($ordem['ultimaAlteracao'] ?? 'N/A') . "\n";
                echo "═══════════════════════════════════════════════════════════════\n";
                
                $encontrada = true;
                break;
            }
        }
        
        if ($encontrada) break;
    }
}

if (!$encontrada) {
    echo "\n⚠️  OP 201055 não encontrada em paginação\n";
}

?>
