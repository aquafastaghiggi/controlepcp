<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('programacoes', function (Blueprint $table) {
            $table->dropUnique(['numero_op']);
            $table->unique(['linha_id', 'numero_op'], 'programacoes_linha_op_unique');
        });
    }

    public function down(): void
    {
        Schema::table('programacoes', function (Blueprint $table) {
            $table->dropUnique('programacoes_linha_op_unique');
            $table->unique('numero_op');
        });
    }
};
