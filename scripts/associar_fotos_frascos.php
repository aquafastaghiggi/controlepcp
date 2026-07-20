<?php

declare(strict_types=1);

/**
 * Script standalone (não faz parte do Laravel) para associar frascos.foto aos
 * arquivos PNG disponíveis em public/Frascos sem rótulo/.
 *
 * Com $dryRun = true (padrão): só lê o banco e o disco, imprime as sugestões
 * e os UPDATE statements, mas não executa nada.
 * Com $dryRun = false: executa de fato os UPDATEs dos SKUs classificados como
 * AUTOMATICO (matching único ou override manual), dentro de uma transação.
 *
 * Uso: php scripts/associar_fotos_frascos.php
 */

$dryRun = false;

$pdo = new PDO('mysql:host=127.0.0.1;dbname=controlepcp_v2;charset=utf8mb4', 'root', 'k7m2y9u4');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$pastaFotos = __DIR__ . '/../public/Frascos sem rótulo';

// Limiar mínimo de similaridade (0-100) pra um PNG ser considerado candidato.
const LIMIAR_SIMILARIDADE = 55.0;

// Overrides manuais — casos revisados onde o 1º candidato do matching
// automático é claramente correto. Força o resultado como AUTOMATICO com a
// foto especificada, ignorando o resultado do matching automático pra esse SKU.
$overrides = [
    '07030004' => 'Água Sanitária Aquafast 1L.png',
    '07030005' => 'Água Sanitária Aquafast 2L.png',
    '07030006' => 'Água Sanitária Aquafast 5L.png',
    '07030008' => 'Alvejante Sem Cloro Aquafast 2L.png',
    '07030016' => 'Frasco Desinfetante 500ml.png',
    '07030021' => 'Frasco Limpa Vidros Recarga e Pulverizador.png',
    '07030023' => 'Desengordurante Aquafast Recarga 500ml.png',
    '07030039' => 'Frasco Lava roupas 1L.png',
    '07030041' => 'Frasco Limpa Vidros Squeeze.png',
    '07030066' => 'Amaciante Concentrado 1L.png',
    '07030068' => 'Alvejante Sem Cloro Aquafast 3L 2020.png',
    '07030069' => 'Lava Roupas Aquafast Azul 3L 2020.png',
    '07030074' => 'Desengordurante Aquafast Squeeze 500ml.png',
    '07030076' => 'Frasco multiuso vermelho 500ml.png',
    '07030077' => 'Frasco multiuso preto 500ml.png',
    '07030078' => 'Frasco multiuso roxo 500ml.png',
    '07030081' => 'Frasco Desinfetante 2L.png',
    '07030083' => 'Frasco Amaciante Concentrado 1,5L.png',
    '07030088' => 'Amaciante Aquafast Vermelho 5L 2020.png',
    '07030089' => 'Amaciante Aquafast Roxo 5L 2020.png',
    '07030092' => 'Amaciante Vermelho Sedução 2L.png',
    '07030093' => 'Amaciante Roxo Magia 2L.png',
    '07030100' => 'Frasco multiuso azul 1L.png',
    '07030101' => 'Frasco multiuso preto 1L.png',
    '07030102' => 'Frasco multiuso vermelho 1L.png',
    '07030107' => 'Desinfetante Aquafast 1L 2023.png',
    '07030110' => 'Multiuso Pulverizador 1L 2026.png',
    '07030014' => 'Detergente 500ml frasco vazio.png',
    '07030038' => 'Frasco Limpador Perfumado e Amaciante Concentrado 500ml.png',
    '07030063' => 'Frasco Limpador Perfumado e Amaciante Concentrado 1L.png',
    '07030070' => 'Lava Roupas Aquafast branco 3L 2020.png',
    '07030073' => 'Frasco multiuso azul 500ml.png',
    '07030086' => 'Lava Roupas Ternura 5L 2020.png',
    '07030091' => 'Eliminador de Odores Floral 2L.png',
    '07030094' => 'Amaciante Energia 2L.png',
    '07030095' => 'Amaciante_Lava Roupas 2L frasco natural.png',
    '07030098' => 'Frasco branco 2L.png',
    '07030104' => 'Multiuso Aquafast Lavanda com Álcool 1L 2022.png',
    '07030105' => 'Lava Roupas Aquafast Glicerina 3L 2023.png',
    '07030106' => 'Amaciante Concentrado Branco Ternura 3L.png',
    '07030111' => 'Amaciante Energia 2L.png',
    '07030112' => 'Amaciante Branco 5L 2020.png',
];

