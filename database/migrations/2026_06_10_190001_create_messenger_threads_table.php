<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Maps a Messenger thread (a page + page-scoped user id) to the Chatvia
 * Session + Conversation that carry its history, so a Messenger user keeps
 * context across messages. One row per (page, psid); the Session +
 * Conversation are created on first contact.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messenger_threads', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('agent_messenger_page_id')->constrained()->cascadeOnDelete();
            $table->string('psid'); // page-scoped user id — stable per (user, page) pair
            // Integer FKs to the Chatvia session + conversation; resolvers re-validate soft deletes.
            $table->foreignId('chat_session_id')->index()
                ->constrained('chat_sessions')->cascadeOnDelete();
            $table->foreignId('chat_conversation_id')->index()
                ->constrained('chat_conversations')->cascadeOnDelete();
            $table->string('first_name')->nullable(); // looked up once at first contact (best-effort)
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();

            $table->unique(['agent_messenger_page_id', 'psid']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messenger_threads');
    }
};
