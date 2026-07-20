<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('frascos', function (Blueprint $table) {
            $table->string('molde', 50)->nullable()->after('material');
            $table->string('cor', 50)->nullable()->after('molde');
        });
    }

    public function down(): void
    {
        Schema::table('frascos', function (Blueprint $table) {
            $table->dropColumn(['molde', 'cor']);
        });
    }
};
