<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('codi_eficiencia', function (Blueprint $table) {
            // decimal(5,2) suporta até 999.99 — overflow com valores como 3280%
            // decimal(8,2) suporta até 999999.99%
            $table->decimal('performance_tempo', 8, 2)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('codi_eficiencia', function (Blueprint $table) {
            $table->decimal('performance_tempo', 5, 2)->nullable()->change();
        });
    }
};
