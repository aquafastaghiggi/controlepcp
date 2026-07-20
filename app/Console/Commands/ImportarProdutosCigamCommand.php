<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\ImportarProdutosService;
use Illuminate\Console\Command;

class ImportarProdutosCigamCommand extends Command
{
    protected $signature   = 'cigam:importar-produtos {--matriz : Importar também a matriz de setup}';
    protected $description = 'Importa produtos do grupo 20 da API CIGAM cruzando com dados do sandbox';

    public function handle(ImportarProdutosService $servico): int
    {
        $this->info('Iniciando importação de produtos CIGAM...');

        $resultado = $servico->importar();

        $this->table(
            ['Importados', 'Atualizados', 'Sem taxa (sandbox)', 'Erros'],
            [[$resultado['importados'], $resultado['atualizados'], $resultado['sem_taxa'], count($resultado['erros'])]]
        );

        if (! empty($resultado['erros'])) {
            $this->warn('Erros encontrados:');
            foreach ($resultado['erros'] as $erro) {
                $this->line("  • {$erro}");
            }
        }

        if ($this->option('matriz')) {
            $this->info('Importando matriz de setup...');
            $resSetup = $servico->importarMatrizSetup();
            $this->info("Matriz: {$resSetup['importados']} entradas importadas.");
            foreach ($resSetup['erros'] as $e) {
                $this->warn("  • {$e}");
            }
        }

        $this->info('Concluído.');
        return self::SUCCESS;
    }
}
