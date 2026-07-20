<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Itens (ordens individuais) de uma programação de produção.
 * Cada item representa um SKU com quantidade a produzir em uma posição
 * específica da sequência. A ordem dos itens define diretamente o tempo
 * total de setup (trocar a sequência pode reduzir horas de preparação).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('itens_programacao', function (Blueprint $table) {
            $table->id();

            $table->foreignId('programacao_id')
                ->constrained('programacoes')
                ->cascadeOnDelete();

            // Posição na sequência de produção (1 = primeiro a produzir)
            $table->unsignedInteger('sequencia');

            // Desnormalizado intencionalmente: permite que o SKU seja renomeado
            // no cadastro sem alterar o histórico das programações
            $table->string('sku', 120);
            $table->string('descricao_produto', 255)->default('');

            $table->decimal('quantidade', 10, 2);

            $table->timestamps();

            $table->foreign('sku')->references('sku')->on('produtos')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('itens_programacao');
    }
};
