<?php

declare(strict_types=1);

/**
 * Popula automaticamente a matriz_setup da LN07 (codigo_recurso=22, linha_id=9).
 * Combina histórico real do CODI (150 dias) com regras de padrão por
 * família/litragem para os pares sem ocorrência.
 *
 * Uso: php scripts/matriz_ln07.php
 */

// =============================================================
// 1. CONFIGURAÇÃO
// =============================================================

$dryRun = false; // true = só imprime os SQLs, não executa nada no banco

$codigoRecurso = '22';
$linhaId       = 9;

$conn = new PDO('mysql:host=127.0.0.1;dbname=controlepcp_v2;charset=utf8', 'controlepcp_v2', 'CpcpV2!9Q2s');
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

const TAMANHO_LOTE = 200; // pares por INSERT, pra não estourar o limite de placeholders

// =============================================================
// 2. BUSCAR SKUs DA LN07
// =============================================================

$stmt = $conn->query("SELECT sku, descricao FROM produtos WHERE linha_id = {$linhaId} ORDER BY sku");
$produtos = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($produtos)) {
    fwrite(STDERR, "Nenhum produto encontrado para linha_id={$linhaId}. Abortando.\n");
    exit(1);
}

$skus = array_column($produtos, 'sku');

echo "=== LN07 (codigo_recurso={$codigoRecurso}, linha_id={$linhaId}) ===\n";
echo count($skus) . " produtos encontrados.\n\n";

// =============================================================
// 3. BUSCAR HISTÓRICO REAL DO CODI (150 dias)
// =============================================================

$sqlHistorico = "
    SELECT
        JSON_UNQUOTE(JSON_EXTRACT(ce_ant.dados_raw, '\$.ordens[0].ordemProducao.item.codItem')) as sku_origem,
        JSON_UNQUOTE(JSON_EXTRACT(ce_dep.dados_raw, '\$.ordens[0].ordemProducao.item.codItem')) as sku_destino,
        TIMESTAMPDIFF(MINUTE, ce_setup.inicio_evento, IFNULL(ce_setup.fim_evento, NOW())) as duracao_min
    FROM codi_eventos ce_setup
    LEFT JOIN codi_eventos ce_ant ON ce_ant.codigo_recurso = ce_setup.codigo_recurso
        AND ce_ant.tipo_evento = 'PRODUCAO'
        AND ce_ant.fim_evento = (
            SELECT MAX(fim_evento) FROM codi_eventos
            WHERE codigo_recurso = ce_setup.codigo_recurso
            AND tipo_evento = 'PRODUCAO'
            AND fim_evento <= ce_setup.inicio_evento
        )
    LEFT JOIN codi_eventos ce_dep ON ce_dep.codigo_recurso = ce_setup.codigo_recurso
        AND ce_dep.tipo_evento = 'PRODUCAO'
        AND ce_dep.inicio_evento = (
            SELECT MIN(inicio_evento) FROM codi_eventos
            WHERE codigo_recurso = ce_setup.codigo_recurso
            AND tipo_evento = 'PRODUCAO'
            AND inicio_evento >= ce_setup.fim_evento
        )
    WHERE ce_setup.codigo_recurso = :codigoRecurso
    AND ce_setup.tipo_evento = 'PARADA'
    AND UPPER(JSON_UNQUOTE(JSON_EXTRACT(ce_setup.dados_raw, '\$.parada.nomeParada'))) IN ('TROCA DE KIT','TROCA DE LIQUIDO')
    AND ce_setup.inicio_evento >= DATE_SUB(NOW(), INTERVAL 150 DAY)
    AND JSON_UNQUOTE(JSON_EXTRACT(ce_ant.dados_raw, '\$.ordens[0].ordemProducao.item.codItem')) IS NOT NULL
    AND JSON_UNQUOTE(JSON_EXTRACT(ce_dep.dados_raw, '\$.ordens[0].ordemProducao.item.codItem')) IS NOT NULL
    ORDER BY sku_origem, sku_destino
";

$stmt = $conn->prepare($sqlHistorico);
$stmt->execute(['codigoRecurso' => $codigoRecurso]);
$ocorrencias = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Agrupa duracoes por par (sku_origem, sku_destino)
$duracoesPorPar = [];
$paresForaDaLinha = 0;
$skusSet = array_flip($skus);

foreach ($ocorrencias as $row) {
    $origem  = $row['sku_origem'];
    $destino = $row['sku_destino'];

    if (! isset($skusSet[$origem]) || ! isset($skusSet[$destino])) {
        // Par envolve SKU que não pertence à lista oficial de produtos da LN07
        // (ex.: produto de outra linha que passou pelo mesmo recurso) — ignorado.
        $paresForaDaLinha++;
        continue;
    }

    $duracoesPorPar[$origem][$destino][] = (int) $row['duracao_min'];
}

