<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One Telegram bot per agent (the agent's Telegram "channel"). The bot token is
 * stored encrypted (it's a write-capable secret); `webhook_secret` is the random
 * value that appears in the public webhook URL and the
 * `X-Telegram-Bot-Api-Secret-Token` header — that's how an incoming update is
 * routed to the right agent and verified.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_telegram_bots', function (Blueprint $table): void {
            $table->id();
            $table->uuid('external_id')->unique();
            $table->foreignId('agent_id')->unique()->constrained()->cascadeOnDelete();
            $table->text('bot_token'); // encrypted at the model layer
            $table->string('bot_username')->nullable();
            $table->string('webhook_secret', 64)->unique();
            $table->boolean('enabled')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_telegram_bots');
    }
};
