<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dias da semana em que cada turno (intervalo) está ativo.
 * Permite configurar turnos que só existem em determinados dias —
 * por exemplo, turno noturno somente de segunda a quinta.
 * Convenção: 1 = Segunda ... 7 = Domingo (padrão ISO 8601).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dias_uteis', function (Blueprint $table) {
            $table->id();

            $table->foreignId('intervalo_id')
                ->constrained('intervalos')
                ->cascadeOnDelete();

            // 1=Segunda, 2=Terça, 3=Quarta, 4=Quinta, 5=Sexta, 6=Sábado, 7=Domingo
            $table->unsignedTinyInteger('dia_semana');

            $table->timestamps();

            $table->unique(['intervalo_id', 'dia_semana']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dias_uteis');
    }
};
