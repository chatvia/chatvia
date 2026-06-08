<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agent evaluation suites (Governed MCP Gateway → #9 Agent Governance + Evals,
 * phase 1). Test cases are defined per agent, run through the agent's REAL
 * pipeline off-request, and scored; runs + per-case results are stored so a
 * pass-rate trend (regression) is visible over time.
 *
 * agent_id is an FK that nulls on delete, with a denormalized agent_name kept so
 * rows stay readable after the agent is removed — same pattern as agent_activity.
 */
return new class extends Migration
{
    public function up(): void
    {
        // A single test case: an input prompt + how to score the answer.
        Schema::create('eval_cases', function (Blueprint $table): void {
            $table->id();
            $table->uuid('external_id')->unique(); // stable, non-guessable id used in routes
            $table->foreignId('agent_id')->nullable()->constrained()->nullOnDelete();
            $table->string('agent_name');
            $table->unsignedBigInteger('workspace_id');
            $table->string('name');
            $table->text('input'); // the test prompt sent to the agent
            $table->string('scorer_type', 32); // contains | regex | exact | json_schema | llm_judge
            $table->jsonb('scorer_config')->nullable(); // expected substring / regex / schema / judge rubric
            $table->text('expected_output')->nullable(); // reference answer for exact / judge
            $table->boolean('active')->default(true);
            $table->timestampsTz();

            $table->index('agent_id');
            $table->index('workspace_id');
        });

        // One execution of an agent's whole suite.
        Schema::create('eval_runs', function (Blueprint $table): void {
            $table->id();
            $table->uuid('external_id')->unique(); // stable, non-guessable id used in routes
            $table->foreignId('agent_id')->nullable()->constrained()->nullOnDelete();
            $table->string('agent_name');
            $table->unsignedBigInteger('workspace_id');
            $table->string('status', 16)->default('running'); // running | completed | failed
            $table->unsignedInteger('total_cases')->default(0);
            $table->unsignedInteger('passed_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->decimal('avg_score', 5, 4)->nullable();
            $table->unsignedBigInteger('total_tokens')->nullable();
            $table->string('model_snapshot')->nullable(); // the agent's LLM at run time
            $table->foreignId('triggered_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('triggered_by_name')->nullable();
            $table->uuid('batch_id')->nullable(); // Bus::batch id
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('finished_at')->nullable();
            $table->timestampsTz();

            $table->index('agent_id');
            $table->index('workspace_id');
            $table->index(['agent_id', 'id']); // previous-run lookup (order by id desc)
        });

        // Immutable per-case result of a run.
        Schema::create('eval_results', function (Blueprint $table): void {
            $table->id();
            $table->uuid('external_id')->unique(); // stable, non-guessable id
            $table->foreignId('run_id')->constrained('eval_runs')->cascadeOnDelete();
            $table->foreignId('case_id')->nullable()->constrained('eval_cases')->nullOnDelete();
            $table->string('case_name');
            $table->string('scorer_type', 32);
            $table->boolean('pass')->default(false);
            $table->decimal('score', 5, 4)->nullable(); // 0..1
            $table->text('actual_output')->nullable(); // the agent's answer (redacted + size-capped)
            $table->string('actual_output_sha256', 64)->nullable();
            $table->boolean('actual_output_truncated')->default(false);
            $table->jsonb('sources_used')->nullable(); // RAG sources the agent retrieved
            $table->jsonb('tools_called')->nullable();
            $table->text('judge_rationale')->nullable();
            $table->unsignedInteger('latency_ms')->nullable();
            $table->unsignedInteger('tokens')->nullable();
            $table->string('trace_id')->nullable(); // = the eval assistant message id (joins spectra_requests)
            $table->timestampTz('created_at')->nullable(); // immutable; no updated_at

            $table->index('run_id');
            $table->index(['run_id', 'pass']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('eval_results');
        Schema::dropIfExists('eval_runs');
        Schema::dropIfExists('eval_cases');
    }
};
