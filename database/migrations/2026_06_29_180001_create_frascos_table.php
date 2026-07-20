<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('frascos', function (Blueprint $table) {
            $table->id();
            $table->string('sku', 20)->unique();
            $table->string('descricao', 255);
            $table->enum('material', ['PEAD', 'PET', 'OUTRO'])->default('PEAD');
            $table->decimal('taxa_por_hora', 10, 2)->nullable();
            $table->foreignId('maquina_id')->nullable()->constrained('maquinas')->nullOnDelete();
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('frascos');
    }
};
