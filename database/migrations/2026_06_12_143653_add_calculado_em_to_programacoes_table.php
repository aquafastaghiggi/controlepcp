<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('programacoes', function (Blueprint $table) {
            $table->timestamp('calculado_em')->nullable()->after('otimizado');
        });
    }

    public function down(): void
    {
        Schema::table('programacoes', function (Blueprint $table) {
            $table->dropColumn('calculado_em');
        });
    }
};
