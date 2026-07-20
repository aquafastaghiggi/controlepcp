<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Intervalos de trabalho (turnos) dentro de um calendário.
 * Cada intervalo define um bloco de horário disponível para produção.
 * Suporta até 4 turnos por calendário, incluindo turnos noturnos que
 * cruzam a meia-noite (ex: 23:00–03:00).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('intervalos', function (Blueprint $table) {
            $table->id();

            $table->foreignId('calendario_id')
                ->constrained('calendarios')
                ->cascadeOnDelete();

            // Nome amigável para exibição (ex: "Turno 1", "Turno Noturno")
            $table->string('nome', 50)->default('Turno');

            // Ordem de exibição e prioridade no cálculo (1 = primeiro turno do dia)
            $table->unsignedTinyInteger('ordem')->default(1);

            $table->time('hora_inicio');
            $table->time('hora_fim');

            // Turno inativo é ignorado no cálculo sem precisar ser excluído
            $table->boolean('ativo')->default(true);

            $table->timestamps();

            // Não pode ter dois turnos com mesmo horário no mesmo calendário
            $table->unique(['calendario_id', 'hora_inicio', 'hora_fim']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('intervalos');
    }
};
