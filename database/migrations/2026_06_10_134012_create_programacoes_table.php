<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cabeçalho de uma programação de produção.
 * Cada programação representa uma sessão de sequenciamento para uma linha,
 * com um conjunto de ordens a produzir a partir de uma data/hora base.
 *
 * O campo eficiencia permite simular o impacto de uma eficiência menor que
 * 100% antes de confirmar a programação (ex: 85% = linha mais lenta).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('programacoes', function (Blueprint $table) {
            $table->id();

            $table->foreignId('linha_id')
                ->constrained('linhas')
                ->restrictOnDelete();

            // Identificador da ordem de produção — pode vir do ERP
            $table->string('numero_op', 120)->nullable()->unique();

            $table->string('descricao', 255)->nullable();

            // Momento a partir do qual o cálculo começa a alocar tempo
            $table->dateTime('data_inicio_planejada');

            // Percentual de eficiência aplicado globalmente (100 = velocidade nominal)
            $table->decimal('eficiencia', 5, 2)->default(100.00);

            // rascunho: editável | calculada: resultado gerado | confirmada: enviada ao chão de fábrica
            $table->enum('status', ['rascunho', 'calculada', 'confirmada', 'cancelada'])
                ->default('rascunho');

            // Rastreia a origem das ordens para auditoria
            $table->enum('origem', ['manual', 'excel', 'api_erp'])->default('manual');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('programacoes');
    }
};
