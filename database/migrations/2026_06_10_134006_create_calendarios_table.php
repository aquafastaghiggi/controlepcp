<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Calendários de trabalho vinculados a uma linha de produção.
 * Cada linha possui exatamente um calendário ativo que define os turnos
 * disponíveis, os dias da semana válidos e os feriados.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calendarios', function (Blueprint $table) {
            $table->id();

            $table->foreignId('linha_id')
                ->constrained('linhas')
                ->cascadeOnDelete();

            $table->string('nome', 120)->default('Calendário padrão');

            $table->timestamps();

            // Cada linha pode ter apenas um calendário ativo por vez
            $table->unique('linha_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calendarios');
    }
};
