<?php

use Domain\Agents\Models\Agent;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('agent_sources', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Agent::class)->constrained()->cascadeOnDelete();
            $table->morphs('sourceable');
            $table->unsignedInteger('retrieval_count')->default(0);
            $table->timestamp('last_retrieved_at')->nullable()->index();
            $table->unique(['agent_id', 'sourceable_id', 'sourceable_type'], 'agent_sources_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agent_sources');
    }
};
