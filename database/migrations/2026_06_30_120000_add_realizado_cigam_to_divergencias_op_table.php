<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('divergencias_op', function (Blueprint $table) {
            $table->decimal('quantidade_realizada_cigam', 12, 2)->nullable()->after('quantidade_realizada');
        });
    }

    public function down(): void
    {
        Schema::table('divergencias_op', function (Blueprint $table) {
            $table->dropColumn('quantidade_realizada_cigam');
        });
    }
};
