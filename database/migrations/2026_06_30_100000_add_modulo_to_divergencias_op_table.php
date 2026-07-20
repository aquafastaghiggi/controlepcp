<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('divergencias_op', function (Blueprint $table) {
            $table->string('modulo', 20)->default('envase')->after('id');
            $table->string('tipo', 30)->default('op_nao_programada')->after('modulo');
        });
    }

    public function down(): void
    {
        Schema::table('divergencias_op', function (Blueprint $table) {
            $table->dropColumn(['modulo', 'tipo']);
        });
    }
};
