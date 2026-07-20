<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('codi_performance', function (Blueprint $table) {
            // Chave natural do CODI para updateOrCreate correto
            $table->unsignedBigInteger('codigo_performance')->nullable()->unique()->after('id');
            // Índice composto para consultas por recurso+item
            $table->index(['codigo_recurso', 'codigo_item'], 'idx_codi_perf_recurso_item');
        });
    }

    public function down(): void
    {
        Schema::table('codi_performance', function (Blueprint $table) {
            $table->dropUnique(['codigo_performance']);
            $table->dropIndex('idx_codi_perf_recurso_item');
            $table->dropColumn('codigo_performance');
        });
    }
};
