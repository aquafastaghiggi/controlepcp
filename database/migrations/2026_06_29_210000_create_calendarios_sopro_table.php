<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calendarios_sopro', function (Blueprint $table) {
            $table->id();
            $table->foreignId('maquina_id')
                ->constrained('maquinas')
                ->cascadeOnDelete();
            $table->string('nome', 120)->default('Calendário Sopro');
            $table->timestamps();
            $table->unique('maquina_id');
        });

        Schema::create('intervalos_sopro', function (Blueprint $table) {
            $table->id();
            $table->foreignId('calendario_sopro_id')
                ->constrained('calendarios_sopro')
                ->cascadeOnDelete();
            $table->string('nome', 60);
            $table->time('hora_inicio');
            $table->time('hora_fim');
            $table->unsignedTinyInteger('ordem')->default(1);
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });

        Schema::create('feriados_sopro', function (Blueprint $table) {
            $table->id();
            $table->foreignId('calendario_sopro_id')
                ->constrained('calendarios_sopro')
                ->cascadeOnDelete();
            $table->date('data');
            $table->string('descricao', 120)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feriados_sopro');
        Schema::dropIfExists('intervalos_sopro');
        Schema::dropIfExists('calendarios_sopro');
    }
};
