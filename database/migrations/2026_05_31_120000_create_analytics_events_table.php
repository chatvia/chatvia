<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function getConnection(): ?string
    {
        return config('analytics.connection');
    }

    public function up(): void
    {
        Schema::connection($this->getConnection())->create('analytics_events', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('agent_id');
            $table->string('event_type', 40);
            $table->string('role', 20)->nullable();
            $table->string('feedback', 20)->nullable();
            $table->string('prior_feedback', 20)->nullable();
            $table->string('llm_external_name', 64)->nullable();
            $table->string('country_code', 2)->nullable();
            $table->unsignedInteger('response_time_ms')->nullable();
            $table->unsignedInteger('prompt_tokens')->nullable();
            $table->unsignedInteger('completion_tokens')->nullable();
            $table->unsignedInteger('total_tokens')->nullable();
            $table->uuid('conversation_external_id')->nullable();
            $table->uuid('message_external_id')->nullable();
            $table->uuid('session_external_id')->nullable();
            $table->timestampTz('occurred_at');
            $table->timestampTz('inserted_at')->useCurrent();

            $table->index(['agent_id', 'occurred_at']);
            $table->index(['agent_id', 'event_type', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::connection($this->getConnection())->dropIfExists('analytics_events');
    }
};
