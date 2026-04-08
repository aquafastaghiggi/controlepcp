<?php
/**
 * Teste dos endpoints REAIS disponíveis para encontrar quantidade de boas produzidas
 * Vamos testar: relatorioEvento e relatorioEventoConsolidado
 */

$codi_url = 'http://192.168.8.246:8080';
$codi_user = 'Aghiggi';
$codi_pass = '@Ag0351@';

$endpoints_teste = [
    'relatorioEvento' => '/action/ger/webservice/rest/relatorioEvento?codOrdemProducao=0201055',
    'relatorioEventoConsolidado' => '/action/ger/webservice/rest/relatorioEventoConsolidado?ordem=0201055',
    'estadoAtualRecurso' => '/action/ger/webservice/rest/relatorioEstadoAtualRecurso',
];

foreach ($endpoints_teste as $nome => $endpoint) {
    echo "\n" . str_repeat("=", 60) . "\n";
    echo "TESTANDO: $nome\n";
    echo "URL: " . $codi_url . $endpoint . "\n";
    echo str_repeat("=", 60) . "\n";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $codi_url . $endpoint);
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
        
        if (json_last_error() === JSON_ERROR_NONE && isset($data['data'])) {
            if (is_array($data['data']) && count($data['data']) > 0) {
                echo "✓ Encontrado! Campos do primeiro registro:\n\n";
                $primeiro = $data['data'][0];
                
                // Mostrar estrutura completa
                echo json_encode($primeiro, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
                
                // Procurar por campos de quantidade
                echo "\n📊 CAMPOS INTERESSANTES:\n";
                foreach ($primeiro as $campo => $valor) {
                    $campo_lower = strtolower($campo);
                    if (strpos($campo_lower, 'quant') !== false || 
                        strpos($campo_lower, 'boas') !== false || 
                        strpos($campo_lower, 'produção') !== false ||
                        strpos($campo_lower, 'somatório') !== false ||
                        strpos($campo_lower, 'realizado') !== false ||
                        strpos($campo_lower, 'executado') !== false) {
                        echo "  ➜ $campo: " . json_encode($valor) . "\n";
                    }
                }
            } else {
                echo "⚠ Sem dados para OP 201055\n";
            }
        } else {
            echo "✗ Erro ao parsear JSON ou sem dados\n";
            echo "Resposta: " . substr($response, 0, 300) . "\n";
        }
    }
}

?>
