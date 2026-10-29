<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_activities', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('agent_id')->nullable();
            $table->string('agent_name');
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();

            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('chatter_id')->nullable()->constrained()->nullOnDelete();
            $table->string('user_name')->nullable();

            $table->uuid('conversation_external_id')->nullable();
            $table->string('trace_id');
            $table->string('request_id')->nullable();
            $table->string('tool_call_id');

            $table->string('tool_name');
            $table->enum('tool_kind', ['mcp', 'function']);
            $table->string('connector_slug')->nullable();
            $table->string('connector_name')->nullable();

            $table->enum('outcome', ['auto', 'approved', 'denied', 'rate_limited', 'not_offered']);
            $table->boolean('is_gated')->default(false);
            $table->boolean('is_error')->default(false);
            $table->unsignedInteger('latency_ms')->nullable();

            $table->jsonb('arguments')->nullable();
            $table->text('arguments_preview')->nullable();
            $table->string('arguments_sha256', 64)->nullable();
            $table->boolean('arguments_truncated')->default(false);
            $table->text('result_text')->nullable();
            $table->boolean('result_truncated')->default(false);

            $table->timestamp('decided_at')->nullable();
            $table->text('decision_note')->nullable();

            $table->timestamp('created_at')->useCurrent();

            $table->index(['agent_id', 'id']);
            $table->index(['workspace_id', 'id']);
            $table->index('user_id');
            $table->index('chatter_id');
        });

        DB::statement('ALTER TABLE agent_activities ADD CONSTRAINT agent_activities_workspace_agent_foreign FOREIGN KEY (workspace_id, agent_id) REFERENCES agents (workspace_id, id) ON DELETE SET NULL (agent_id)');

        DB::statement('CREATE INDEX agent_activities_created_at_brin ON agent_activities USING brin (created_at)');
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_activities');
    }
};
