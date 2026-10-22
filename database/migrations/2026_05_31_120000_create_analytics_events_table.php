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

            $table->unsignedBigInteger('workspace_id');

            $table->unsignedBigInteger('chatter_id')->nullable();
            $table->enum('event_type', ['message_sent', 'feedback_given']);
            $table->string('role', 20)->nullable();
            $table->string('feedback', 20)->nullable();
            $table->unsignedBigInteger('llm_id')->nullable();
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

            // The largest table: every index costs a write per event, so only
            // the ones the readers use (by agent or workspace, by event type).
            $table->index(['agent_id', 'event_type', 'occurred_at']);
        });

        DB::connection($this->getConnection())->statement(
            'CREATE INDEX analytics_events_workspace_id_event_type_occurred_at_index ON analytics_events (workspace_id, event_type, occurred_at) INCLUDE (agent_id, role, chatter_id, response_time_ms)'
        );

        DB::connection($this->getConnection())->statement(
            "CREATE UNIQUE INDEX analytics_events_feedback_message_unique ON analytics_events (message_external_id) WHERE event_type = 'feedback_given'"
        );

        DB::connection($this->getConnection())->statement(
            "CREATE UNIQUE INDEX analytics_events_message_sent_unique ON analytics_events (message_external_id) WHERE event_type = 'message_sent'"
        );

        DB::connection($this->getConnection())->statement(
            'CREATE INDEX analytics_events_occurred_at_brin ON analytics_events USING brin (occurred_at)'
        );
    }

    public function down(): void
    {
        Schema::connection($this->getConnection())->dropIfExists('analytics_events');
    }
};
