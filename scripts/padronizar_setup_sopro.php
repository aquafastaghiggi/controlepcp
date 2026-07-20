<?php

declare(strict_types=1);

/**
 * Script standalone (bootstrap Laravel) pra padronizar tempos de setup
 * faltantes/zerados na matriz_setup_sopro, com base nos pares reais de SKUs
 * que aparecem em sequência nas OPs de itens_programacao_sopro.
 *
 * Classifica o tipo de setup (troca_molde/troca_cor) por heurística de texto
 * na descrição do frasco (volume + palavra de cor) — molde/cor cadastrados em
 * frascos estão vazios pra todos os itens, então não dá pra usar esses campos.
 *
 * Com $dryRun = true (padrão): só lê e imprime o que seria inserido/atualizado,
 * não persiste nada.
 * Com $dryRun = false: aplica de fato (INSERT/UPDATE em matriz_setup_sopro).
 *
 * Uso: php scripts/padronizar_setup_sopro.php
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Frasco;
use App\Models\MatrizSetupSopro;
use Illuminate\Support\Facades\DB;

$dryRun = false;

$temposPadrao = [
    'troca_molde' => 210,
    'troca_cor'   => 120,
];

// Padrão anterior (já aplicado numa rodada passada deste script) — usado só
// pra identificar quais registros ainda estão no valor-padrão antigo e
// precisam subir pro novo padrão acima. Não mexe em durações reais/diferentes.
$temposPadraoAntigos = [
    'troca_molde' => 120,
    'troca_cor'   => 30,
];

const CORES_CONHECIDAS = [
    'azul', 'vermelho', 'preto', 'roxo', 'verde',
    'branco', 'rosa', 'amarelo', 'laranja', 'cinza',
];

/**
 * Extrai o token de volume (ex.: "500ml", "1l", "1.5l") de uma descrição.
 * Retorna null se não encontrar nenhum volume reconhecível.
 */
function extrairVolumeDescricao(string $descricao): ?string
{
    $normalizado = mb_strtolower($descricao, 'UTF-8');
    $normalizado = preg_replace('/(\d),(\d)/', '$1.$2', $normalizado) ?? $normalizado;

    if (preg_match('/(\d+(?:\.\d+)?)\s*(ml|l)\b/', $normalizado, $m)) {
        return $m[1] . $m[2];
    }

    return null;
}

/**
 * Extrai a primeira cor conhecida encontrada na descrição.
 * Retorna null se nenhuma cor da lista aparecer.
 */
function extrairCorDescricao(string $descricao): ?string
{
    $normalizado = mb_strtolower($descricao, 'UTF-8');

    foreach (CORES_CONHECIDAS as $cor) {
        if (preg_match('/\b' . $cor . '\b/', $normalizado)) {
            return $cor;
        }
    }

    return null;
}

/**
 * Classifica o tipo de setup entre dois frascos por heurística de texto:
 *   - Volume diferente                              → troca_molde
 *   - Mesmo volume + cor detectada nos dois lados    → troca_cor (mesma ou diferente)
 *   - Mesmo volume + cor não detectada em algum lado → troca_molde (produto diferente)
 */
function classificarTipoSetup(string $descOrigem, string $descDestino): string
{
    $volumeOrigem  = extrairVolumeDescricao($descOrigem);
    $volumeDestino = extrairVolumeDescricao($descDestino);

    if ($volumeOrigem !== $volumeDestino) {
        return 'troca_molde';
    }

    $corOrigem  = extrairCorDescricao($descOrigem);
    $corDestino = extrairCorDescricao($descDestino);

    if ($corOrigem !== null && $corDestino !== null) {
        return 'troca_cor';
    }

    return 'troca_molde';
}

// 1. Pares reais (maquina_id, sku_origem, sku_destino) — derivados da sequência
// de itens dentro de cada programação Sopro (itens consecutivos com sku diferente).
$itens = DB::table('itens_programacao_sopro as ip')
    ->join('programacoes_sopro as p', 'p.id', '=', 'ip.programacao_sopro_id')
    ->orderBy('ip.programacao_sopro_id')
    ->orderBy('ip.sequencia')
    ->get(['ip.programacao_sopro_id', 'ip.sku', 'ip.sequencia', 'p.maquina_id']);

$pares = []; // chave: maquina_id|sku_origem|sku_destino => dados do par

foreach ($itens->groupBy('programacao_sopro_id') as $itensDaProgramacao) {
    $anterior = null;

    foreach ($itensDaProgramacao as $item) {
        if ($anterior !== null && $anterior->sku !== $item->sku && $anterior->maquina_id !== null) {
            $chave = $anterior->maquina_id . '|' . $anterior->sku . '|' . $item->sku;

            $pares[$chave] = [
                'maquina_id'  => $anterior->maquina_id,
                'sku_origem'  => $anterior->sku,
                'sku_destino' => $item->sku,
            ];
        }

        $anterior = $item;
    }
}

