<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function getConnection(): ?string
    {
        return config('chatvia.analytics.connection');
    }

    public function up(): void
    {
        Schema::connection($this->getConnection())->create('analytics_events', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('agent_id');
            // Workspace owner, captured at dispatch time so events stay
            // attributable to the workspace even after the agent is deleted.
            $table->unsignedBigInteger('workspace_id');
            // The chatter (end-user) who made this event — message_sent and
            // feedback_given carry it; aggregate/system events and anonymous
            // widget visitors leave it null. The "who", vs workspace_id's "where".
            $table->unsignedBigInteger('chatter_user_id')->nullable();
            $table->string('event_type', 40);
            $table->string('role', 20)->nullable();
            $table->string('feedback', 20)->nullable();
            $table->string('llm_external_name', 64)->nullable();
            $table->string('country_code', 2)->nullable();
            $table->unsignedInteger('response_time_ms')->nullable();
            $table->unsignedInteger('prompt_tokens')->nullable();
            $table->unsignedInteger('completion_tokens')->nullable();
            $table->unsignedInteger('total_tokens')->nullable();
            $table->uuid('conversation_external_id')->nullable();
            $table->uuid('message_external_id')->nullable();
            $table->uuid('session_external_id')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamp('inserted_at')->useCurrent();

            $table->index(['agent_id', 'occurred_at']);
            $table->index(['agent_id', 'event_type', 'occurred_at']);
            $table->index(['workspace_id', 'occurred_at']);
            $table->index(['chatter_user_id', 'occurred_at']);
        });

        // Feedback is current-state: one row per message, upserted on
        // message_external_id (switching a reaction updates the same row;
        // clearing deletes it). Partial-unique so it constrains only feedback
        // rows, not the message_sent rows that share a message id.
        DB::connection($this->getConnection())->statement(
            "CREATE UNIQUE INDEX analytics_events_feedback_message_unique ON analytics_events (message_external_id) WHERE event_type = 'feedback_given'"
        );

        // Idempotency anchor: RecordMessageSentAction insertOrIgnores against this on queue redelivery.
        DB::connection($this->getConnection())->statement(
            "CREATE UNIQUE INDEX analytics_events_message_sent_unique ON analytics_events (message_external_id) WHERE event_type = 'message_sent'"
        );
    }

    public function down(): void
    {
        Schema::connection($this->getConnection())->dropIfExists('analytics_events');
    }
};
