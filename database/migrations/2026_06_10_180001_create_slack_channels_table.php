<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('slack_channels', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('agent_slack_app_id')->constrained()->cascadeOnDelete();
            $table->string('channel_id');

            // The session and conversation keys pair with the workspace: a
            // mapping can never point at another workspace's chat.
            $table->foreignId('workspace_id');
            $table->foreignId('chat_session_id')->index();
            $table->foreignId('chat_conversation_id')->index();
            $table->string('channel_name')->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();

            $table->foreign(['workspace_id', 'chat_session_id'])->references(['workspace_id', 'id'])->on('chat_sessions')->cascadeOnDelete();
            $table->foreign(['workspace_id', 'chat_conversation_id'])->references(['workspace_id', 'id'])->on('chat_conversations')->cascadeOnDelete();

            $table->unique(['agent_slack_app_id', 'channel_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('slack_channels');
    }
};
