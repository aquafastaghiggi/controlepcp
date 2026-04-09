<?php
// Ler arquivo de eventos salvo anteriormente e extrair informações

$arquivo = 'eventos_201055.json';

if (!file_exists($arquivo)) {
    echo "Arquivo $arquivo não encontrado\n";
    exit;
}

echo "=== ANALISANDO EVENTOS DO ARQUIVO ===\n\n";

$conteudo = file_get_contents($arquivo);

// Decodificar com UTF-8 force
$data = json_decode($conteudo, true, 512, JSON_BIGINT_AS_STRING);

if ($data === null) {
    echo "Erro ao decodificar JSON: " . json_last_error_msg() . "\n";

    // Tentar alternativa
    $conteudo_utf8 = iconv('UTF-8', 'UTF-8//IGNORE', $conteudo);
    $data = json_decode($conteudo_utf8, true);

    if ($data === null) {
        echo "Falhou na segunda tentativa também\n";
        exit;
    }
}

echo "✓ Arquivo decodificado com sucesso\n";
echo "Total de eventos: " . ($data['totalCount'] ?? 'N/A') . "\n";
echo "Eventos nesta página: " . count($data['data'] ?? []) . "\n\n";

if (isset($data['data']) && is_array($data['data'])) {
    // Procurar eventos da OP 201055 (código 0201055)
    $eventos_201055 = [];

    foreach ($data['data'] as $evento) {
        if (isset($evento['ordens']) && is_array($evento['ordens'])) {
            foreach ($evento['ordens'] as $ordem) {
                if (isset($ordem['ordemProducao']['ordem']) && $ordem['ordemProducao']['ordem'] == '07030005') {
                    // Verificar código
                    // Só a ordem está com código 07030005 (produto), não é a OP 201055
                    // Procurar por "0201055"
                }
                if (isset($ordem['ordemProducao']['ordem']) && strpos($ordem['ordemProducao']['ordem'], '201055') !== false) {
                    $eventos_201055[] = $evento;
                }
            }
        }
    }

    echo "Procurando eventos específicos...\n\n";

    // Procurar na ordem qualificada diretamente dentro dos eventos
    $matching_events = [];
    foreach ($data['data'] as $i => $evento) {
        if (isset($evento['ordens']) && is_array($evento['ordens'])) {
            foreach ($evento['ordens'] as $ordem) {
                // Procurar por qualquer referência a 201055
                if (isset($ordem['ordemProducao']) && is_array($ordem['ordemProducao'])) {
                    $ordem_str = json_encode($ordem['ordemProducao']);
                    if (strpos($ordem_str, '201055') !== false || strpos($ordem_str, '23599') !== false) {
                        $matching_events[] = ['index' => $i, 'evento' => $evento, 'ordem' => $ordem];
                    }
                }
            }
        }
    }

    echo "Total de eventos que mencionam 201055 ou 23599: " . count($matching_events) . "\n\n";

    // Se encontrou, exibir os últimos 5 eventos
    if (count($matching_events) > 0) {
        echo "ÚLTIMOS 5 EVENTOS COM A ORDEM 201055:\n\n";

        $ultimos = array_slice($matching_events, -5);

        foreach (array_reverse($ultimos) as $item) {
            $evento = $item['evento'];
            $ordem = $item['ordem'];

            echo "---\n";
            echo "Evento: " . ($evento['codigoEvento'] ?? 'N/A') . "\n";
            echo "Estado: " . ($evento['estado'] ?? 'N/A') . "\n";
            echo "Início: " . ($evento['inicio'] ?? 'N/A') . "\n";
            echo "Fim: " . ($evento['fim'] ?? 'N/A') . "\n";
            echo "Máquina: " . (isset($evento['grandeza']['recurso']['nomeRecurso']) ? $evento['grandeza']['recurso']['nomeRecurso'] : 'N/A') . "\n";
            echo "Status Operação: " . ($ordem['statusOperacao'] ?? 'N/A') . "\n";
            echo "Qtde Produzida: " . ($ordem['quantidadeProduzidaItem'] ?? 'N/A') . " itens\n";
            echo "\n";
        }

        // Análise semanal
        echo "\n=== ANÁLISE DE FINALIZAÇÃO ===\n\n";

        $estados = array_map(fn($i) => $i['evento']['estado'], $matching_events);
        echo "Estados encontrados: " . implode(', ', array_unique($estados)) . "\n";

        $produzidos_total = 0;
        foreach ($matching_events as $item) {
            if (isset($item['ordem']['quantidadeProduzidaItem'])) {
                $produzidos_total += $item['ordem']['quantidadeProduzidaItem'];
            }
        }

        echo "Total produzido (soma dos eventos): " . $produzidos_total . " unidades\n";
        echo "Quantidade planejada: 5000 CX\n";

        // Verificar se há eventos com estado PARADA nos últimos
        $tem_parada = count(array_filter($matching_events, fn($i) => $i['evento']['estado'] === 'PARADA')) > 0;
        echo "Tem eventos com estado PARADA: " . ($tem_parada ? 'SIM' : 'NÃO') . "\n";
    } else {
        echo "✗ Nenhum evento encontrado para a OP 201055 neste arquivo\n";
        echo "\nEventos disponíveis no arquivo:\n";

        // Listar as primeiras ordens
        $ordens_encontradas = [];
        foreach ($data['data'] as $evento) {
            if (isset($evento['ordens']) && count($evento['ordens']) > 0) {
                $ordem = $evento['ordens'][0];
                if (isset($ordem['ordemProducao']['ordem'])) {
                    $ordens_encontradas[] = $ordem['ordemProducao']['ordem'];
                }
            }
        }

        $ordens_unicas = array_unique($ordens_encontradas);
        echo implode(', ', array_slice($ordens_unicas, 0, 10)) . "...\n";
    }
}

?>
