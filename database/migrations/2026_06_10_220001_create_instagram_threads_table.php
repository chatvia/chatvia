<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Maps an Instagram Direct thread (account + Instagram-scoped user id) to the
 * Chatvia Session + Conversation that carry its history, so an Instagram user
 * keeps context across messages. One row per (account, igsid); the Session +
 * Conversation are created on first contact.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('instagram_threads', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('agent_instagram_account_id')->constrained()->cascadeOnDelete();
            $table->string('igsid'); // Instagram-scoped id of the messaging user
            $table->uuid('session_external_id');
            $table->uuid('conversation_external_id');
            $table->string('name')->nullable(); // looked up once at first contact (best-effort)
            $table->timestampTz('last_message_at')->nullable();
            $table->timestampsTz();

            $table->unique(['agent_instagram_account_id', 'igsid']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('instagram_threads');
    }
};
