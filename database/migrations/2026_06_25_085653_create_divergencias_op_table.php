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
        Schema::create('divergencias_op', function (Blueprint $table) {
            $table->id();
            $table->string('linha_nome');
            $table->string('linha_codigo');
            $table->string('op_esperada')->nullable();
            $table->string('prod_esperada')->nullable();
            $table->string('op_rodando');
            $table->string('prod_rodando')->nullable();
            $table->timestamp('detectado_em');
            $table->timestamp('resolvida_em')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('divergencias_op');
    }
};
