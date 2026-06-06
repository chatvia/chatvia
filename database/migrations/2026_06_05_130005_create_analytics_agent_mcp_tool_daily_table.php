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
        Schema::connection($this->getConnection())->create('analytics_agent_mcp_tool_daily', function (Blueprint $table): void {
            $table->unsignedBigInteger('agent_id');
            $table->unsignedBigInteger('workspace_id');
            $table->date('bucket_day');
            // Keyed by (server_slug, tool_name) rather than mcp_tool_id so the
            // rollup is stable across catalog re-syncs (tool ids churn) and
            // survives tool/server deletion.
            $table->string('server_slug');
            $table->string('tool_name');
            $table->unsignedInteger('invocation_count')->default(0);
            // Denormalised labels captured at invocation time.
            $table->string('tool_description', 1024)->nullable();
            $table->string('server_name')->nullable();
            $table->string('connector_slug')->nullable();

            $table->primary(['agent_id', 'bucket_day', 'server_slug', 'tool_name']);
            $table->index(['workspace_id', 'bucket_day']);
        });
    }

    public function down(): void
    {
        Schema::connection($this->getConnection())->dropIfExists('analytics_agent_mcp_tool_daily');
    }
};
