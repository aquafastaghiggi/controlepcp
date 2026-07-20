<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('matriz_setup_sopro', function (Blueprint $table) {
            $table->id();
            $table->string('sku_origem', 20)->charset('utf8mb4')->collation('utf8mb4_general_ci');
            $table->string('sku_destino', 20)->charset('utf8mb4')->collation('utf8mb4_general_ci');
            $table->unsignedInteger('duracao_minutos')->default(0);
            $table->enum('tipo_setup', ['troca_cor', 'troca_molde'])->nullable();
            $table->timestamps();

            $table->unique(['sku_origem', 'sku_destino']);
            $table->foreign('sku_origem')->references('sku')->on('frascos')->cascadeOnDelete();
            $table->foreign('sku_destino')->references('sku')->on('frascos')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('matriz_setup_sopro');
    }
};
