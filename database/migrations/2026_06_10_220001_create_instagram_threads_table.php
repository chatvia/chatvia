<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('instagram_threads', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('agent_instagram_account_id')->constrained()->cascadeOnDelete();
            $table->string('igsid');

            $table->foreignId('workspace_id');
            $table->foreignId('chat_session_id')->index();
            $table->foreignId('chat_conversation_id')->index();
            $table->string('name')->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();

            $table->foreign(['workspace_id', 'chat_session_id'])->references(['workspace_id', 'id'])->on('chat_sessions')->cascadeOnDelete();
            $table->foreign(['workspace_id', 'chat_conversation_id'])->references(['workspace_id', 'id'])->on('chat_conversations')->cascadeOnDelete();

            $table->unique(['agent_instagram_account_id', 'igsid']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('instagram_threads');
    }
};
