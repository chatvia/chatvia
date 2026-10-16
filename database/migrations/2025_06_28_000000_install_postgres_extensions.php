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

        // pg_textsearch gives the lexical search channels true BM25 ranking.
        // It must be preloaded into Postgres, so a managed database usually
        // cannot have it — hybrid search then falls back to the built-in
        // full-text search (LexicalSearch), and this step quietly skips.
        rescue(fn () => DB::statement('CREATE EXTENSION IF NOT EXISTS pg_textsearch'), report: false);
    }

    public function down(): void
    {
        // The extensions stay: other databases on the server may use them,
        // and dropping `vector` would cascade into every embedding column.
    }
};
