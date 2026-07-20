<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Linhas de produção físicas da fábrica.
 * Cada linha tem seu próprio calendário de turnos, produtos habilitados
 * e matriz de setup — são ambientes completamente independentes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('linhas', function (Blueprint $table) {
            $table->id();

            // Código curto para identificação rápida e importações (ex: "L2", "L3")
            $table->string('codigo', 20)->unique();

            $table->string('nome', 150);

            // Linha inativa não aparece para seleção, mas histórico é preservado
            $table->boolean('ativo')->default(true);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('linhas');
    }
};
