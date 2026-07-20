<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('programacoes', function (Blueprint $table): void {
            // Suporta scopeHistorico: WHERE status='arquivada' ORDER BY arquivada_em DESC
            $table->index(['status', 'arquivada_em'], 'idx_programacoes_status_arquivada_em');
        });
    }

    public function down(): void
    {
        Schema::table('programacoes', function (Blueprint $table): void {
            $table->dropIndex('idx_programacoes_status_arquivada_em');
        });
    }
};
