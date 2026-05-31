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

            $table->foreignIdFor(Agent::class)->unique()->constrained()->cascadeOnDelete();
            $table->jsonb('text');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_starters');
    }
};
