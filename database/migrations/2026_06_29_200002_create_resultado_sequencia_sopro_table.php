<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('resultado_sequencia_sopro', function (Blueprint $table) {
            $table->id();
            $table->foreignId('programacao_sopro_id')->constrained('programacoes_sopro')->cascadeOnDelete();
            $table->foreignId('item_id')->nullable()->constrained('itens_programacao_sopro')->nullOnDelete();
            $table->enum('tipo', ['setup', 'producao']);
            $table->string('sku', 20)->nullable();
            $table->dateTime('inicio');
            $table->dateTime('fim');
            $table->integer('duracao_minutos')->default(0);
            $table->decimal('quantidade_estimada', 12, 2)->default(0);
            $table->text('memoria_calculo')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resultado_sequencia_sopro');
    }
};
