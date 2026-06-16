<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * WhatsApp moved from a BYO paste-token model to Chatvia's shared Meta app via
 * Embedded Signup: the per-number app secret + webhook secret are no longer
 * collected (the HMAC is verified with the shared app secret and the webhook is
 * a single app-level URL routed by phone number id). Relax those columns to
 * nullable and drop the now-pointless unique index on `webhook_secret`.
 * Forward-only (prod is live) — the columns are kept, not dropped, so any
 * legacy row still round-trips.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agent_whatsapp_numbers', function (Blueprint $table): void {
            $table->dropUnique(['webhook_secret']);
            $table->text('app_secret')->nullable()->change();
            $table->string('webhook_secret', 64)->nullable()->change();
        });

        // Inbound events now route purely by phone_number_id, so at most one
        // ENABLED row may claim a given number — a DB-level backstop behind the
        // controller's race-prone exists() check. Partial (WHERE enabled) so a
        // disabled/legacy row can't block reconnecting the same number.
        DB::statement('CREATE UNIQUE INDEX agent_whatsapp_numbers_phone_number_id_enabled_unique ON agent_whatsapp_numbers (phone_number_id) WHERE enabled');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS agent_whatsapp_numbers_phone_number_id_enabled_unique');

        Schema::table('agent_whatsapp_numbers', function (Blueprint $table): void {
            $table->text('app_secret')->nullable(false)->change();
            $table->string('webhook_secret', 64)->nullable(false)->change();
            $table->unique('webhook_secret');
        });
    }
};
