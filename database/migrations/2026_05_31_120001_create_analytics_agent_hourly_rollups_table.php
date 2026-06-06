<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function getConnection(): ?string
    {
        return config('chatvia.analytics.connection');
    }

    public function up(): void
    {
        Schema::connection($this->getConnection())->create('analytics_agent_hourly_rollups', function (Blueprint $table): void {
            $table->unsignedBigInteger('agent_id');
            // Workspace owner, denormalised from the agent so rollups stay
            // attributable — and queryable for account-wide stats — even after
            // the agent is deleted.
            $table->unsignedBigInteger('workspace_id');
            $table->timestampTz('bucket_hour');

            $table->string('country_code', 2);
            $table->string('llm_external_name', 64);

            $table->unsignedInteger('conversations_started')->default(0);
            $table->unsignedInteger('user_messages')->default(0);
            $table->unsignedInteger('assistant_messages')->default(0);
            $table->unsignedBigInteger('total_tokens')->default(0);

            $table->unsignedBigInteger('response_ms_sum')->default(0);
            $table->unsignedInteger('response_ms_count')->default(0);

            $table->primary(['agent_id', 'bucket_hour', 'country_code', 'llm_external_name']);
            $table->index(['workspace_id', 'bucket_hour']);
        });
    }

    public function down(): void
    {
        Schema::connection($this->getConnection())->dropIfExists('analytics_agent_hourly_rollups');
    }
};
