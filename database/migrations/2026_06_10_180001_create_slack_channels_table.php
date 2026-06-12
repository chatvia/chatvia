<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Maps a Slack conversation (an app + Slack channel id — a public/private
 * channel or a DM) to the Chatvia Session + Conversation that carry its
 * history, so a Slack channel keeps context across messages. One row per
 * (app, channel_id); the Session + Conversation are created on first contact.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('slack_channels', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('agent_slack_app_id')->constrained()->cascadeOnDelete();
            $table->string('channel_id'); // Slack conversation id (C… channel, G… group, D… DM)
            // Integer FKs to the Chatvia session + conversation; resolvers re-validate soft deletes.
            $table->foreignId('chat_session_id')->index()
                ->constrained('chat_sessions')->cascadeOnDelete();
            $table->foreignId('chat_conversation_id')->index()
                ->constrained('chat_conversations')->cascadeOnDelete();
            $table->string('channel_name')->nullable(); // looked up once at first contact (best-effort)
            $table->timestampTz('last_message_at')->nullable();
            $table->timestampsTz();

            $table->unique(['agent_slack_app_id', 'channel_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('slack_channels');
    }
};
