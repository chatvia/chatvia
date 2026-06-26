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
        Schema::connection($this->getConnection())->create('analytics_agent_function_daily', function (Blueprint $table): void {
            $table->unsignedBigInteger('agent_id');
            $table->unsignedBigInteger('workspace_id');
            $table->date('bucket_day');
            $table->unsignedBigInteger('function_id');
            $table->unsignedInteger('invocation_count')->default(0);

            $table->string('function_name')->nullable();
            $table->text('function_description')->nullable();

            $table->primary(['agent_id', 'bucket_day', 'function_id']);
            $table->index(['workspace_id', 'bucket_day']);
        });
    }

    public function down(): void
    {
        Schema::connection($this->getConnection())->dropIfExists('analytics_agent_function_daily');
    }
};
