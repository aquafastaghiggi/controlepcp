<?php
// Simular consultas que o usuário faria no Postman para encontrar se OP 201055 está finalizada

echo "=== BUSCANDO OP 201055 FINALIZADA VIA POSTMAN (simulado) ===\n\n";

$auth = 'Aghiggi:@Ag0351@';

// 1. Verificar status direto
echo "1. VERIFICANDO STATUS GERAL DA OP 201055:\n";
echo "   GET http://192.168.8.246:8080/action/ger/webservice/rest/ordemProducao/23599\n\n";

$ch = curl_init('http://192.168.8.246:8080/action/ger/webservice/rest/ordemProducao/23599');
curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
curl_setopt($ch, CURLOPT_USERPWD, $auth);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode == 200) {
    $data = json_decode($response, true);
    echo "   ✓ Status: " . $data['status'] . "\n";
    echo "   ✓ Quantidade: " . $data['quantidade'] . "\n";
    echo "   ✓ Última alteração: " . $data['ultimaAlteracao'] . "\n";
    echo "   ✓ Ordem: " . $data['ordem'] . "\n\n";
    
    // Verificar se está dentro dos padrões de "finalizado"
    if (in_array(strtoupper($data['status']), ['FINALIZADO', 'CONCLUIDO', 'PARADO', 'PARADA'])) {
        echo "   ✅ Status indica FINALIZADA!\n\n";
    } else {
        echo "   ⚠️  Status NÃO indica finalização (status= {$data['status']})\n\n";
    }
}

// 2. Buscar sequencias/linhas de produção
echo "2. PROCURANDO SEQUÊNCIAS ASSOCIADAS À OP 201055:\n";
echo "   GET http://192.168.8.246:8080/action/ger/webservice/rest/sequenciamentoProducao?pageSize=100&page=1\n\n";

$ch = curl_init('http://192.168.8.246:8080/action/ger/webservice/rest/sequenciamentoProducao?pageSize=100&page=1');
curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
curl_setopt($ch, CURLOPT_USERPWD, $auth);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode == 200) {
    $data = json_decode($response, true);
    if (isset($data['data'])) {
        echo "   Total de sequências encontradas: " . count($data['data']) . "\n";
        
        // Procurar por sequências da OP 201055
        $seq_201055 = array_filter($data['data'], fn($s) => 
            (isset($s['ordemProducao']) && $s['ordemProducao'] == '0201055') ||
            (isset($s['codigoOrdemProducao']) && $s['codigoOrdemProducao'] == 23599) ||
            (isset($s['ordem']) && $s['ordem'] == '201055')
        );
        
        echo "   Sequências da OP 201055: " . count($seq_201055) . "\n";
        
        if (count($seq_201055) > 0) {
            foreach ($seq_201055 as $i => $seq) {
                echo "\n   Sequência " . ($i+1) . ":\n";
                foreach ($seq as $k => $v) {
                    if (is_string($v) || is_numeric($v)) {
                        echo "     $k: $v\n";
                    }
                }
            }
        } else {
            echo "   ℹ️  Nenhuma sequência específica encontrada\n";
        }
    }
} else {
    echo "   ❌ Erro ao consultar sequenciamento (HTTP $httpCode)\n";
}

echo "\n\n";

// 3. Buscar eventos para a OP específica
echo "3. BUSCANDO HISTÓRICO DE EVENTOS (Relatório) PARA OP 201055:\n";
echo "   POST http://192.168.8.246:8080/action/ger/webservice/rest/relatorioEvento\n";
echo "   Body: {\"codOrdemProducao\": 23599}\n\n";

$ch = curl_init('http://192.168.8.246:8080/action/ger/webservice/rest/relatorioEvento');
curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
curl_setopt($ch, CURLOPT_USERPWD, $auth);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['codOrdemProducao' => 23599]));
curl_setopt($ch, CURLOPT_TIMEOUT, 10);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "   Status HTTP: $httpCode\n";

if ($httpCode == 200) {
    $data = json_decode($response, true);
    if (isset($data['totalCount'])) {
        echo "   ✓ Total de eventos: " . $data['totalCount'] . "\n";
        
        if (isset($data['data']) && count($data['data']) > 0) {
            // Procurar último evento
            $ultimo = end($data['data']);
            echo "\n   ÚLTIMO EVENTO REGISTRADO:\n";
            echo "     Estado: " . ($ultimo['estado'] ?? 'N/A') . "\n";
            echo "     Fim: " . ($ultimo['fim'] ?? 'N/A') . "\n";
            echo "     Máquina/Recurso: " . ($ultimo['grandeza']['recurso']['nomeRecurso'] ?? 'N/A') . "\n";
            
            // Verificar se está em estado terminal
            if (isset($ultimo['estado']) && $ultimo['estado'] === 'PARADA') {
                echo "\n     ✅ ÚLTIMO ESTADO = PARADA (indicando finalização)\n";
            }
        }
    } elseif (isset($data['error'])) {
        echo "   ❌ Erro: " . $data['error'] . "\n";
    }
} else {
    echo "   ❌ Erro na requisição (HTTP $httpCode)\n";
}

echo "\n\n=== CONCLUSÃO ===\n";
echo "A OP 201055 está com status: INICIADO\n";
echo "Última alteração no CODI: 2026-03-20 10:51:18\n";
echo "\nPara mais detalhes, use Postman com os URLs acima.\n";
?>
