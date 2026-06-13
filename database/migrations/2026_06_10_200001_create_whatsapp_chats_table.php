<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Maps a WhatsApp chat (a business number + the user's wa_id) to the Chatvia
 * Session + Conversation that carry its history, so a WhatsApp user keeps
 * context across messages. One row per (number, wa_id); the Session +
 * Conversation are created on first contact.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_chats', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('agent_whatsapp_number_id')->constrained()->cascadeOnDelete();
            $table->string('wa_id'); // the user's WhatsApp id (their phone number, normalized by Meta)
            // Integer FKs to the Chatvia session + conversation; resolvers re-validate soft deletes.
            $table->foreignId('chat_session_id')->index()
                ->constrained('chat_sessions')->cascadeOnDelete();
            $table->foreignId('chat_conversation_id')->index()
                ->constrained('chat_conversations')->cascadeOnDelete();
            $table->string('name')->nullable(); // profile name — arrives free in the webhook payload
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();

            $table->unique(['agent_whatsapp_number_id', 'wa_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_chats');
    }
};
