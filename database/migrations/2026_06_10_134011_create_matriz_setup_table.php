<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Matriz de tempo de setup (troca de produto) entre pares de SKUs.
 * Define quantos minutos são necessários para preparar a linha para
 * produzir o produto_destino depois de ter produzido produto_origem.
 *
 * Par (A→B) pode ter tempo diferente de (B→A) — isso é intencional.
 * Se um par não estiver cadastrado, o sistema assume 0 minutos de setup.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('matriz_setup', function (Blueprint $table) {
            $table->id();

            // Referência pelo SKU (string) para facilitar importação sem precisar do id
            $table->string('sku_origem', 120);
            $table->string('sku_destino', 120);

            $table->unsignedInteger('duracao_minutos');

            $table->timestamps();

            // Cada combinação origem→destino só pode ter uma entrada
            $table->unique(['sku_origem', 'sku_destino']);

            $table->foreign('sku_origem')->references('sku')->on('produtos')->restrictOnDelete();
            $table->foreign('sku_destino')->references('sku')->on('produtos')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('matriz_setup');
    }
};
