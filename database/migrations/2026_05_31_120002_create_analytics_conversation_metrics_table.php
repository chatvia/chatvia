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
        Schema::connection($this->getConnection())->create('analytics_conversation_metrics', function (Blueprint $table): void {
            $table->uuid('conversation_external_id')->primary();
            $table->unsignedBigInteger('agent_id');
            $table->unsignedBigInteger('workspace_id');
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();

            $table->timestamp('first_user_at')->nullable();
            $table->timestamp('first_assistant_at')->nullable();
            $table->unsignedInteger('message_count')->default(0);
            $table->unsignedInteger('duration_seconds')->default(0);

            $table->index(['agent_id', 'started_at']);
            $table->index(['workspace_id', 'started_at']);
        });

        // Updated on every message of the conversation: leave room on each
        // page so the update stays in place.
        DB::connection($this->getConnection())->statement('ALTER TABLE analytics_conversation_metrics SET (fillfactor = 80)');
        DB::connection($this->getConnection())->statement('CREATE INDEX analytics_conversation_metrics_started_brin ON analytics_conversation_metrics USING brin (started_at)');
    }

    public function down(): void
    {
        Schema::connection($this->getConnection())->dropIfExists('analytics_conversation_metrics');
    }
};
