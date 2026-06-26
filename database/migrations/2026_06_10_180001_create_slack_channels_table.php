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

            $table->foreignId('chat_session_id')->index()
                ->constrained('chat_sessions')->cascadeOnDelete();
            $table->foreignId('chat_conversation_id')->index()
                ->constrained('chat_conversations')->cascadeOnDelete();
            $table->string('channel_name')->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();

            $table->unique(['agent_slack_app_id', 'channel_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('slack_channels');
    }
};
