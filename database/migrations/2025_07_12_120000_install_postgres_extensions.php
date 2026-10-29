<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $extensions = [
            'pg_stat_statements',
            'pg_trgm',
            'btree_gin',
            'btree_gist',
            'vector',
        ];

        foreach ($extensions as $extension) {
            DB::statement("CREATE EXTENSION IF NOT EXISTS {$extension} CASCADE");
        }

        rescue(fn () => DB::statement('CREATE EXTENSION IF NOT EXISTS pg_textsearch'), report: false);
    }

    public function down(): void {}
};
