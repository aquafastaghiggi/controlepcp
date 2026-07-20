<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Programacao;
use App\Services\Codi\CodiClient;
use App\Services\Codi\CodiSyncService;
use App\Services\Codi\EficienciaCalculator;
use App\Models\ProgramacaoSopro;
use App\Services\Codi\EficienciaCalculatorSopro;
use Illuminate\Console\Command;

class SincronizarCodiCommand extends Command
{
    protected $signature = 'codi:sincronizar
        {--tipo=performance : Tipo de sincronização: performance, eventos, todos}
        {--data-inicio= : Data de início para sync de eventos (YYYY-MM-DD)}
        {--data-fim= : Data de fim para sync de eventos (YYYY-MM-DD)}';

    protected $description = 'Sincroniza dados do CODI com o banco local';

    public function handle(): int
    {
        $tipo = $this->option('tipo');

        $client  = new CodiClient();
        $service = new CodiSyncService($client);

        // Teste de conexão antes de qualquer sync
        $this->info('Testando conexão com o CODI...');
        $conexao = $client->testarConexao();

        if (!$conexao['conectado']) {
            $this->error('❌ CODI inacessível: ' . $conexao['mensagem']);
            $this->warn('  Verifique se o servidor está ligado e acessível na rede.');
            return self::FAILURE;
        }

        $this->info('✅ CODI conectado. Iniciando sincronização: ' . strtoupper($tipo));
        $this->newLine();

        if ($tipo === 'performance' || $tipo === 'todos') {
            $this->components->task('Sincronizar performance', function () use ($service) {
                $log = $service->sincronizarPerformance();
                $this->line(sprintf(
                    '   → novos: %d | atualizados: %d | erros: %d',
                    $log['novos'], $log['atualizados'], $log['erros']
                ));
                return $log['erros'] === 0;
            });
        }

        if ($tipo === 'eventos' || $tipo === 'todos') {
            $dataInicio = $this->option('data-inicio') ?? now()->subDay()->format('Y-m-d');
            $dataFim    = $this->option('data-fim')    ?? now()->format('Y-m-d');

            $this->components->task(
                "Sincronizar eventos ({$dataInicio} → {$dataFim})",
                function () use ($service, $dataInicio, $dataFim) {
                    $log = $service->sincronizarEventos($dataInicio, $dataFim);
                    $this->line(sprintf(
                        '   → novos: %d | atualizados: %d | erros: %d',
                        $log['novos'], $log['atualizados'], $log['erros']
                    ));
                    return $log['erros'] === 0;
                }
            );
        }

        // Recalcula eficiência para programações confirmadas com OPs ainda pendentes
        if ($tipo === 'eventos' || $tipo === 'todos') {
            $this->components->task('Recalcular eficiência', function () {
                $programacoes = Programacao::where('status', 'confirmada')
                    ->whereHas('eficiencias', fn ($q) => $q->where('status', 'pendente'))
                    ->get();

                $ok = 0;
                $erros = 0;
                foreach ($programacoes as $prog) {
                    try {
                        app(EficienciaCalculator::class)->calcularParaProgramacao($prog->id);
                        $ok++;
                    } catch (\Throwable $e) {
                        \Log::warning('EficienciaCalculator falhou no sync', [
                            'programacao_id' => $prog->id,
                            'erro'           => $e->getMessage(),
                        ]);
                        $erros++;
                    }
                }

                $this->line(sprintf('   → recalculadas: %d | erros: %d', $ok, $erros));
                return $erros === 0;
            });
        }

        // Recalcula eficiência Sopro para programações confirmadas com OPs pendentes
        if ($tipo === 'eventos' || $tipo === 'todos') {
            $this->components->task('Recalcular eficiência Sopro', function () {
                $programacoes = ProgramacaoSopro::where('status', 'confirmada')
                    ->whereHas('eficiencias', fn ($q) => $q->where('status', 'pendente'))
                    ->get();
                $ok = 0;
                $erros = 0;
                foreach ($programacoes as $prog) {
                    try {
                        app(EficienciaCalculatorSopro::class)->calcularParaProgramacao($prog->id);
                        $ok++;
                    } catch (\Throwable $e) {
                        \Log::warning('EficienciaCalculatorSopro falhou no sync', [
                            'programacao_id' => $prog->id,
                            'erro'           => $e->getMessage(),
                        ]);
                        $erros++;
                    }
                }
                $this->line(sprintf('   → recalculadas: %d | erros: %d', $ok, $erros));
                return $erros === 0;
            });
        }

        $this->newLine();
        $this->info('Sincronização concluída.');

        return self::SUCCESS;
    }
}
