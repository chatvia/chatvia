<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE INDEX chunks_metadata_gin_idx ON chunks USING GIN (metadata)');
    }

    public function down(): void {}
};
