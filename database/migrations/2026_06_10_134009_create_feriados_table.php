<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Feriados e paradas programadas de cada calendário.
 * Em dias cadastrados aqui, nenhum turno é disponibilizado para produção,
 * independente dos dias_uteis configurados.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feriados', function (Blueprint $table) {
            $table->id();

            $table->foreignId('calendario_id')
                ->constrained('calendarios')
                ->cascadeOnDelete();

            $table->date('data');

            // Descrição opcional para rastreabilidade (ex: "Natal", "Parada preventiva")
            $table->string('descricao', 150)->default('');

            $table->timestamps();

            // Mesma data não pode ser cadastrada duas vezes no mesmo calendário
            $table->unique(['calendario_id', 'data']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feriados');
    }
};
