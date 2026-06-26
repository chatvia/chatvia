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
        Schema::connection($this->getConnection())->create('analytics_agent_source_daily', function (Blueprint $table): void {
            $table->unsignedBigInteger('agent_id');
            $table->unsignedBigInteger('workspace_id');
            $table->date('bucket_day');
            $table->string('sourceable_type', 64);
            $table->unsignedBigInteger('sourceable_id');
            $table->unsignedInteger('retrieval_count')->default(0);

            $table->string('source_name')->nullable();
            $table->string('source_mime')->nullable();

            $table->primary(['agent_id', 'bucket_day', 'sourceable_type', 'sourceable_id']);
            $table->index(['workspace_id', 'bucket_day']);
        });
    }

    public function down(): void
    {
        Schema::connection($this->getConnection())->dropIfExists('analytics_agent_source_daily');
    }
};
