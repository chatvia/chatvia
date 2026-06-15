<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agent_telegram_bots', function (Blueprint $table): void {
            // Agent-defined slash commands: a list of {command, description, reply}.
            // Built-in commands (/start, /reset, /help) are code-defined, never stored.
            $table->jsonb('custom_commands')->nullable()->after('enabled');
        });
    }

    public function down(): void
    {
        Schema::table('agent_telegram_bots', function (Blueprint $table): void {
            $table->dropColumn('custom_commands');
        });
    }
};
