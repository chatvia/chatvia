<?php

use Domain\Agent\Models\Agent;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_question_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Agent::class)->index()->constrained()->cascadeOnDelete();

            $table->string('title');
            $table->text('answer');
            $table->text('questions');

            $table->timestamps();
        });

        // Expression index matching phraseSearch's predicate exactly
        // (`to_tsvector('simple', questions) @@ phraseto_tsquery(...)`), which
        // runs on every chat turn. A trgm index used to sit here — unusable
        // for a tsvector `@@` probe, so each turn re-tokenized every QA row.
        DB::statement("CREATE INDEX agent_question_answers_questions_fts_idx ON agent_question_answers USING gin (to_tsvector('simple', questions))");
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_question_answers');
    }
};
