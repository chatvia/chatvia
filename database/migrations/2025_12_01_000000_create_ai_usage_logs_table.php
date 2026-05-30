<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_usage_logs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('team_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('agent_id')->nullable()->constrained()->nullOnDelete();

            // Snapshots preserved when the parent row is deleted.
            $table->string('user_email_snapshot')->nullable();
            $table->string('agent_name_snapshot')->nullable();

            $table->string('feature');                   // chat / ingestion / summary
            $table->string('reference_id')->nullable();  // logical reference, no FK

            $table->string('provider');                  // openai / anthropic / …
            $table->string('model');                     // gpt-4o / claude-… / …

            $table->unsignedInteger('input_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);
            $table->unsignedInteger('total_tokens')->default(0);

            $table->decimal('input_cost', 10, 6)->default(0);
            $table->decimal('output_cost', 10, 6)->default(0);
            $table->decimal('total_cost', 10, 6)->default(0);

            $table->timestamps();

            // Time-bucketed analytics
            $table->index([DB::raw('extract(year from created_at)'), DB::raw('extract(month from created_at)')], 'ai_usage_year_month_idx');

            // Per-actor cost slices
            $table->index(['user_id', 'created_at']);
            $table->index(['team_id', 'created_at']);
            $table->index(['agent_id', 'created_at']);

            // Per-feature cost slices
            $table->index(['feature', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_usage_logs');
    }
};
