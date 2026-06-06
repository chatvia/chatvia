<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Many-to-many link between a conversation and its topics. Each row is
        // EITHER a committed topic (topic_id) OR an emergent LLM suggestion
        // awaiting promotion (suggested_label) — never both. A conversation can
        // carry several of each (multi-label).
        Schema::create('conversation_topics', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('chat_conversation_id')->constrained('chat_conversations')->cascadeOnDelete();
            $table->foreignId('topic_id')->nullable()->constrained('topics')->cascadeOnDelete();
            $table->string('suggested_label')->nullable();

            // Postgres treats NULLs as distinct, so these two uniques coexist:
            // multiple committed rows (suggested_label NULL) and multiple
            // suggestion rows (topic_id NULL) per conversation are allowed,
            // while a given (conversation, topic) / (conversation, label) is unique.
            $table->unique(['chat_conversation_id', 'topic_id']);
            $table->unique(['chat_conversation_id', 'suggested_label']);
            $table->index('topic_id');
            $table->index('suggested_label');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversation_topics');
    }
};
