<?php

use Domain\Agent\Models\Agent;
use Domain\Knowledge\Source\Qa\Models\AgentQuestionAnswer;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per question of a curated Q&A entry: the question as written, its
 * normalized form (for the whole-question match) and its embedding (for the
 * match by meaning). Rebuilt from the entry's `questions` whenever they change.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_question_answer_questions', function (Blueprint $table): void {
            $table->id();
            $table->foreignIdFor(AgentQuestionAnswer::class)->index()->constrained()->cascadeOnDelete();
            $table->foreignIdFor(Agent::class)->index()->constrained()->cascadeOnDelete();
            $table->text('question');
            $table->text('normalized');
            $table->embedding('embedding')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_question_answer_questions');
    }
};