/**
 * Normaliza um texto (descrição de frasco ou nome de arquivo) pra comparação:
 * lowercase, sem acento, decimal com vírgula -> ponto, sem palavras de ruído
 * ("frasco", "aquafast" e a variante com erro de digitação "aquafat"), sem
 * pontuação, espaços colapsados.
 */
function normalizar(string $texto): string
{
    $texto = preg_replace('/\.png$/i', '', $texto) ?? $texto;
    $texto = mb_strtolower($texto, 'UTF-8');

    // Decimal com vírgula (ex.: "1,5l") -> ponto, antes de mexer em pontuação
    $texto = preg_replace('/(\d),(\d)/', '$1.$2', $texto) ?? $texto;

    // Remove acentos via tabela explícita (mais previsível entre ambientes que iconv//TRANSLIT)
    $comAcento = ['á','à','â','ã','ä','é','è','ê','ë','í','ì','î','ï','ó','ò','ô','õ','ö','ú','ù','û','ü','ç','ñ'];
    $semAcento = ['a','a','a','a','a','e','e','e','e','i','i','i','i','o','o','o','o','o','u','u','u','u','c','n'];
    $texto = str_replace($comAcento, $semAcento, $texto);

    // Palavras de ruído que aparecem em um lado (banco ou arquivo) mas não
    // ajudam a diferenciar produtos
    $texto = str_replace(['frasco', 'aquafast', 'aquafat'], ' ', $texto);

    // Remove pontuação (mantém letras, números, ponto decimal já normalizado, espaço)
    $texto = preg_replace('/[^a-z0-9. ]+/', ' ', $texto) ?? $texto;
    $texto = preg_replace('/\s+/', ' ', $texto) ?? $texto;

    return trim($texto);
}

/**
 * Extrai o token de volume (ex.: "2l", "500ml", "1.5l") de um texto já normalizado.
 * Retorna null se não encontrar nenhum volume reconhecível.
 */
function extrairVolume(string $normalizado): ?string
{
    if (preg_match('/(\d+(?:\.\d+)?)\s*(ml|l)\b/', $normalizado, $m)) {
        return $m[1] . $m[2];
    }
    return null;
}

// 1. Carregar frascos
$frascos = $pdo->query('SELECT id, sku, descricao FROM frascos ORDER BY sku')->fetchAll(PDO::FETCH_ASSOC);

// 2. Carregar PNGs disponíveis
$arquivosPng = glob($pastaFotos . '/*.png') ?: [];
$nomesPng = array_map('basename', $arquivosPng);

if (empty($nomesPng)) {
    fwrite(STDERR, "Nenhum PNG encontrado em: {$pastaFotos}\n");
    exit(1);
}

$pngNormalizados = [];
foreach ($nomesPng as $nome) {
    $pngNormalizados[$nome] = normalizar($nome);
}

echo "Frascos cadastrados: " . count($frascos) . PHP_EOL;
echo "PNGs disponíveis: " . count($nomesPng) . PHP_EOL;
echo str_repeat('=', 130) . PHP_EOL;
printf("%-10s | %-52s | %-45s | %s\n", 'SKU', 'Descrição', 'Foto sugerida', 'STATUS');
echo str_repeat('-', 130) . PHP_EOL;

$updates = [];
$paraExecutar = []; // sku => foto, só os AUTOMATICO com arquivo confirmado — usado na execução real
$resumo = ['AUTOMATICO' => 0, 'MANUAL' => 0, 'SEM_MATCH' => 0];
$manuais = [];
$semMatch = [];

