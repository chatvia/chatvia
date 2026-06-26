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

            $table->foreignId('chat_session_id')->index()
                ->constrained('chat_sessions')->cascadeOnDelete();
            $table->foreignId('chat_conversation_id')->index()
                ->constrained('chat_conversations')->cascadeOnDelete();
            $table->string('peer_name')->nullable();
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
