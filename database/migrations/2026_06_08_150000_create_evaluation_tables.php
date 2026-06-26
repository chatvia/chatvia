<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {

        Schema::create('eval_cases', function (Blueprint $table): void {
            $table->id();
            $table->uuid('external_id')->unique();
            $table->foreignId('agent_id')->nullable()->constrained()->nullOnDelete();
            $table->string('agent_name');
            $table->foreignId('workspace_id')->constrained('users')->cascadeOnDelete();
            $table->string('name');
            $table->text('input');
            $table->enum('scorer_type', ['contains', 'regex', 'exact', 'json_schema', 'llm_judge']);
            $table->jsonb('scorer_config')->nullable();
            $table->text('expected_output')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index('agent_id');

        });

        Schema::create('eval_runs', function (Blueprint $table): void {
            $table->id();
            $table->uuid('external_id')->unique();
            $table->foreignId('agent_id')->nullable()->constrained()->nullOnDelete();
            $table->string('agent_name');
            $table->foreignId('workspace_id')->constrained('users')->cascadeOnDelete();
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
        });

        Schema::create('eval_results', function (Blueprint $table): void {
            $table->id();
            $table->uuid('external_id')->unique();
            $table->foreignId('run_id')->constrained('eval_runs')->cascadeOnDelete();
            $table->foreignId('case_id')->nullable()->constrained('eval_cases')->nullOnDelete();
            $table->string('case_name');
            $table->enum('scorer_type', ['contains', 'regex', 'exact', 'json_schema', 'llm_judge']);
            $table->boolean('pass')->default(false);
            $table->decimal('score', 5, 4)->nullable();
            $table->text('actual_output')->nullable();
            $table->string('actual_output_sha256', 64)->nullable();
            $table->boolean('actual_output_truncated')->default(false);
            $table->jsonb('sources_used')->nullable();
            $table->jsonb('tools_called')->nullable();
            $table->text('judge_rationale')->nullable();
            $table->unsignedInteger('latency_ms')->nullable();
            $table->unsignedInteger('tokens')->nullable();
            $table->string('trace_id')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['run_id', 'pass']);
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
