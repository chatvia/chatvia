<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Messenger moved from per-customer Facebook apps (paste page token + app
 * secret) to one Chatvia Meta app + Facebook Login for Business. The per-page
 * `app_secret` and `webhook_secret` are no longer written — HMAC verification
 * and the webhook verify token are now app-level config, and inbound events
 * route by `page_id` (a single app-level Callback URL). Make the two now-dead
 * columns nullable, and enforce one-agent-per-page so `page_id` routing is
 * unambiguous.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agent_messenger_pages', function (Blueprint $table): void {
            $table->text('app_secret')->nullable()->change();
            $table->string('webhook_secret', 64)->nullable()->change();
            $table->unique('page_id');
        });
    }

    public function down(): void
    {
        Schema::table('agent_messenger_pages', function (Blueprint $table): void {
            $table->dropUnique(['page_id']);
            $table->string('webhook_secret', 64)->nullable(false)->change();
            $table->text('app_secret')->nullable(false)->change();
        });
    }
};
