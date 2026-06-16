<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Path B: Instagram moves to Facebook Login for Business (shared Chatvia Meta
 * app). The channel now stores the linked Facebook Page id + page access token
 * (encrypted) and routes inbound events by the IG account id (`ig_id`). The old
 * per-account `access_token` / `app_secret` / `webhook_secret` are kept but made
 * nullable so already-connected rows survive; new OAuth connections leave them
 * null (HMAC + verify token are app-level config now).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agent_instagram_accounts', function (Blueprint $table): void {
            $table->text('page_access_token')->nullable()->after('app_secret'); // encrypted at the model layer
            $table->string('page_id')->nullable()->after('page_access_token');
        });

        // Legacy per-account secrets are no longer written under OAuth.
        DB::statement('ALTER TABLE agent_instagram_accounts ALTER COLUMN access_token DROP NOT NULL');
        DB::statement('ALTER TABLE agent_instagram_accounts ALTER COLUMN app_secret DROP NOT NULL');

        Schema::table('agent_instagram_accounts', function (Blueprint $table): void {
            // webhook_secret is no longer the routing key — drop its unique +
            // NOT NULL so OAuth rows can omit it.
            $table->dropUnique('agent_instagram_accounts_webhook_secret_unique');
        });
        DB::statement('ALTER TABLE agent_instagram_accounts ALTER COLUMN webhook_secret DROP NOT NULL');

        Schema::table('agent_instagram_accounts', function (Blueprint $table): void {
            // New routing key: one IG account per row, globally unique.
            $table->unique('ig_id');
        });
    }

    public function down(): void
    {
        Schema::table('agent_instagram_accounts', function (Blueprint $table): void {
            $table->dropUnique('agent_instagram_accounts_ig_id_unique');
            $table->dropColumn(['page_access_token', 'page_id']);
        });
    }
};