echo 'Pares únicos encontrados nas OPs: ' . count($pares) . PHP_EOL;
echo str_repeat('=', 100) . PHP_EOL;

$frascos = Frasco::all()->keyBy('sku');

$inseridos = 0;
$atualizados = 0;
$atualizadosPadraoAntigo = 0;
$semAlteracao = 0;
$semCadastro = 0;

foreach ($pares as $par) {
    $frascoOrigem  = $frascos->get($par['sku_origem']);
    $frascoDestino = $frascos->get($par['sku_destino']);

    if ($frascoOrigem === null || $frascoDestino === null) {
        echo "AVISO: par maquina={$par['maquina_id']} {$par['sku_origem']} -> {$par['sku_destino']} tem SKU sem cadastro em frascos, pulando." . PHP_EOL;
        $semCadastro++;
        continue;
    }

    $tipo = classificarTipoSetup($frascoOrigem->descricao, $frascoDestino->descricao);
    $padrao = $temposPadrao[$tipo];

    $existente = MatrizSetupSopro::where('maquina_id', $par['maquina_id'])
        ->where('sku_origem', $par['sku_origem'])
        ->where('sku_destino', $par['sku_destino'])
        ->first();

    if ($existente === null) {
        printf(
            "%sINSERIR: maquina=%s | %s (%s) -> %s (%s) | tipo=%s | duracao=%d min%s",
            $dryRun ? '[DRY RUN] ' : '',
            $par['maquina_id'],
            $par['sku_origem'],
            $frascoOrigem->descricao,
            $par['sku_destino'],
            $frascoDestino->descricao,
            $tipo,
            $padrao,
            PHP_EOL
        );

        if (! $dryRun) {
            MatrizSetupSopro::create([
                'maquina_id'      => $par['maquina_id'],
                'sku_origem'      => $par['sku_origem'],
                'sku_destino'     => $par['sku_destino'],
                'duracao_minutos' => $padrao,
                'tipo_setup'      => $tipo,
            ]);
        }

        $inseridos++;
    } elseif ((int) $existente->duracao_minutos === 0) {
        printf(
            "%sATUALIZAR (era 0): maquina=%s | %s (%s) -> %s (%s) | tipo=%s | duracao=%d min%s",
            $dryRun ? '[DRY RUN] ' : '',
            $par['maquina_id'],
            $par['sku_origem'],
            $frascoOrigem->descricao,
            $par['sku_destino'],
            $frascoDestino->descricao,
            $tipo,
            $padrao,
            PHP_EOL
        );

        if (! $dryRun) {
            $existente->update([
                'duracao_minutos' => $padrao,
                'tipo_setup'      => $existente->tipo_setup ?? $tipo,
            ]);
        }

        $atualizados++;
    } elseif ($existente->tipo_setup === $tipo && (int) $existente->duracao_minutos === ($temposPadraoAntigos[$tipo] ?? null)) {
        // Registro ainda no padrão antigo (aplicado numa rodada passada deste
        // mesmo script) — sobe pro novo padrão. Não mexe em durações reais
        // diferentes do padrão antigo (essas são dados genuínos, não default).
        printf(
            "%sATUALIZAR (padrão antigo %d -> %d): maquina=%s | %s (%s) -> %s (%s) | tipo=%s%s",
            $dryRun ? '[DRY RUN] ' : '',
            $temposPadraoAntigos[$tipo],
            $padrao,
            $par['maquina_id'],
            $par['sku_origem'],
            $frascoOrigem->descricao,
            $par['sku_destino'],
            $frascoDestino->descricao,
            $tipo,
            PHP_EOL
        );

        if (! $dryRun) {
            $existente->update(['duracao_minutos' => $padrao]);
        }

        $atualizadosPadraoAntigo++;
    } else {
        $semAlteracao++;
    }
}

echo str_repeat('=', 100) . PHP_EOL;
echo "Resumo: inseridos={$inseridos} | atualizados={$atualizados} | atualizados_padrao_antigo={$atualizadosPadraoAntigo} | sem_alteracao={$semAlteracao} | sem_cadastro_frasco={$semCadastro}" . PHP_EOL;

if ($dryRun) {
    echo PHP_EOL . '[DRY RUN] Nenhuma alteração foi persistida — script só leu e imprimiu sugestões.' . PHP_EOL;
} else {
    echo PHP_EOL . 'Alterações persistidas em matriz_setup_sopro.' . PHP_EOL;
}
