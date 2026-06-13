<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Immutable per-tool-call governance audit for the Governed MCP Gateway — one
 * row per tool decision (auto / approved / denied / rate_limited).
 *
 * agent_id and user_id are FKs that null on delete, with denormalized
 * agent_name / user_name kept so a row stays readable after the agent or user
 * is removed. Insert-only (no updated_at).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_activity', function (Blueprint $table): void {
            $table->id();

            // The agent acted through. FK nulls on delete; agent_name survives it.
            $table->foreignId('agent_id')->nullable()->constrained()->nullOnDelete();
            $table->string('agent_name');
            $table->foreignId('workspace_id')->constrained('users')->cascadeOnDelete(); // tenant scope (agent owner)

            // The actor (a chatter). FK nulls on delete; user_name survives it.
            // user_name === null ⇒ anonymous visitor.
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('user_name')->nullable();

            // Correlation.
            $table->uuid('conversation_external_id')->nullable();
            $table->string('trace_id'); // = assistant message id
            $table->string('request_id')->nullable();
            $table->string('tool_call_id');

            // The tool + connector. tool_name is UNPREFIXED (e.g. current-weather-tool).
            $table->string('tool_name');
            $table->enum('tool_kind', ['mcp', 'function']);
            $table->string('connector_slug')->nullable();
            $table->string('connector_name')->nullable();

            // Outcome.
            $table->enum('outcome', ['auto', 'approved', 'denied', 'rate_limited']);
            $table->boolean('gated')->default(false);
            $table->boolean('is_error')->default(false);
            $table->unsignedInteger('latency_ms')->nullable();

            // Payload (redacted + size-capped; sha256 over the raw args for integrity).
            $table->jsonb('arguments')->nullable();
            $table->string('arguments_sha256', 64)->nullable();
            $table->boolean('arguments_truncated')->default(false);
            $table->text('result_text')->nullable();
            $table->boolean('result_truncated')->default(false);

            // When a gated call was resolved (decider == the actor user).
            $table->timestamp('decided_at')->nullable();

            $table->timestamp('created_at')->useCurrent(); // immutable; no updated_at

            // Postgres does not auto-index FK columns — index them explicitly.
            // The (…, id) composites serve the audit pages' ORDER BY id DESC walks.
            $table->index(['agent_id', 'id']);
            $table->index(['workspace_id', 'id']);
            $table->index('user_id');
            $table->index(['workspace_id', 'created_at']);
            $table->index('connector_slug');
            $table->index('outcome');
        });

        // BRIN for the retention prune's created_at sweep.
        DB::statement('CREATE INDEX agent_activity_created_at_brin ON agent_activity USING brin (created_at)');
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_activity');
    }
};
