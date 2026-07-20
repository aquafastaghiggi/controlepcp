<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('codi_eficiencia_sopro', function (Blueprint $table) {
            $table->id();
            $table->foreignId('programacao_sopro_id')->constrained('programacoes_sopro')->cascadeOnDelete();
            $table->string('numero_op');
            $table->string('sku', 20)->nullable();
            $table->decimal('quantidade_programada', 12, 2)->nullable();
            $table->decimal('quantidade_realizada', 12, 2)->nullable();
            $table->integer('tempo_padrao_minutos')->nullable();
            $table->integer('tempo_padrao_nominal')->nullable();
            $table->integer('tempo_real_minutos')->nullable();
            $table->integer('tempo_parado_minutos')->nullable();
            $table->dateTime('inicio_previsto')->nullable();
            $table->dateTime('fim_previsto')->nullable();
            $table->dateTime('inicio_real')->nullable();
            $table->dateTime('fim_real')->nullable();
            $table->decimal('eficiencia_quantidade', 6, 2)->nullable();
            $table->decimal('performance_tempo', 8, 2)->nullable();
            $table->decimal('disponibilidade', 6, 2)->nullable();
            $table->decimal('oee', 6, 2)->nullable();
            $table->decimal('produtividade', 10, 2)->nullable();
            $table->decimal('desvio_quantidade', 12, 2)->nullable();
            $table->decimal('desvio_quantidade_pct', 6, 2)->nullable();
            $table->decimal('desvio_tempo_horas', 8, 2)->nullable();
            $table->integer('desvio_prazo_dias')->nullable();
            $table->enum('status', ['pendente', 'ok', 'aviso', 'critico'])->default('pendente');
            $table->dateTime('calculado_em')->nullable();
            $table->timestamps();

            $table->unique(['programacao_sopro_id', 'numero_op']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('codi_eficiencia_sopro');
    }
};
