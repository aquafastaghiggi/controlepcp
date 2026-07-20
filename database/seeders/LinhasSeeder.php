<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Linha;
use Illuminate\Database\Seeder;

/**
 * Cria as linhas reais da fábrica conforme cadastro do sandbox (lin_linhas).
 * Idempotente: usa updateOrCreate pelo codigo, nunca remove linhas existentes.
 */
class LinhasSeeder extends Seeder
{
    public function run(): void
    {
        // Linhas reais mapeadas do controlepcp_sandbox.lin_linhas
        $linhas = [
            ['codigo' => 'LN01', 'nome' => 'Linha LN01', 'ativo' => true],
            ['codigo' => 'LN02', 'nome' => 'Linha LN02', 'ativo' => true],
            ['codigo' => 'LN03', 'nome' => 'Linha LN03', 'ativo' => true],
            ['codigo' => 'LN04', 'nome' => 'Linha LN04', 'ativo' => true],
            ['codigo' => 'LN05', 'nome' => 'Linha LN05', 'ativo' => true],
            ['codigo' => 'LN06', 'nome' => 'Linha LN06', 'ativo' => true],
            ['codigo' => 'LN07', 'nome' => 'Linha LN07', 'ativo' => true],
            ['codigo' => 'LN10', 'nome' => 'Linha LN10', 'ativo' => true],
        ];

        foreach ($linhas as $dados) {
            Linha::updateOrCreate(
                ['codigo' => $dados['codigo']],
                ['nome' => $dados['nome'], 'ativo' => $dados['ativo']]
            );
        }

        $this->command->info('LinhasSeeder: ' . count($linhas) . ' linhas sincronizadas.');
    }
}
