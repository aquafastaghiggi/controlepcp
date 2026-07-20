<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kpis_diarios', function (Blueprint $table) {
            $table->id();
            $table->date('data')->unique();
            $table->string('modulo', 20)->default('envase');
            $table->integer('previsto_hoje')->default(0);
            $table->timestamp('calculado_em')->nullable();
            $table->timestamps();

            $table->unique(['data', 'modulo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kpis_diarios');
    }
};
