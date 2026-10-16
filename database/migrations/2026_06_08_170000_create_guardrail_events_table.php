<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guardrail_events', function (Blueprint $table): void {
            $table->id();
            $table->uuid('external_id')->unique();
            // Paired with workspace_id below: an event can never point at
            // another workspace's agent. Deleting the agent keeps the event
            // (the name column remembers whose it was).
            $table->foreignId('agent_id')->nullable();
            $table->string('agent_name');
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            // The person whose turn tripped the guard: a staff account or a
            // chatter (at most one). The name stays after either is deleted.
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('chatter_id')->nullable()->constrained()->nullOnDelete();
            $table->string('user_name')->nullable();
            // An anonymous visitor's fingerprint (VisitorFingerprint): a keyed
            // hash of their network address and browser, so repeat attacks from
            // one visitor can be seen and blocked. Null for chatters, staff and
            // the messaging channels.
            $table->string('visitor_key', 64)->nullable();

            $table->uuid('conversation_external_id')->nullable();
            $table->uuid('message_id')->nullable();
            $table->string('trace_id')->nullable();

            $table->enum('stage', ['input', 'source', 'tool', 'output']);
            $table->enum('guard_type', ['prompt_injection', 'pii', 'blocked_topic', 'toxicity', 'prompt_leak']);
            $table->enum('outcome', ['blocked', 'redacted', 'flagged', 'skipped']);

            $table->enum('severity', ['none', 'low', 'medium', 'high'])->nullable();

            $table->jsonb('detail')->nullable();
            $table->text('snippet')->nullable();
            $table->string('snippet_sha256', 64)->nullable();

            $table->timestamp('created_at')->useCurrent();

            $table->index('user_id');
            $table->index('chatter_id');
            $table->index(['workspace_id', 'created_at']);
            $table->index(['agent_id', 'id']);
            $table->index(['workspace_id', 'visitor_key', 'created_at']);
        });

        // Deleting the agent keeps the events and clears only the agent link
        // (Postgres can null one column of a composite key).
        DB::statement('ALTER TABLE guardrail_events ADD CONSTRAINT guardrail_events_workspace_agent_foreign FOREIGN KEY (workspace_id, agent_id) REFERENCES agents (workspace_id, id) ON DELETE SET NULL (agent_id)');

        DB::statement('CREATE INDEX guardrail_events_created_at_brin ON guardrail_events USING brin (created_at)');
    }

    public function down(): void
    {
        Schema::dropIfExists('guardrail_events');
    }
};
