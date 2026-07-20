<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const INDEX_NAME = 'idx_inicio_tipo';

    private const TABLE = 'codi_eventos';

    /**
     * Adds a composite index on (inicio_evento, tipo_evento) to support the
     * production history batch query that scans ALL resources across a date
     * range and groups by resource, event type, and day:
     *
     *   WHERE inicio_evento >= ?
     *     AND tipo_evento IN ('PRODUCAO', 'PARADA')
     *   GROUP BY codigo_recurso, tipo_evento, DATE(inicio_evento)
     *
     * Column order rationale: inicio_evento leads because it carries the range
     * predicate, allowing MySQL to perform a single index range scan.
     * tipo_evento follows so MySQL can apply it as an Index Condition Pushdown
     * (ICP) filter before fetching the full row — avoiding a full table scan
     * that the existing (codigo_recurso, inicio_evento) index would require
     * when no equality predicate on codigo_recurso is present.
     */
    public function up(): void
    {
        if ($this->indexExists()) {
            return;
        }

        Schema::table(self::TABLE, function (Blueprint $table): void {
            $table->index(['inicio_evento', 'tipo_evento'], self::INDEX_NAME);
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
