<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        rescue(fn() => DB::statement('SET search_path = public, bm25_catalog, tokenizer_catalog'));

        DB::statement('CREATE EXTENSION IF NOT EXISTS vector');
        DB::statement('CREATE EXTENSION IF NOT EXISTS pg_tokenizer CASCADE');
        DB::statement('CREATE EXTENSION IF NOT EXISTS vchord_bm25 CASCADE');
        DB::statement('CREATE EXTENSION IF NOT EXISTS vchord CASCADE');
        DB::statement('CREATE EXTENSION IF NOT EXISTS vchord CASCADE');

        // Only create the tokenizer if it doesn't exist
        rescue(function () {
            DB::statement("SELECT create_tokenizer('llmlingua2', $$ 'model' = \"llmlingua2\"  # using pre-trained model $$);");
        }, function ($e) {
            if (! str_contains($e->getMessage(), 'Tokenizer already exists: llmlingua2')) {
                throw $e; // Re-throw if it's a different error
            }
        });
    }

    public function down()
    {
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
