<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('programacoes_sopro', function (Blueprint $table) {
            $table->id();
            $table->foreignId('maquina_id')->constrained('maquinas')->cascadeOnDelete();
            $table->string('numero_op')->nullable();
            $table->enum('status', ['rascunho', 'calculada', 'confirmada', 'cancelada', 'arquivada'])->default('rascunho');
            $table->decimal('eficiencia', 5, 2)->default(70.00);
            $table->json('dias_selecionados')->nullable();
            $table->boolean('otimizado')->default(false);
            $table->string('origem')->default('excel');
            $table->dateTime('data_inicio_planejada')->nullable();
            $table->dateTime('calculado_em')->nullable();
            $table->dateTime('arquivada_em')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('programacoes_sopro');
    }
};
