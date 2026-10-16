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
            // The conversation key pairs with the workspace: an assignment can
            // never point at another workspace's conversation. topic_id keeps a
            // plain key because system topics have no workspace.
            $table->foreignId('workspace_id');
            $table->foreignId('chat_conversation_id');
            $table->foreignId('topic_id')->nullable()->constrained('topics')->cascadeOnDelete();
            $table->string('suggested_label')->nullable();

            $table->foreign(['workspace_id', 'chat_conversation_id'])->references(['workspace_id', 'id'])->on('chat_conversations')->cascadeOnDelete();

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
