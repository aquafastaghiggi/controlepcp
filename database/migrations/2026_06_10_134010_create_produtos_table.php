<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Produtos (SKUs) que podem ser programados para produção.
 * O campo taxa_por_hora é o dado mais crítico do sistema:
 * ele define diretamente quanto tempo cada ordem vai consumir na linha.
 *
 * referencia_setup agrupa produtos com tempo de troca equivalente
 * (família de produto), facilitando a manutenção da matriz de setup.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('produtos', function (Blueprint $table) {
            $table->id();

            // SKU é a chave de negócio — usado em importações, integração ERP e matriz de setup
            $table->string('sku', 120)->unique();

            $table->string('descricao', 255);

            // Caixas (ou unidades) produzidas por hora a 100% de eficiência
            $table->decimal('taxa_por_hora', 8, 2);

            // Família de produto para agrupamento na matriz de setup (ex: "DESINFETANTE 5L")
            $table->string('referencia_setup', 120)->nullable();

            $table->boolean('ativo')->default(true);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('produtos');
    }
};
