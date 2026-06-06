<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
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
            $table->timestampTz('started_at');
            $table->timestampTz('ended_at')->nullable();
            $table->unsignedInteger('message_count')->default(0);
            $table->unsignedInteger('duration_seconds')->default(0);

            $table->index(['agent_id', 'started_at']);
            $table->index(['workspace_id', 'started_at']);
        });
    }

    public function down(): void
    {
        Schema::connection($this->getConnection())->dropIfExists('analytics_conversation_metrics');
    }
};
