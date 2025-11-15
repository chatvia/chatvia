<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');
        DB::statement('CREATE EXTENSION IF NOT EXISTS btree_gin');
        DB::statement('CREATE EXTENSION IF NOT EXISTS btree_gist');
    }

    public function down(): void
    {
        rescue(fn () => DB::statement('DROP EXTENSION IF EXISTS pg_trgm CASCADE'));
        rescue(fn () => DB::statement('DROP EXTENSION IF EXISTS btree_gin CASCADE'));
        rescue(fn () => DB::statement('DROP EXTENSION IF EXISTS btree_gist CASCADE'));
    }
};
