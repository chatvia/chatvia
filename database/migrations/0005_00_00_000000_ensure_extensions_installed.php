<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->checkRequiredExtensions();
    }

    public function down(): void
    {

    }

    private function listInstalledExtensions(): array
    {
        $extensions = DB::select("
        SELECT extname
        FROM pg_extension
    ");

        return collect($extensions)->pluck('extname')->toArray();
    }

    private function checkRequiredExtensions(): void
    {
        $required = [
            'btree_gin',
            'btree_gist',
            'pg_tokenizer',
            'pg_trgm',
            'plpgsql',
            'vchord',
            'vchord_bm25',
            'vector',
        ];

        $installed = $this->listInstalledExtensions();

        $missing = array_diff($required, $installed);

        if (count($missing) !== 0) {
            throw new Exception('Missing required PostgreSQL extensions: '. implode(', ', $missing));
        }
    }
};
