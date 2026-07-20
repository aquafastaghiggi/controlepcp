<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Remove duplicatas mantendo o registro mais recente (maior id)
        DB::statement('
            DELETE ce1 FROM codi_eficiencia ce1
            INNER JOIN codi_eficiencia ce2
            WHERE ce1.programacao_id = ce2.programacao_id
              AND ce1.numero_op = ce2.numero_op
              AND ce1.id < ce2.id
        ');

        Schema::table('codi_eficiencia', function (Blueprint $table) {
            $table->unique(['programacao_id', 'numero_op'], 'uq_eficiencia_prog_op');
        });
    }

    public function down(): void
    {
        Schema::table('codi_eficiencia', function (Blueprint $table) {
            $table->dropUnique('uq_eficiencia_prog_op');
        });
    }
};
