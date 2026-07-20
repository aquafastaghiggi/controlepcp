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
        Schema::table('linhas', function (Blueprint $table) {
            $table->string('codigo_recurso')->nullable()->after('codigo');
        });
    }

    public function down(): void
    {
        Schema::table('linhas', function (Blueprint $table) {
            $table->dropColumn('codigo_recurso');
        });
    }
};
