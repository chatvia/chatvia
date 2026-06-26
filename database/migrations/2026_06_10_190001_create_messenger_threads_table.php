<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messenger_threads', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('agent_messenger_page_id')->constrained()->cascadeOnDelete();
            $table->string('psid');

            $table->foreignId('chat_session_id')->index()
                ->constrained('chat_sessions')->cascadeOnDelete();
            $table->foreignId('chat_conversation_id')->index()
                ->constrained('chat_conversations')->cascadeOnDelete();
            $table->string('first_name')->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();

            $table->unique(['agent_messenger_page_id', 'psid']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messenger_threads');
    }
};
