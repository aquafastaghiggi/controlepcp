<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE programacoes MODIFY COLUMN status ENUM('rascunho','calculada','confirmada','cancelada','arquivada') NOT NULL DEFAULT 'rascunho'");

        DB::statement("ALTER TABLE programacoes ADD COLUMN arquivada_em TIMESTAMP NULL DEFAULT NULL AFTER status");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE programacoes DROP COLUMN arquivada_em");

        DB::statement("ALTER TABLE programacoes MODIFY COLUMN status ENUM('rascunho','calculada','confirmada','cancelada') NOT NULL DEFAULT 'rascunho'");
    }
};