// Média por par, ignorando ocorrências de 0 min (conforme especificado).
// Se só uma ocorrência, usa aquele valor. Se todas as ocorrências forem 0,
// mantém 0 (não há base pra descartar todas).
$historicoCodi = [];
foreach ($duracoesPorPar as $origem => $destinos) {
    foreach ($destinos as $destino => $duracoes) {
        $naoZero = array_filter($duracoes, fn ($d) => $d > 0);

        if (count($naoZero) > 0) {
            $media = (int) round(array_sum($naoZero) / count($naoZero));
        } else {
            $media = 0;
        }

        $historicoCodi[$origem][$destino] = $media;
    }
}

// Correções manuais: pares com valor zerado ou abaixo de 5 min revisados
// manualmente (revisão manual pós dry-run).
$overrides = [
    ['20150057', '20150055', 50],
    ['20150069', '20150059', 10],
    ['20150011', '20150074', 10],
    ['20150059', '20150075', 10],
];
foreach ($overrides as [$orig, $dest, $min]) {
    $historicoCodi[$orig][$dest] = $min;
}

echo count($duracoesPorPar) . " SKUs de origem com histórico real no CODI. ";
echo "{$paresForaDaLinha} ocorrência(s) ignorada(s) por envolver SKU fora da lista oficial da LN07.\n\n";

// =============================================================
// 4. REGRAS DE PADRÃO (família + litragem)
// =============================================================

function classificarFamilia(string $sku): string
{
    return match (true) {
        str_starts_with($sku, '20040') => 'Amaciante',
        str_starts_with($sku, '20050') => 'Desinfetante',
        str_starts_with($sku, '20060') => 'Limpador Perfumado',
        str_starts_with($sku, '20070') => 'Limpa Vidros',
        str_starts_with($sku, '20090') => 'Desengordurante',
        str_starts_with($sku, '20110') => 'Multi Uso',
        str_starts_with($sku, '20200') => 'Lava Roupas',
        str_starts_with($sku, '20220') => 'Aromatizante',
        default => 'Desconhecida',
    };
}

function extrairLitragem(string $descricao): ?float
{
    // Descrições terminam consistentemente em "X 2l", "X 1l" etc.
    if (preg_match('/(\d+(?:[.,]\d+)?)\s*l\s*$/i', trim($descricao), $m)) {
        return (float) str_replace(',', '.', $m[1]);
    }
    return null;
}

function duracaoPorRegra(string $familiaOrigem, ?float $litragemOrigem, string $familiaDestino, ?float $litragemDestino): int
{
    $mesmaFamilia  = $familiaOrigem === $familiaDestino;
    // Litragem desconhecida em qualquer lado é tratada como "diferente" (conservador)
    $mesmaLitragem = $litragemOrigem !== null && $litragemDestino !== null && $litragemOrigem === $litragemDestino;

    return match (true) {
        $mesmaFamilia && $mesmaLitragem   => 10,
        $mesmaFamilia && ! $mesmaLitragem => 50,
        ! $mesmaFamilia && $mesmaLitragem => 20,
        default                           => 50,
    };
}

$classificacao = [];
foreach ($produtos as $p) {
    $classificacao[$p['sku']] = [
        'familia'   => classificarFamilia($p['sku']),
        'litragem'  => extrairLitragem($p['descricao']),
        'descricao' => $p['descricao'],
    ];
}

$semLitragemDetectada = array_filter($classificacao, fn ($c) => $c['litragem'] === null);
if (! empty($semLitragemDetectada)) {
    echo "AVISO: " . count($semLitragemDetectada) . " produto(s) sem litragem detectável na descrição (tratados como litragem diferente por padrão):\n";
    foreach ($semLitragemDetectada as $sku => $c) {
        echo "  - {$sku}: {$c['descricao']}\n";
    }
    echo "\n";
}

// =============================================================
// 5. MONTAR PARES E INSERIR
// =============================================================

$pares       = [];
$totalDoCodi = 0;
$totalDaRegra = 0;
$paresDoCodiParaRevisao = [];

foreach ($skus as $origem) {
    foreach ($skus as $destino) {
        if ($origem === $destino) {
            continue;
        }

        if (isset($historicoCodi[$origem][$destino])) {
            $minutos = $historicoCodi[$origem][$destino];
            $fonte   = 'CODI';
            $totalDoCodi++;
            $paresDoCodiParaRevisao[] = [$origem, $destino, $minutos];
        } else {
            $minutos = duracaoPorRegra(
                $classificacao[$origem]['familia'],
                $classificacao[$origem]['litragem'],
                $classificacao[$destino]['familia'],
                $classificacao[$destino]['litragem']
            );
            $fonte = 'REGRA';
            $totalDaRegra++;
        }

        $pares[] = [$origem, $destino, $minutos, $fonte];
    }
}

