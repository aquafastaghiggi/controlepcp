<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('programacoes', function (Blueprint $table) {
            // JSON com os dias da semana selecionados para esta programação.
            // Ex: [1,2,3,4,5] = Seg–Sex. Null = usa diasUteis do banco (comportamento padrão).
            $table->json('dias_selecionados')->nullable()->after('eficiencia');
        });
    }

    public function down(): void
    {
        Schema::table('programacoes', function (Blueprint $table) {
            $table->dropColumn('dias_selecionados');
        });
    }
};
