<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const INDEX_NAME = 'idx_recurso_inicio';

    private const TABLE = 'codi_eventos';

    /**
     * Adds a composite index on (codigo_recurso, inicio_evento) to speed up
     * historical range queries that filter by resource and time window, such as
     * OEE calculations and event timelines in the performance dashboard.
     */
    public function up(): void
    {
        if ($this->indexExists()) {
            return;
        }

        Schema::table(self::TABLE, function (Blueprint $table): void {
            $table->index(['codigo_recurso', 'inicio_evento'], self::INDEX_NAME);
        });
    }

    public function down(): void
    {
        if (! $this->indexExists()) {
            return;
        }

        Schema::table(self::TABLE, function (Blueprint $table): void {
            $table->dropIndex(self::INDEX_NAME);
        });
    }

    /**
     * Checks whether the named index already exists in the database.
     * Uses information_schema on MySQL/MariaDB and pragma_index_list on SQLite
     * so the migration stays idempotent across all environments.
     */
    private function indexExists(): bool
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            $rows = DB::select(
                "SELECT name FROM pragma_index_list(?) WHERE name = ?",
                [self::TABLE, self::INDEX_NAME]
            );
            return count($rows) > 0;
        }

        $database = DB::connection()->getDatabaseName();

        $count = DB::selectOne(
            'SELECT COUNT(*) AS cnt
             FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = ?
               AND TABLE_NAME   = ?
               AND INDEX_NAME   = ?',
            [$database, self::TABLE, self::INDEX_NAME]
        );

        return (int) ($count->cnt ?? 0) > 0;
    }
};
