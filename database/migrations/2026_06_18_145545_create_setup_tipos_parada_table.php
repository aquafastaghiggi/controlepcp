<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('setup_tipos_parada', function (Blueprint $table) {
            $table->id();
            $table->string('codigo_recurso', 10);
            $table->string('nome_parada');
            $table->timestamps();
            $table->index('codigo_recurso');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('setup_tipos_parada');
    }
};
