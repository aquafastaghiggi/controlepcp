<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Calendario;
use App\Models\DiaUtil;
use App\Models\Intervalo;
use App\Models\Linha;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Cria um calendário padrão para todas as linhas que ainda não possuem um.
 *
 * Turnos padrão (Seg–Sex):
 *   Turno 1 (Matutino):   07:10–11:28
 *   Turno 2 (Vespertino): 13:35–17:40
 *   Turno 3 (Noturno 1):  17:40–22:00
 *
 * Idempotente: pula linhas que já têm calendário.
 */
class CalendariosLinhasSeeder extends Seeder
{
    private const TURNOS = [
        ['nome' => 'Turno 1', 'inicio' => '07:10', 'fim' => '11:28', 'ordem' => 1],
        ['nome' => 'Turno 2', 'inicio' => '13:35', 'fim' => '17:40', 'ordem' => 2],
        ['nome' => 'Turno 3', 'inicio' => '17:40', 'fim' => '22:00', 'ordem' => 3],
    ];

    private const DIAS_SEG_SEX = [1, 2, 3, 4, 5];

    public function run(): void
    {
        $linhas = Linha::whereDoesntHave('calendario')->get();

        if ($linhas->isEmpty()) {
            $this->command->info('Todas as linhas já possuem calendário. Nada a fazer.');
            return;
        }

        DB::transaction(function () use ($linhas) {
            foreach ($linhas as $linha) {
                $cal = Calendario::create([
                    'linha_id' => $linha->id,
                    'nome'     => "Calendário Padrão {$linha->codigo}",
                ]);

                foreach (self::TURNOS as $t) {
                    $intervalo = Intervalo::create([
                        'calendario_id' => $cal->id,
                        'nome'          => $t['nome'],
                        'hora_inicio'   => $t['inicio'],
                        'hora_fim'      => $t['fim'],
                        'ordem'         => $t['ordem'],
                        'ativo'         => true,
                    ]);

                    foreach (self::DIAS_SEG_SEX as $dia) {
                        DiaUtil::create([
                            'intervalo_id' => $intervalo->id,
                            'dia_semana'   => $dia,
                        ]);
                    }
                }

                $this->command->info("✓ Calendário padrão criado para {$linha->codigo}");
            }
        });
    }
}
