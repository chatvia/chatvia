<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        // Skip if not using PostgreSQL
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('CREATE EXTENSION IF NOT EXISTS pg_stat_statements');
        DB::statement('CREATE EXTENSION IF NOT EXISTS vector');
        DB::statement('CREATE EXTENSION IF NOT EXISTS pg_tokenizer CASCADE');
        DB::statement('CREATE EXTENSION IF NOT EXISTS vchord_bm25 CASCADE');
        DB::statement('CREATE EXTENSION IF NOT EXISTS vchord CASCADE');

        $tokenizerExists = DB::selectOne("
            SELECT EXISTS (
                SELECT 1 FROM information_schema.routines
                WHERE routine_name = 'create_tokenizer'
            ) as exists
        ");

        if (! $tokenizerExists->exists) {
            rescue(function () {
                DB::statement("SELECT tokenizer_catalog.create_tokenizer('llmlingua2', $$ 'model' = 'llmlingua2' $$)");
            }, function ($e) {
                if (! str_contains($e->getMessage(), 'Tokenizer already exists: llmlingua2')) {
                    throw $e;
                }
            });
        }
    }

    public function down()
    {
        // Skip if not using PostgreSQL
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        rescue(fn () => DB::statement("SELECT drop_tokenizer('llmlingua2')"));
        rescue(fn () => DB::statement('DROP EXTENSION IF EXISTS vchord CASCADE'));
        rescue(fn () => DB::statement('DROP EXTENSION IF EXISTS vchord_bm25 CASCADE'));
        rescue(fn () => DB::statement('DROP EXTENSION IF EXISTS pg_tokenizer CASCADE'));
        rescue(fn () => DB::statement('DROP EXTENSION IF EXISTS btree_gist CASCADE'));
        rescue(fn () => DB::statement('DROP EXTENSION IF EXISTS btree_gin CASCADE'));
        rescue(fn () => DB::statement('DROP EXTENSION IF EXISTS pg_trgm CASCADE'));
        rescue(fn () => DB::statement('DROP EXTENSION IF EXISTS vector CASCADE'));
    }
};