foreach ($frascos as $frasco) {
    // Override manual — pula o matching automático inteiramente pra esse SKU.
    if (isset($overrides[$frasco['sku']])) {
        $fotoOverride = $overrides[$frasco['sku']];
        $existeArquivo = in_array($fotoOverride, $nomesPng, true);

        $status       = 'AUTOMATICO';
        $fotoSugerida = $fotoOverride . ($existeArquivo ? '' : ' [ARQUIVO NAO ENCONTRADO!]');
        $resumo['AUTOMATICO']++;

        if ($existeArquivo) {
            $updates[] = sprintf(
                "UPDATE frascos SET foto = %s WHERE sku = %s; -- override manual",
                $pdo->quote($fotoOverride),
                $pdo->quote($frasco['sku'])
            );
            $paraExecutar[$frasco['sku']] = $fotoOverride;
        } else {
            fwrite(STDERR, "AVISO: override do SKU {$frasco['sku']} aponta pra um arquivo que não existe: {$fotoOverride}\n");
        }

        printf("%-10s | %-52s | %-45s | %s\n", $frasco['sku'], $frasco['descricao'], $fotoSugerida, $status);
        continue;
    }

    $descNorm = normalizar($frasco['descricao']);
    $volDesc  = extrairVolume($descNorm);

    $candidatos = [];
    foreach ($pngNormalizados as $nomeArquivo => $nomeNorm) {
        $volArquivo = extrairVolume($nomeNorm);

        // Se os dois lados têm um volume detectável, ele precisa bater —
        // evita cruzar "500ml" com "5L" só por causa de palavras em comum.
        if ($volDesc !== null && $volArquivo !== null && $volDesc !== $volArquivo) {
            continue;
        }

        similar_text($descNorm, $nomeNorm, $percentual);
        if ($percentual >= LIMIAR_SIMILARIDADE) {
            $candidatos[] = ['arquivo' => $nomeArquivo, 'score' => round($percentual, 1)];
        }
    }

    usort($candidatos, fn ($a, $b) => $b['score'] <=> $a['score']);

    if (count($candidatos) === 1) {
        $status       = 'AUTOMATICO';
        $fotoSugerida = $candidatos[0]['arquivo'];
        $resumo['AUTOMATICO']++;
        $updates[] = sprintf(
            "UPDATE frascos SET foto = %s WHERE sku = %s; -- score %.1f%%",
            $pdo->quote($fotoSugerida),
            $pdo->quote($frasco['sku']),
            $candidatos[0]['score']
        );
        $paraExecutar[$frasco['sku']] = $fotoSugerida;
    } elseif (count($candidatos) > 1) {
        $status       = 'MANUAL';
        $fotoSugerida = implode(' | ', array_map(fn ($c) => "{$c['arquivo']} ({$c['score']}%)", $candidatos));
        $resumo['MANUAL']++;
        $manuais[] = ['sku' => $frasco['sku'], 'descricao' => $frasco['descricao'], 'candidatos' => $candidatos];
    } else {
        $status       = 'SEM_MATCH';
        $fotoSugerida = '-';
        $resumo['SEM_MATCH']++;
        $semMatch[] = ['sku' => $frasco['sku'], 'descricao' => $frasco['descricao']];
    }

    printf("%-10s | %-52s | %-45s | %s\n", $frasco['sku'], $frasco['descricao'], $fotoSugerida, $status);
}

echo str_repeat('=', 130) . PHP_EOL;
echo "Resumo: AUTOMATICO={$resumo['AUTOMATICO']}  MANUAL={$resumo['MANUAL']}  SEM_MATCH={$resumo['SEM_MATCH']}" . PHP_EOL;

if (!empty($manuais)) {
    echo PHP_EOL . "=== Detalhe dos MANUAL (múltiplos candidatos) ===" . PHP_EOL;
    foreach ($manuais as $m) {
        echo "{$m['sku']} | {$m['descricao']}" . PHP_EOL;
        foreach ($m['candidatos'] as $c) {
            echo "    - {$c['arquivo']} ({$c['score']}%)" . PHP_EOL;
        }
    }
}

if (!empty($semMatch)) {
    echo PHP_EOL . "=== SEM_MATCH (sem foto) ===" . PHP_EOL;
    foreach ($semMatch as $s) {
        echo "{$s['sku']} | {$s['descricao']}" . PHP_EOL;
    }
}

echo PHP_EOL . "=== UPDATE statements sugeridos (só os AUTOMATICO) ===" . PHP_EOL;
if (empty($updates)) {
    echo "(nenhum)" . PHP_EOL;
} else {
    foreach ($updates as $sql) {
        echo $sql . PHP_EOL;
    }
}

if ($dryRun) {
    echo PHP_EOL . "[DRY RUN] Nenhum UPDATE foi executado — script só lê e imprime sugestões." . PHP_EOL;
} else {
    echo PHP_EOL . "=== Executando UPDATEs no banco (\$dryRun = false) ===" . PHP_EOL;
    $stmt = $pdo->prepare('UPDATE frascos SET foto = :foto WHERE sku = :sku');
    $atualizados = 0;
    $pdo->beginTransaction();
    try {
        foreach ($paraExecutar as $sku => $foto) {
            $stmt->execute(['foto' => $foto, 'sku' => $sku]);
            $atualizados += $stmt->rowCount();
        }
        $pdo->commit();
        echo "Frascos atualizados: {$atualizados} de " . count($paraExecutar) . " tentativas." . PHP_EOL;
    } catch (\Throwable $e) {
        $pdo->rollBack();
        fwrite(STDERR, "ERRO durante a execução — rollback aplicado: " . $e->getMessage() . "\n");
        exit(1);
    }
}
