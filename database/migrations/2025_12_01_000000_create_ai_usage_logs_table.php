<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_usage_logs', function (Blueprint $table) {
            $table->id();

            // Relationships (Set to NULL on delete to preserve the log)
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('team_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('agent_id')->nullable()->constrained()->nullOnDelete();

            // Snapshot data (Preserved even if the related record is deleted)
            $table->string('user_email_snapshot')->nullable()->comment('Email at the time of usage, preserved if user is deleted');
            $table->string('agent_name_snapshot')->nullable()->comment('Agent name at the time of usage');

            // Usage Context
            $table->string('feature')->index()->comment('e.g. chat, ingestion, summary');
            $table->string('reference_id')->nullable()->index()->comment('ID of the related record (message_id, file_id) without FK constraint');

            // Model Details
            $table->string('provider')->index(); // e.g. openai, anthropic
            $table->string('model')->index(); // e.g. gpt-4o

            // Token Usage
            $table->unsignedInteger('input_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);
            $table->unsignedInteger('total_tokens')->default(0);

            // Cost Calculation (High precision for micro-transactions)
            $table->decimal('input_cost', 10, 6)->default(0);
            $table->decimal('output_cost', 10, 6)->default(0);
            $table->decimal('total_cost', 10, 6)->default(0)->index();

            $table->timestamps();

            // Indexes for Analytics & Reporting
            // 1. Monthly aggregations (Year + Month)
            $table->index([DB::raw('extract(year from created_at)'), DB::raw('extract(month from created_at)')], 'ai_usage_year_month_idx');
            
            // 2. Cost analysis per user/team over time
            $table->index(['user_id', 'created_at']);
            $table->index(['team_id', 'created_at']);
            
            // 3. Cost analysis per feature (e.g. "How much does ingestion cost vs chat?")
            $table->index(['feature', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_usage_logs');
    }
};

