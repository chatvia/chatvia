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
            $table->uuid('session_external_id');
            $table->uuid('conversation_external_id');
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
