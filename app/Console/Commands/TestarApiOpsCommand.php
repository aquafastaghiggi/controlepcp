<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\IntegracaoOrdemService;
use Illuminate\Console\Command;

class TestarApiOpsCommand extends Command
{
    protected $signature   = 'cigam:testar-ops {numero? : Número da OP a buscar (ex: 201298)}';
    protected $description = 'Testa o endpoint ?ops da API CIGAM e exibe a estrutura retornada';

    public function handle(IntegracaoOrdemService $servico): int
    {
        $this->info('=== Teste da API CIGAM — endpoint ?ops ===');
        $this->newLine();

        // 1. Verificar conexão
        $this->line('Verificando conexão...');
        $status = $servico->verificarConexao();

        if (! $status['conectado']) {
            $this->error('❌ ' . $status['mensagem']);
            return self::FAILURE;
        }

        $this->info('✅ ' . $status['mensagem']);
        $this->newLine();

        // 2. Buscar OP específica
        $numero = $this->argument('numero') ?? '201298';
        $this->line("Buscando OP #{$numero}...");

        $ordem = $servico->buscarOrdem((string) $numero);

        if ($ordem === null) {
            $this->warn("⚠️  OP #{$numero} não encontrada (API retornou vazio).");
            $this->newLine();
            $this->line('Isso é normal se:');
            $this->line('  • A OP não existe no CIGAM');
            $this->line('  • A OP já foi encerrada');
            $this->line('  • O número está incorreto');
            return self::SUCCESS;
        }

        $this->info("✅ OP #{$numero} encontrada:");
        $this->newLine();

        $this->table(
            ['Campo', 'Valor'],
            [
                ['numero_op',  $ordem['numero_op']],
                ['sku',        $ordem['sku']],
                ['descricao',  $ordem['descricao']],
                ['quantidade', number_format($ordem['quantidade'], 3, ',', '.')],
                ['unidade',    $ordem['unidade']],
            ]
        );

        return self::SUCCESS;
    }
}
