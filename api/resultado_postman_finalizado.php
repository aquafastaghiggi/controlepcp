<?php
// Simular o resultado que do Postman para buscar finalização OP 201055

echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║         RESULTADO DO POSTMAN - OP 201055 (Simulado)          ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n\n";

$auth = 'Aghiggi:@Ag0351@';

// REQUEST 1: Status geral
echo "REQUEST 1: Verificar Status Geral\n";
echo "─────────────────────────────────────────────────────────────────\n";
echo "GET http://192.168.8.246:8080/action/ger/webservice/rest/ordemProducao/23599\n";
echo "Auth: Basic Auth (Aghiggi:@Ag0351@)\n\n";

$ch = curl_init('http://192.168.8.246:8080/action/ger/webservice/rest/ordemProducao/23599');
curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
curl_setopt($ch, CURLOPT_USERPWD, $auth);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode == 200) {
    $data = json_decode($response, true);
    
    echo "RESPONSE (HTTP $httpCode):\n";
    echo "{\n";
    echo '  "codigoOrdemProducao": ' . $data['codigoOrdemProducao'] . ",\n";
    echo '  "ordem": "' . $data['ordem'] . '",\n';
    echo '  "status": "' . $data['status'] . '",  ← PROCURE AQUI\n';
    echo '  "quantidade": ' . $data['quantidade'] . ",\n";
    echo '  "item": {\n';
    echo '    "codItem": "' . $data['item']['codItem'] . '",\n';
    echo '    "nomeItem": "' . $data['item']['nomeItem'] . '"\n';
    echo '  },\n';
    echo '  "ultimaAlteracao": "' . $data['ultimaAlteracao'] . '"\n';
    echo "}\n\n";
    
    echo "ANÁLISE:\n";
    echo "────────\n";
    
    $status = $data['status'];
    $data_alteracao = $data['ultimaAlteracao'];
    
    if ($status === 'INICIADO') {
        echo "❌ Status = \"INICIADO\"\n";
        echo "   Isto significa: A ordem ainda está em status de \"iniciada\" no CODI.\n";
        echo "   Última atualização: $data_alteracao\n";
        echo "   Conclusão: Segundo o API, ainda NÃO finalizou oficialmente.\n\n";
        
        echo "   PORÉM: Se você vê \"finalizado\" na web, pode ser:\n";
        echo "   • Status foi atualizado na interface mas não refletiu na API\n";
        echo "   • Precisa verificar se há eventos PARADA mais recentes\n\n";
    } else if (in_array($status, ['FINALIZADO', 'CONCLUIDO', 'PARADO', 'PARADA'])) {
        echo "✅ Status = \"$status\"\n";
        echo "   Isto significa: A ordem FOI FINALIZADA.\n";
        echo "   Data: $data_alteracao\n";
    } else {
        echo "⚠️  Status = \"$status\"\n";
        echo "   Desconhecido - contate suporte.\n";
    }
}

echo "\n\n";

// REQUEST 2: Buscar eventos
echo "REQUEST 2: Ver Histórico de Eventos (Mais detalhado)\n";
echo "─────────────────────────────────────────────────────────────────\n";
echo "GET http://192.168.8.246:8080/action/ger/webservice/rest/relatorioEvento\n";
echo "    ?codOrdemProducao=23599&pageSize=100&page=1\n";
echo "Auth: Basic Auth\n\n";

$ch = curl_init('http://192.168.8.246:8080/action/ger/webservice/rest/relatorioEvento?codOrdemProducao=23599&pageSize=100&page=1');
curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
curl_setopt($ch, CURLOPT_USERPWD, $auth);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "Status HTTP: $httpCode\n\n";

if ($httpCode == 200) {
    echo "✓ Resposta recebida com sucesso\n";
    echo "✓ Procure no Response JSON pelo array 'data'\n";
    echo "✓ Vá até o ÚLTIMO objeto do array\n";
    echo "✓ Procure pelo campo 'estado'\n\n";
    
    echo "Se ver:\n";
    echo "   'estado': 'PARADA' → Máquina parou (pode indicar conclusão)\n";
    echo "   'estado': 'PRODUCAO' → Ainda em produção\n";
    echo "   'fim': '2026-XX-XXTXX:XX:XX' → Quando parou\n\n";
} else {
    echo "❌ Erro na requisição\n";
}

echo "\n\n";
echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║                    RESUMO FINAL                               ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n\n";

echo "Status Atual (API): INICIADO\n";
echo "Última Atualização: 2026-03-20 10:51:18\n";
echo "\n";
echo "Para confirmar se REALMENTE finalizou:\n";
echo "1. Use REQUEST 2 acima (Eventos)\n";
echo "2. Procure pelo campo 'fim' mais recente\n";
echo "3. Se tiver data mais recente que 2026-03-20, houve evolução\n";
echo "\n";
echo "Se a web mostra 'finalizado' mas API mostra 'INICIADO':\n";
echo "→ Há discrepância entre os sistemas\n";
echo "→ Pode precisar sincronizar dados ou atualizar no CODI\n";

?>
