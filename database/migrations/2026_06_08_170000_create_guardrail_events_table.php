<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
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
            $table->unsignedBigInteger('workspace_id');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('user_name')->nullable(); // null ⇒ visitor

            $table->string('conversation_external_id')->nullable();
            $table->string('message_id')->nullable(); // the user/assistant message id the guard ran on
            $table->string('trace_id')->nullable();

            $table->string('stage', 16);     // input | source | output
            $table->string('guard_type', 32); // prompt_injection | pii | blocked_topic | toxicity
            $table->string('outcome', 16);   // blocked | redacted | flagged
            $table->string('severity', 16)->nullable(); // low | medium | high

            $table->jsonb('detail')->nullable();   // pii_types / risk / reason / categories
            $table->text('snippet')->nullable();   // redacted + size-capped excerpt
            $table->string('snippet_sha256', 64)->nullable(); // hash of the ORIGINAL text (integrity)

            $table->timestampTz('created_at')->nullable(); // immutable; no updated_at

            $table->index('agent_id');
            $table->index('user_id');
            $table->index(['workspace_id', 'created_at']);
            $table->index(['agent_id', 'id']); // per-agent recent lookup (order by id desc)
            $table->index('guard_type');
            $table->index('stage');
            $table->index('outcome');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guardrail_events');
    }
};
