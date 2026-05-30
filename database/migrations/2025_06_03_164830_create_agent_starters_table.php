<?php

use Domain\Agents\Models\Agent;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_starters', function (Blueprint $table) {
            $table->id();
            // One row per agent; `text` holds the ordered JSON array of starter
            // strings — same shape as a template's `starters` column.
            $table->foreignIdFor(Agent::class)->unique()->constrained()->cascadeOnDelete();
            $table->json('text');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_starters');
    }
};
