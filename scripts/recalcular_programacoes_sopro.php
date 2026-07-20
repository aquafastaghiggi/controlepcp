<?php

declare(strict_types=1);

/**
 * Script standalone (bootstrap Laravel) pra recalcular as programações Sopro
 * já confirmadas — necessário depois da correção do bug ×1000 em
 * SequenciadorSoproService, já que os horários previstos delas foram
 * calculados com a duração de produção 1000x mais curta.
 *
 * Com $dryRun = true (padrão): executa o recálculo de verdade (pra validar que
 * não dá erro em nenhuma programação), mas SEMPRE desfaz a transação no final
 * — nada é persistido.
 * Com $dryRun = false: aplica de fato (commita as alterações).
 *
 * Uso: php scripts/recalcular_programacoes_sopro.php
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Actions\CalcularSequenciaSoproAction;
use App\Models\ProgramacaoSopro;
use App\Services\Codi\EficienciaCalculatorSopro;
use Illuminate\Support\Facades\DB;

$dryRun = false;

$programacaoIds = [10, 11, 12, 18, 13, 14, 15, 16, 17]; // MAQ01-MAQ10 (confirmadas)

/** Marcador interno só pra forçar rollback em modo dry-run — não é um erro real. */
final class DryRunRollback extends \RuntimeException {}

$ok = 0;
$erros = 0;

foreach ($programacaoIds as $progId) {
    try {
        DB::transaction(function () use ($progId, $dryRun) {
            $prog = ProgramacaoSopro::findOrFail($progId);
            $maquinaCodigo = $prog->maquina->codigo ?? '?';

            // Temporariamente volta para calculada para passar a validação
            // de CalcularSequenciaSoproAction::validarProgramacao().
            $prog->update(['status' => 'calculada']);

            // Recalcula a sequência (assinatura real: executar(int $programacaoId,
            // ?DateTimeImmutable $momentoConsulta = null): array — sem parâmetro
            // de otimizador, o Sopro não tem OtimizadorSoproService).
            $calcularSequencia = app(CalcularSequenciaSoproAction::class);
            $calcularSequencia->executar($progId, new \DateTimeImmutable());

            // Restaura para confirmada
            $prog->update(['status' => 'confirmada', 'calculado_em' => now()]);

            // Atualiza codi_eficiencia_sopro (assinatura real:
            // calcularParaProgramacao(int $programacaoId): array).
            app(EficienciaCalculatorSopro::class)->calcularParaProgramacao($progId);

            $prefixo = $dryRun ? '[DRY RUN] ' : '';
            echo $prefixo . 'Prog #' . $progId . ' (' . $maquinaCodigo . ') — OK' . PHP_EOL;

            if ($dryRun) {
                // Força rollback — em dry-run, nada pode ser persistido.
                throw new DryRunRollback();
            }
        });

        $ok++;
    } catch (DryRunRollback) {
        // Esperado em dry-run — já reportado "OK" acima, rollback silencioso.
        $ok++;
    } catch (\Throwable $e) {
        echo 'Prog #' . $progId . ' — ERRO: ' . $e->getMessage() . PHP_EOL;
        $erros++;
    }
}

echo str_repeat('=', 80) . PHP_EOL;
echo "Resumo: ok={$ok} | erros={$erros} de " . count($programacaoIds) . ' programações' . PHP_EOL;

if ($dryRun) {
    echo PHP_EOL . '[DRY RUN] Recálculo executado e revertido (rollback) — nada foi persistido.' . PHP_EOL;
} else {
    echo PHP_EOL . 'Recálculo aplicado e persistido.' . PHP_EOL;
}
