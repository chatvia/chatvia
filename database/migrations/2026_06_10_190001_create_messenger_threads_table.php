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
            // FK'd to the parents' UNIQUE external_id columns so hard purges
            // (retention force-delete) self-clean the mapping; the resolver
            // re-validates against soft deletes before reusing a row.
            $table->foreignUuid('session_external_id')->index()
                ->constrained('chat_sessions', 'external_id')->cascadeOnDelete();
            $table->foreignUuid('conversation_external_id')->index()
                ->constrained('chat_conversations', 'external_id')->cascadeOnDelete();
            $table->string('first_name')->nullable(); // looked up once at first contact (best-effort)
            $table->timestampTz('last_message_at')->nullable();
            $table->timestampsTz();

            $table->unique(['agent_messenger_page_id', 'psid']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messenger_threads');
    }
};
