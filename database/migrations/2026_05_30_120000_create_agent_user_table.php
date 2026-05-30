<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_id')->constrained('agents')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            $table->unique(['agent_id', 'user_id']);
            // Reverse direction: "what agents can this chatter access?"
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_user');
    }
};
