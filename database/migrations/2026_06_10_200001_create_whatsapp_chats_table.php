<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_chats', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('agent_whatsapp_number_id')->constrained()->cascadeOnDelete();
            $table->string('wa_id');

            $table->foreignId('chat_session_id')->index()
                ->constrained('chat_sessions')->cascadeOnDelete();
            $table->foreignId('chat_conversation_id')->index()
                ->constrained('chat_conversations')->cascadeOnDelete();
            $table->string('name')->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();

            $table->unique(['agent_whatsapp_number_id', 'wa_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_chats');
    }
};
