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
            'vchord',
            'pg_tokenizer',
            'vchord_bm25',
        ];

        foreach ($extensions as $extension) {
            DB::statement("CREATE EXTENSION IF NOT EXISTS {$extension} CASCADE");
        }

        DB::statement(<<<'SQL'
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM tokenizer_catalog.tokenizer WHERE name = 'llmlingua2'
    ) THEN
        PERFORM tokenizer_catalog.create_tokenizer(
            'llmlingua2', $tk$ model = "llmlingua2" $tk$
        );
    END IF;
END $$;
SQL);

        $database = '"'.str_replace('"', '""', DB::getDatabaseName()).'"';
        DB::statement("ALTER DATABASE {$database} SET vchordrq.prefilter = on");
    }

    public function down(): void
    {
        $database = '"'.str_replace('"', '""', DB::getDatabaseName()).'"';
        DB::statement("ALTER DATABASE {$database} RESET vchordrq.prefilter");
    }
};
