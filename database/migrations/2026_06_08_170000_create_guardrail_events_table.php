<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Immutable log of every guardrail trigger (PII / prompt-injection / blocked
 * topic / toxicity) on the live chat. A clean pass writes nothing; only blocks,
 * redactions, and flags land here. Agent/user names are denormalized so a row
 * stays readable after the agent or visitor is deleted — same shape as
 * `agent_activity`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guardrail_events', function (Blueprint $table): void {
            $table->id();
            $table->uuid('external_id')->unique();
            $table->foreignId('agent_id')->nullable()->constrained()->nullOnDelete();
            $table->string('agent_name');
            // Tenant scope — the log dies with the workspace (GDPR: no
            // orphaned snippets/names after account deletion).
            $table->foreignId('workspace_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('user_name')->nullable(); // null ⇒ visitor

            $table->uuid('conversation_external_id')->nullable();
            $table->uuid('message_id')->nullable(); // the user/assistant message id the guard ran on
            $table->string('trace_id')->nullable();

            $table->enum('stage', ['input', 'source', 'output']);
            $table->enum('guard_type', ['prompt_injection', 'pii', 'blocked_topic', 'toxicity']);
            $table->enum('outcome', ['blocked', 'redacted', 'flagged']);
            // 'none' is a real classifier verdict level, not just absence.
            $table->enum('severity', ['none', 'low', 'medium', 'high'])->nullable();

            $table->jsonb('detail')->nullable();   // pii_types / risk / reason / categories
            $table->text('snippet')->nullable();   // redacted + size-capped excerpt
            $table->string('snippet_sha256', 64)->nullable(); // hash of the ORIGINAL text (integrity)

            $table->timestampTz('created_at')->useCurrent(); // immutable; no updated_at

            $table->index('user_id');
            $table->index(['workspace_id', 'created_at']);
            $table->index(['agent_id', 'id']); // per-agent recent lookup (order by id desc)
            $table->index('guard_type');
            $table->index('stage');
            $table->index('outcome');
        });

        // Cheap insert-order index for the retention prune's
        // `WHERE created_at < ?` sweep — BRIN, since rows arrive in time order.
        DB::statement('CREATE INDEX guardrail_events_created_at_brin ON guardrail_events USING brin (created_at)');
    }

    public function down(): void
    {
        Schema::dropIfExists('guardrail_events');
    }
};
