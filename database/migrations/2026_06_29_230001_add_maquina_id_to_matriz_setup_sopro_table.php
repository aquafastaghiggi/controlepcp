<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('matriz_setup_sopro', function (Blueprint $table) {
            // Adicionar maquina_id
            $table->foreignId('maquina_id')
                ->nullable()
                ->after('id')
                ->constrained('maquinas')
                ->nullOnDelete();
        });

        Schema::table('matriz_setup_sopro', function (Blueprint $table) {
            // Dropar FKs que dependem do índice único antes de removê-lo
            $table->dropForeign('matriz_setup_sopro_sku_origem_foreign');
            $table->dropForeign('matriz_setup_sopro_sku_destino_foreign');

            // Trocar o índice único
            $table->dropUnique(['sku_origem', 'sku_destino']);
            $table->unique(['maquina_id', 'sku_origem', 'sku_destino']);

            // Recriar as FKs
            $table->foreign('sku_origem')->references('sku')->on('frascos')->cascadeOnDelete();
            $table->foreign('sku_destino')->references('sku')->on('frascos')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('matriz_setup_sopro', function (Blueprint $table) {
            $table->dropUnique(['maquina_id', 'sku_origem', 'sku_destino']);
            $table->dropForeign(['maquina_id']);
            $table->dropColumn('maquina_id');
            $table->unique(['sku_origem', 'sku_destino']);
        });
    }
};
