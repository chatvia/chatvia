<?php

use Domain\Agent\Models\Agent;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_starters', function (Blueprint $table) {
            $table->id();

            $table->foreignIdFor(Agent::class)->unique()->constrained()->cascadeOnDelete();
            // Ordered list of conversation starters, each `{ text, icon }` where
            // `icon` is an optional Heroicons (outline) export name, e.g.
            // `ChatBubbleLeftRightIcon`, or null for a text-only chip.
            $table->jsonb('starters');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_starters');
    }
};
