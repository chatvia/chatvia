<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::statement('CREATE EXTENSION IF NOT EXISTS vector;');
        DB::statement('CREATE EXTENSION IF NOT EXISTS pg_tokenizer CASCADE;');
        DB::statement('CREATE EXTENSION IF NOT EXISTS vchord_bm25 CASCADE;');

        // Check if create_tokenizer function exists before calling it
        $functionExists = DB::selectOne("
            SELECT 1 as found
            FROM pg_catalog.pg_proc p
            JOIN pg_catalog.pg_namespace n ON n.oid = p.pronamespace
            WHERE p.proname = 'create_tokenizer'
        ");
        if ($functionExists->found != 1) {
            DB::statement("SELECT create_tokenizer('llmlingua2', 'model = \"llmlingua2\"');");
        }
        //        DB::statement("SELECT create_tokenizer('llmlingua2', $$ model = \"llmlingua2\"  # using pre-trained model $$);")
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::statement('DROP EXTENSION vector');
    }
};
