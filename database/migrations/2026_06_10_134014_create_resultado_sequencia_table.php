<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Resultado detalhado do cálculo de sequenciamento.
 * Cada linha representa um bloco de tempo alocado — podendo ser um setup
 * (troca de produto) ou uma produção real. Múltiplas linhas por item
 * acontecem quando a produção cruza turnos ou dias diferentes.
 *
 * O campo memoria_calculo armazena o detalhamento segmento-a-segmento
 * em texto legível, essencial para auditoria e rastreabilidade.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('resultado_sequencia', function (Blueprint $table) {
            $table->id();

            $table->foreignId('programacao_id')
                ->constrained('programacoes')
                ->cascadeOnDelete();

            // Nulo para linhas de setup (que não têm item próprio)
            $table->foreignId('item_id')
                ->nullable()
                ->constrained('itens_programacao')
                ->nullOnDelete();

            // setup = troca de produto | producao = bloco de fabricação
            $table->enum('tipo', ['setup', 'producao']);

            // SKU produzido neste bloco (ou SKU destino, no caso de setup)
            $table->string('sku', 120)->nullable();

            $table->dateTime('inicio');
            $table->dateTime('fim');

            $table->unsignedInteger('duracao_minutos');

            // Quantidade estimada com base no progresso até o momento da consulta
            $table->decimal('quantidade_estimada', 10, 2)->nullable();

            // Texto legível: "12/06 turno 07:10-11:28 | usado 08:00-11:28 = 3h28m | ..."
            $table->text('memoria_calculo')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resultado_sequencia');
    }
};
