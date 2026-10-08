<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Dynamic client registration used to copy the whole registration response
 * into the plain-text `metadata` jsonb column, including `client_secret` and
 * `registration_access_token`. Both already live in their own encrypted
 * columns and nothing reads them from `metadata`, so remove the plain-text
 * copies.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            UPDATE connector_oauth_clients
            SET metadata = metadata - 'client_secret' - 'registration_access_token'
            WHERE jsonb_typeof(metadata) = 'object'
              AND (metadata -> 'client_secret' IS NOT NULL OR metadata -> 'registration_access_token' IS NOT NULL)
            SQL);
    }

    public function down(): void
    {
        // The removed values were plain-text duplicates of encrypted columns;
        // there is nothing to restore.
    }
};
