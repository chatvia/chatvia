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
            // FK'd to the parents' UNIQUE external_id columns so hard purges
            // (retention force-delete) self-clean the mapping; the resolver
            // re-validates against soft deletes before reusing a row.
            $table->foreignUuid('session_external_id')->index()
                ->constrained('chat_sessions', 'external_id')->cascadeOnDelete();
            $table->foreignUuid('conversation_external_id')->index()
                ->constrained('chat_conversations', 'external_id')->cascadeOnDelete();
            $table->string('name')->nullable(); // profile name — arrives free in the webhook payload
            $table->timestampTz('last_message_at')->nullable();
            $table->timestampsTz();

            $table->unique(['agent_whatsapp_number_id', 'wa_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_chats');
    }
};