$totalPares = count($pares);

echo "=== RESUMO ===\n";
echo "Total de pares gerados: {$totalPares}\n";
echo "  - Do histórico real do CODI: {$totalDoCodi}\n";
echo "  - Por regra de padrão (família/litragem): {$totalDaRegra}\n\n";

echo "=== PARES DO CODI PARA REVISÃO ({$totalDoCodi}) ===\n";
foreach ($paresDoCodiParaRevisao as [$o, $d, $min]) {
    $nomeO = $classificacao[$o]['descricao'];
    $nomeD = $classificacao[$d]['descricao'];
    echo "  {$o} ({$nomeO}) -> {$d} ({$nomeD}): {$min} min\n";
}
echo "\n";

// =============================================================
// Gera os INSERTs em lotes
// =============================================================

$lotes = array_chunk($pares, TAMANHO_LOTE);
$totalInseridosOuAtualizados = 0;

foreach ($lotes as $indiceLote => $lote) {
    $placeholders = [];
    $valores      = [];

    foreach ($lote as [$origem, $destino, $minutos, $fonte]) {
        $placeholders[] = '(?, ?, ?, ?, NOW(), NOW())';
        $valores[] = $origem;
        $valores[] = $destino;
        $valores[] = $minutos;
        $valores[] = $linhaId;
    }

    $sqlInsert = "INSERT INTO matriz_setup (sku_origem, sku_destino, duracao_minutos, linha_id, created_at, updated_at) VALUES\n"
        . implode(",\n", $placeholders)
        . "\nON DUPLICATE KEY UPDATE duracao_minutos = VALUES(duracao_minutos), linha_id = VALUES(linha_id), updated_at = NOW();";

    if ($dryRun) {
        echo "--- LOTE " . ($indiceLote + 1) . "/" . count($lotes) . " (" . count($lote) . " pares) [DRY RUN] ---\n";
        echo $sqlInsert . "\n";
        echo "-- Bind values: " . json_encode($valores) . "\n\n";
    } else {
        $stmtInsert = $conn->prepare($sqlInsert);
        $stmtInsert->execute($valores);
        $totalInseridosOuAtualizados += $stmtInsert->rowCount();
        echo "Lote " . ($indiceLote + 1) . "/" . count($lotes) . " executado (" . count($lote) . " pares).\n";
    }
}

// =============================================================
// UPDATE de garantia do linha_id (redundante com o ON DUPLICATE KEY
// UPDATE acima, mas mantido como segunda camada de segurança —
// lição aprendida na LN02: nunca confiar só no ON DUPLICATE KEY
// pra corrigir linha_id de pares pré-existentes).
// =============================================================

$listaSkus = implode(',', array_fill(0, count($skus), '?'));
$sqlUpdateLinha = "
    UPDATE matriz_setup
    SET linha_id = ?, updated_at = NOW()
    WHERE sku_origem IN ({$listaSkus})
    AND sku_destino IN ({$listaSkus})
";

if ($dryRun) {
    echo "--- UPDATE DE GARANTIA DO linha_id [DRY RUN] ---\n";
    echo $sqlUpdateLinha . "\n";
    echo "-- Bind values: linha_id={$linhaId}, skus=" . json_encode($skus) . "\n\n";
} else {
    $stmtUpdate = $conn->prepare($sqlUpdateLinha);
    $stmtUpdate->execute(array_merge([$linhaId], $skus, $skus));
    echo "UPDATE de garantia do linha_id executado: {$stmtUpdate->rowCount()} linha(s) afetada(s).\n\n";
}

// =============================================================
// 6. LOG FINAL
// =============================================================

echo "=== LOG FINAL ===\n";
echo "Modo: " . ($dryRun ? 'DRY RUN (nada foi executado no banco)' : 'EXECUÇÃO REAL') . "\n";
echo "Total de pares gerados: {$totalPares}\n";
echo "  - Do histórico real do CODI: {$totalDoCodi}\n";
echo "  - Por regra de padrão: {$totalDaRegra}\n";
if (! $dryRun) {
    echo "Total de linhas inseridas/atualizadas (INSERT): {$totalInseridosOuAtualizados}\n";
}
echo "Pares ignorados por envolver SKU fora da lista oficial da LN07: {$paresForaDaLinha}\n";
