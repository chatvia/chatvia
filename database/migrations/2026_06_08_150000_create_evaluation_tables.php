<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {

        Schema::create('eval_cases', function (Blueprint $table): void {
            $table->id();
            $table->uuid('external_id')->unique();
            // Paired with workspace_id below: a case can never point at another
            // workspace's agent. Deleting the agent keeps the case (the name
            // column remembers whose it was).
            $table->foreignId('agent_id')->nullable();
            $table->string('agent_name');
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('input');
            $table->enum('scorer_type', ['contains', 'regex', 'exact', 'json_schema', 'llm_judge', 'tool_call', 'citations']);
            $table->jsonb('scorer_config')->nullable();
            $table->text('expected_output')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('agent_id');
            $table->index('workspace_id');

        });

        // Deleting the agent keeps the cases and clears only the agent link
        // (Postgres can null one column of a composite key).
        DB::statement('ALTER TABLE eval_cases ADD CONSTRAINT eval_cases_workspace_agent_foreign FOREIGN KEY (workspace_id, agent_id) REFERENCES agents (workspace_id, id) ON DELETE SET NULL (agent_id)');

        Schema::create('eval_runs', function (Blueprint $table): void {
            $table->id();
            $table->uuid('external_id')->unique();
            // Paired with workspace_id below, like eval_cases.
            $table->foreignId('agent_id')->nullable();
            $table->string('agent_name');
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->enum('status', ['running', 'completed', 'failed'])->default('running');
            $table->unsignedInteger('total_cases')->default(0);
            $table->unsignedInteger('passed_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->decimal('avg_score', 5, 4)->nullable();
            $table->unsignedBigInteger('total_tokens')->nullable();
            $table->string('model_snapshot')->nullable();
            $table->foreignId('triggered_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('triggered_by_name')->nullable();
            $table->uuid('batch_id')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['agent_id', 'id']);
            $table->index('workspace_id');
        });

        DB::statement('ALTER TABLE eval_runs ADD CONSTRAINT eval_runs_workspace_agent_foreign FOREIGN KEY (workspace_id, agent_id) REFERENCES agents (workspace_id, id) ON DELETE SET NULL (agent_id)');
        // Scheduled runs have no triggering person, so most rows are NULL here.
        DB::statement('CREATE INDEX eval_runs_triggered_by_user_idx ON eval_runs (triggered_by_user_id) WHERE triggered_by_user_id IS NOT NULL');

        Schema::create('eval_results', function (Blueprint $table): void {
            $table->id();
            $table->uuid('external_id')->unique();
            $table->foreignId('run_id')->constrained('eval_runs')->cascadeOnDelete();
            $table->foreignId('case_id')->nullable()->constrained('eval_cases')->nullOnDelete();
            $table->string('case_name');
            $table->enum('scorer_type', ['contains', 'regex', 'exact', 'json_schema', 'llm_judge', 'tool_call', 'citations']);
            $table->boolean('has_passed')->default(false);
            $table->decimal('score', 5, 4)->nullable();
            $table->text('actual_output')->nullable();
            $table->string('actual_output_sha256', 64)->nullable();
            $table->boolean('actual_output_truncated')->default(false);
            $table->jsonb('sources_used')->nullable();
            $table->jsonb('source_refs')->nullable();
            $table->jsonb('tools_called')->nullable();
            $table->text('judge_rationale')->nullable();
            $table->unsignedInteger('latency_ms')->nullable();
            $table->unsignedInteger('tokens')->nullable();
            $table->string('trace_id')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['run_id', 'has_passed']);
            $table->index('case_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('eval_results');
        Schema::dropIfExists('eval_runs');
        Schema::dropIfExists('eval_cases');
    }
};
