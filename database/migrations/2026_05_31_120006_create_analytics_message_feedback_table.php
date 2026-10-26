<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function getConnection(): ?string
    {
        return config('chatvia.analytics.connection');
    }

    public function up(): void
    {
        Schema::connection($this->getConnection())->create('analytics_message_feedback', function (Blueprint $table): void {
            $table->uuid('message_external_id')->primary();
            $table->unsignedBigInteger('agent_id');
            $table->unsignedBigInteger('workspace_id');
            $table->unsignedBigInteger('chatter_id')->nullable();
            $table->uuid('conversation_external_id')->nullable();
            $table->enum('feedback', ['positive', 'negative']);
            $table->timestamp('occurred_at');
        });

        DB::connection($this->getConnection())->statement('CREATE INDEX analytics_message_feedback_workspace_index ON analytics_message_feedback (workspace_id, occurred_at) INCLUDE (agent_id, feedback)');
        DB::connection($this->getConnection())->statement('CREATE INDEX analytics_message_feedback_agent_index ON analytics_message_feedback (agent_id) INCLUDE (feedback, conversation_external_id)');
    }

    public function down(): void
    {
        Schema::connection($this->getConnection())->dropIfExists('analytics_message_feedback');
    }
};
