<?php
/**
 * Teste direto com API CODI
 */

$codi_user = 'integrador';
$codi_pass = 'integrador123';
$codi_url = 'http://192.168.8.123:8080/action/ger/webservice/rest/ordemProducao';

echo "=== TESTE: Conectando com API CODI ===\n";
echo "URL: $codi_url\n";
echo "User: $codi_user\n\n";

$auth = base64_encode("$codi_user:$codi_pass");

$context = stream_context_create([
    'http' => [
        'method' => 'GET',
        'header' => "Authorization: Basic $auth\r\n",
        'timeout' => 10
    ]
]);

try {
    echo "Enviando requisição...\n";
    $url = "$codi_url?page=1&pageSize=100";
    
    $response = @file_get_contents($url, false, $context);
    
    if ($response === false) {
        echo "❌ Erro ao conectar\n";
        echo "Verifique:\n";
        echo "1. Se CODI está rodando\n";
        echo "2. Se URL está correta: $codi_url\n";
        echo "3. Se credenciais estão corretas\n";
        exit;
    }
    
    echo "✓ Connexão OK\n\n";
    
    $data = json_decode($response, true);
    
    echo "Total de OPs: {$data['totalCount']}\n";
    echo "Página: {$data['currentPage']}\n\n";
    
    // Procurar OP 201055
    echo "=== PROCURANDO OP 201055 ===\n";
    $found = false;
    
    if (isset($data['data']) && is_array($data['data'])) {
        foreach ($data['data'] as $order) {
            if ($order['ordem'] == '201055') {
                echo "✓ ENCONTRADO!\n";
                echo json_encode($order, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
                $found = true;
                break;
            }
        }
    }
    
    if (!$found) {
        echo "❌ OP 201055 não encontrada nesta página\n";
        echo "\n=== Primeiras 5 OPs disponíveis: ===\n";
        if (isset($data['data'])) {
            foreach (array_slice($data['data'], 0, 5) as $order) {
                echo "OP: {$order['ordem']} | Status: {$order['status']} | Qtd: {$order['quantidade']}\n";
            }
        }
    }
    
} catch (Exception $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
}
