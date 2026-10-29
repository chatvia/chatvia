<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teams_chats', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('agent_teams_bot_id')->constrained()->cascadeOnDelete();
            $table->string('conversation_id', 512);
            $table->text('service_url');
            $table->string('conversation_type')->nullable();

            $table->foreignId('workspace_id');
            $table->foreignId('chat_session_id')->index();
            $table->foreignId('chat_conversation_id')->index();
            $table->string('peer_name')->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();

            $table->foreign(['workspace_id', 'chat_session_id'])->references(['workspace_id', 'id'])->on('chat_sessions')->cascadeOnDelete();
            $table->foreign(['workspace_id', 'chat_conversation_id'])->references(['workspace_id', 'id'])->on('chat_conversations')->cascadeOnDelete();

            $table->unique(['agent_teams_bot_id', 'conversation_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teams_chats');
    }
};
