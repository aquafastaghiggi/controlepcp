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
        Schema::table('itens_programacao', function (Blueprint $table) {
            $table->string('numero_op')->nullable()->after('sequencia');
            $table->index('numero_op', 'idx_itens_numero_op');
        });
    }

    public function down(): void
    {
        Schema::table('itens_programacao', function (Blueprint $table) {
            $table->dropIndex('idx_itens_numero_op');
            $table->dropColumn('numero_op');
        });
    }
};
