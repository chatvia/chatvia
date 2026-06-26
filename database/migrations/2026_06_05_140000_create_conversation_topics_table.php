<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {

        Schema::create('conversation_topics', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('chat_conversation_id')->constrained('chat_conversations')->cascadeOnDelete();
            $table->foreignId('topic_id')->nullable()->constrained('topics')->cascadeOnDelete();
            $table->string('suggested_label')->nullable();

            $table->unique(['chat_conversation_id', 'topic_id']);
            $table->unique(['chat_conversation_id', 'suggested_label']);
            $table->index('topic_id');

        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversation_topics');
    }
};
