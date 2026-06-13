<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Maps a Teams conversation (a bot + Teams conversation id — a personal chat,
 * group chat, or channel thread) to the Chatvia Session + Conversation that
 * carry its history, so a Teams chat keeps context across messages. One row
 * per (bot, conversation_id); the Session + Conversation are created on first
 * contact. `service_url` is the regional Bot Framework host replies are sent
 * to — it arrives on every activity and is refreshed on each message.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teams_chats', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('agent_teams_bot_id')->constrained()->cascadeOnDelete();
            $table->string('conversation_id', 512); // Teams id ("a:…" personal, "19:…@thread.tacv2;messageid=…" channel thread)
            $table->text('service_url');
            $table->string('conversation_type')->nullable(); // personal | groupChat | channel
            // Integer FKs to the Chatvia session + conversation; resolvers re-validate soft deletes.
            $table->foreignId('chat_session_id')->index()
                ->constrained('chat_sessions')->cascadeOnDelete();
            $table->foreignId('chat_conversation_id')->index()
                ->constrained('chat_conversations')->cascadeOnDelete();
            $table->string('peer_name')->nullable(); // the sender's display name at first contact (personal chats)
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();

            $table->unique(['agent_teams_bot_id', 'conversation_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teams_chats');
    }
};
