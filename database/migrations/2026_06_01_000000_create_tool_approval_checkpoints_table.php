<?php

use Domain\Agent\Models\Agent;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A paused agent turn waiting on human approval of one or more tool calls.
 *
 * The chat stream stops at the tool boundary, persists everything needed to
 * resume (the full `messages_snapshot`, the `pending_calls`, and a `context`
 * bundle to rebuild the request), and ends the SSE response. When the user
 * approves/denies, a fresh request loads this row, applies the decisions, and
 * continues the same agentic loop. Resolves exactly once.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tool_approval_checkpoints', function (Blueprint $table) {
            $table->id();
            $table->uuid('external_id')->unique();

            $table->foreignIdFor(Agent::class)->constrained()->cascadeOnDelete();

            $table->string('conversation_external_id');
            $table->uuid('assistant_message_id');
            $table->string('request_id')->nullable();

            // Which tool-loop iteration we paused on, so resume continues the
            // budget from where it stopped.
            $table->unsignedSmallInteger('iteration')->default(0);

            // pending | resolved | expired. Guarded transactionally so a
            // double-submit can never run the tools twice.
            $table->string('status', 16)->default('pending');

            // The exact `$llmMessages` array at pause time (includes the
            // assistant turn carrying the tool_calls).
            $table->jsonb('messages_snapshot');

            // Every tool call in the paused turn: {tool_call_id, name,
            // arguments, requires_approval, label, icon_url, brand_color,
            // kind, reason}. Cards render only for requires_approval=true.
            $table->jsonb('pending_calls');

            // Everything needed to rebuild StreamRequestData/ChatContext on
            // resume without creating new messages: {external_session_id,
            // model_id, user_message, query_language, source, stream_sources,
            // preface}.
            $table->jsonb('context');

            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['conversation_external_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tool_approval_checkpoints');
    }
};
