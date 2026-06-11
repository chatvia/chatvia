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
            // FK'd to the parents' UNIQUE external_id columns so hard purges
            // (retention force-delete) self-clean the mapping; the resolver
            // re-validates against soft deletes before reusing a row.
            $table->foreignUuid('session_external_id')->index()
                ->constrained('chat_sessions', 'external_id')->cascadeOnDelete();
            $table->foreignUuid('conversation_external_id')->index()
                ->constrained('chat_conversations', 'external_id')->cascadeOnDelete();
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
