<?php

use Domain\Agent\Models\Agent;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_question_answers', function (Blueprint $table): void {
            $table->id();
            $table->uuid('external_id')->unique();
            $table->foreignIdFor(Agent::class)->index()->constrained()->cascadeOnDelete();

            $table->string('title');
            $table->text('answer');
            $table->text('questions');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_question_answers');
    }
};
