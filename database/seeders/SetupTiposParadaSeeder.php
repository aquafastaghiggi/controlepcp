<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SetupTiposParadaSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('setup_tipos_parada')->truncate();

        $dados = [
            ['codigo_recurso' => '1',  'nome_parada' => 'TROCA DE COR'],
            ['codigo_recurso' => '1',  'nome_parada' => 'TROCA DE MOLDE'],
            ['codigo_recurso' => '2',  'nome_parada' => 'TROCA DE MOLDE'],
            ['codigo_recurso' => '3',  'nome_parada' => 'TROCA DE COR'],
            ['codigo_recurso' => '5',  'nome_parada' => 'TROCA DE COR'],
            ['codigo_recurso' => '5',  'nome_parada' => 'LIMPEZA TORPEDO CONTAMINAÇÃO'],
            ['codigo_recurso' => '6',  'nome_parada' => 'TROCA DE LIQUIDO'],
            ['codigo_recurso' => '6',  'nome_parada' => 'TROCA DE KIT'],
            ['codigo_recurso' => '7',  'nome_parada' => 'TROCA DE LIQUIDO'],
            ['codigo_recurso' => '7',  'nome_parada' => 'TROCA DE KIT'],
            ['codigo_recurso' => '8',  'nome_parada' => 'TROCA DE TANQUE PRODUTO'],
            ['codigo_recurso' => '8',  'nome_parada' => 'TROCA DE LIQUIDO'],
            ['codigo_recurso' => '8',  'nome_parada' => 'TROCA DE KIT'],
            ['codigo_recurso' => '9',  'nome_parada' => 'TROCA DE LIQUIDO'],
            ['codigo_recurso' => '9',  'nome_parada' => 'TROCA DE KIT'],
            ['codigo_recurso' => '10', 'nome_parada' => 'TROCA DE RÓTULO'],
            ['codigo_recurso' => '10', 'nome_parada' => 'TROCA DE LIQUIDO'],
            ['codigo_recurso' => '10', 'nome_parada' => 'TROCA DE KIT'],
        ];

        foreach ($dados as $row) {
            DB::table('setup_tipos_parada')->insert(array_merge($row, [
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }
    }
}
