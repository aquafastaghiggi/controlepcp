<?php
/**
 * Buscar apontamentos de produção registrados para OP 201055
 * Cada apontamento é um registro de produção (com quantidade de boas, rejeitos, etc)
 */

$codi_url = 'http://192.168.8.246:8080';
$codi_user = 'Aghiggi';
$codi_pass = '@Ag0351@';

// Tentar diferentes parâmetros para filtrar por OP 201055
$tentativas = [
    'Teste 1: ordem' => '?ordem=201055',
    'Teste 2: ordemProducao' => '?ordemProducao=201055',
    'Teste 3: codOrdem' => '?codOrdem=201055',
    'Teste 4: numOrdem' => '?numOrdem=0201055',
    'Teste 5: Sem filtro (página 1)' => '?pagina=1&itensPorPagina=10',
];

foreach ($tentativas as $titulo => $params) {
    echo "\n" . str_repeat("-", 70) . "\n";
    echo "$titulo\n";
    echo str_repeat("-", 70) . "\n";
    
    $url = $codi_url . '/action/ger/webservice/rest/apontamentosProducao' . $params;
    echo "URL: $url\n\n";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_USERPWD, "$codi_user:$codi_pass");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    echo "HTTP Code: $http_code\n";
    
    if ($response) {
        $data = json_decode($response, true);
        
        if (json_last_error() === JSON_ERROR_NONE) {
            if (isset($data['data']) && is_array($data['data'])) {
                echo "✓ Encontrados " . count($data['data']) . " registros\n";
                
                if (count($data['data']) > 0) {
                    $primeiro = $data['data'][0];
                    echo "\n📊 Estrutura do primeiro apontamento:\n";
                    echo json_encode($primeiro, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
                }
            } else {
                echo "Resposta: " . json_encode($data, JSON_PRETTY_PRINT) . "\n";
            }
        } else {
            echo "✗ JSON inválido\n";
            echo substr($response, 0, 400) . "\n";
        }
    }
}

?>
