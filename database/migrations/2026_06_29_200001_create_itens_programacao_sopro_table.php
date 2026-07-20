<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('itens_programacao_sopro', function (Blueprint $table) {
            $table->id();
            $table->foreignId('programacao_sopro_id')->constrained('programacoes_sopro')->cascadeOnDelete();
            $table->string('sku', 20);
            $table->string('descricao_produto')->nullable();
            $table->decimal('quantidade', 12, 2)->default(0);
            $table->string('numero_op')->nullable();
            $table->integer('sequencia')->default(999);
            $table->date('data_programada')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('itens_programacao_sopro');
    }
};
