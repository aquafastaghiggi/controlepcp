<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('divergencias_op', function (Blueprint $table) {
            $table->decimal('quantidade_prevista', 12, 2)->nullable()->after('tipo');
            $table->decimal('quantidade_realizada', 12, 2)->nullable()->after('quantidade_prevista');
            $table->decimal('quantidade_excesso', 12, 2)->nullable()->after('quantidade_realizada');
            $table->string('turno_predominante', 10)->nullable()->after('quantidade_excesso');
        });
    }

    public function down(): void
    {
        Schema::table('divergencias_op', function (Blueprint $table) {
            $table->dropColumn(['quantidade_prevista', 'quantidade_realizada', 'quantidade_excesso', 'turno_predominante']);
        });
    }
};
