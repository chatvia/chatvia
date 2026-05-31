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

            $table->string('user_email_snapshot')->nullable();
            $table->string('agent_name_snapshot')->nullable();

            $table->string('feature');
            $table->string('reference_id')->nullable();

            $table->string('provider');
            $table->string('model');

            $table->unsignedInteger('input_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);
            $table->unsignedInteger('total_tokens')->default(0);

            $table->unsignedBigInteger('input_cost_in_micro_cents')->default(0);
            $table->unsignedBigInteger('output_cost_in_micro_cents')->default(0);
            $table->unsignedBigInteger('total_cost_in_micro_cents')->default(0);

            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['team_id', 'created_at']);
            $table->index(['agent_id', 'created_at']);

            $table->index(['feature', 'created_at']);
        });

        DB::statement('CREATE INDEX ai_usage_created_brin ON ai_usage_logs USING BRIN (created_at)');
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_usage_logs');
    }
};
