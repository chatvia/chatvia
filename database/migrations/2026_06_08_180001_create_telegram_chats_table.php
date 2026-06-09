<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Maps a Telegram chat (a bot + Telegram chat_id) to the Chatvia Session +
 * Conversation that carry its history, so a Telegram user keeps context across
 * messages. One row per (bot, chat_id); the Session + Conversation are created
 * on first contact.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('telegram_chats', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('agent_telegram_bot_id')->constrained()->cascadeOnDelete();
            $table->string('chat_id'); // Telegram chat id (large / negative for groups)
            $table->uuid('session_external_id');
            $table->uuid('conversation_external_id');
            $table->string('first_name')->nullable();
            $table->string('username')->nullable();
            $table->timestampTz('last_message_at')->nullable();
            $table->timestampsTz();

            $table->unique(['agent_telegram_bot_id', 'chat_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telegram_chats');
    }
};
