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

            $table->foreignId('chat_session_id')->index()
                ->constrained('chat_sessions')->cascadeOnDelete();
            $table->foreignId('chat_conversation_id')->index()
                ->constrained('chat_conversations')->cascadeOnDelete();
            $table->string('name')->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();

            $table->unique(['agent_instagram_account_id', 'igsid']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('instagram_threads');
    }
};
