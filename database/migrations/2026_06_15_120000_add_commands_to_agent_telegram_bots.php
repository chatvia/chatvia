<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agent_telegram_bots', function (Blueprint $table): void {
            // The agent's Telegram commands: a list of {command, description, type,
            // value}, where type is "message" (canned text) or "prompt" (run through
            // the agent). Holds the built-in overrides (/start, /reset, /help) plus
            // any custom commands; built-in defaults are merged in at read time.
            $table->jsonb('commands')->nullable()->after('enabled');
        });
    }

    public function down(): void
    {
        Schema::table('agent_telegram_bots', function (Blueprint $table): void {
            $table->dropColumn('commands');
        });
    }
};
