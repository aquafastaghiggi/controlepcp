<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('codi_eficiencia', function (Blueprint $table) {
            $table->integer('tempo_padrao_nominal')->nullable()->after('tempo_padrao_minutos');
        });
    }

    public function down(): void
    {
        Schema::table('codi_eficiencia', function (Blueprint $table) {
            $table->dropColumn('tempo_padrao_nominal');
        });
    }
};
