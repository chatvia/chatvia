<?php

use App\Models\Agent;
use App\Models\Llm;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_llm', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Agent::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(Llm::class)->constrained()->cascadeOnDelete();

            // Additional fields for better management
            $table->boolean('is_default')->default(false)->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->float('temperature')->default(1.0);

            $table->float('top_p')->default(1.0);
            $table->float('top_k')->default(0);
            $table->float('frequency_penalty')->default(0.0);
            $table->float('presence_penalty')->default(0.0);

            // Unique constraint to prevent duplicate agent-llm pairs
            $table->unique(['agent_id', 'llm_id']);

            // Indexes for common queries
            $table->index(['agent_id', 'is_active']);
            $table->index(['agent_id', 'is_default']);
            $table->index(['agent_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_llm');
    }
};
