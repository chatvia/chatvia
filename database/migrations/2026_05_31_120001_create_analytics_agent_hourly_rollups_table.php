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
        Schema::connection($this->getConnection())->create('analytics_agent_hourly_rollups', function (Blueprint $table): void {
            $table->unsignedBigInteger('agent_id');

            $table->unsignedBigInteger('workspace_id');
            $table->timestamp('bucket_hour');

            $table->unsignedInteger('conversations_started')->default(0);
            $table->unsignedInteger('user_messages')->default(0);
            $table->unsignedInteger('assistant_messages')->default(0);
            $table->unsignedBigInteger('prompt_tokens')->default(0);
            $table->unsignedBigInteger('completion_tokens')->default(0);
            $table->unsignedBigInteger('total_tokens')->default(0);

            $table->unsignedBigInteger('response_ms_sum')->default(0);
            $table->unsignedInteger('response_ms_count')->default(0);
            $table->unsignedInteger('response_ms_max')->default(0);

            $table->unsignedInteger('unanswered_replies')->default(0);
            $table->decimal('confidence_sum', 14, 2)->default(0);
            $table->unsignedInteger('confidence_count')->default(0);

            $table->primary(['agent_id', 'bucket_hour']);
            $table->index(['workspace_id', 'bucket_hour']);
        });

        DB::connection($this->getConnection())->statement('ALTER TABLE analytics_agent_hourly_rollups ADD COLUMN response_ms_hist integer[] NOT NULL DEFAULT array_fill(0, ARRAY[24])');

        // Every reply updates its hour's row: free space on each page lets
        // Postgres rewrite it in place. BRIN serves install-wide time ranges.
        DB::connection($this->getConnection())->statement('ALTER TABLE analytics_agent_hourly_rollups SET (fillfactor = 80)');
        DB::connection($this->getConnection())->statement('CREATE INDEX analytics_hourly_bucket_brin ON analytics_agent_hourly_rollups USING brin (bucket_hour)');
    }

    public function down(): void
    {
        Schema::connection($this->getConnection())->dropIfExists('analytics_agent_hourly_rollups');
    }
};
