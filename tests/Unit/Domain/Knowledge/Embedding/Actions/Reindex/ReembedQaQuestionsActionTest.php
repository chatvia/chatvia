<?php

use Domain\Agent\Models\Agent;
use Domain\Knowledge\Embedding\Actions\Reindex\ReembedQaQuestionsAction;
use Domain\Knowledge\Embedding\Contracts\EmbeddingsProvider;
use Domain\Knowledge\Embedding\Services\EmbeddingStorage;
use Domain\Knowledge\Source\Qa\Actions\CreateAgentQaAction;
use Domain\Knowledge\Source\Qa\Data\QaEntryData;
use Domain\Knowledge\Source\Qa\Models\AgentQuestionAnswerQuestion;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Tests\Support\Knowledge\Embedding\FakeEmbeddingsProvider;

/**
 * A re-index embeds the saved Q&A questions of an id range again, from their
 * text, with the model in use.
 */
it('embeds the Q&A questions of a range again', function (): void {
    Bus::fake();
    $agent = Agent::factory()->create();
    app(CreateAgentQaAction::class)->execute($agent, new QaEntryData('Hours', ['What are your hours?', 'When do you open?'], 'Nine to five.'));
    $questions = AgentQuestionAnswerQuestion::query()->where('agent_id', $agent->id)->orderBy('id')->get();
    AgentQuestionAnswerQuestion::query()->whereKey($questions->modelKeys())->update(['embedding' => embeddingVector(0.25)]);
    // The new model: 0.5 everywhere, exact in half precision.
    $provider = FakeEmbeddingsProvider::constant(0.5, 'new-model');
    app()->instance(EmbeddingsProvider::class, $provider);
    $type = app(EmbeddingStorage::class)->columnType();

    $done = app(ReembedQaQuestionsAction::class)->execute($questions->first()->id, $questions->last()->id);

    expect($done)->toBe(2)
        ->and($provider->texts)->toBe(['What are your hours?', 'When do you open?'])
        ->and($questions->every(fn (AgentQuestionAnswerQuestion $question): bool => (bool) DB::scalar(
            "SELECT embedding = ?::{$type} FROM agent_question_answer_questions WHERE id = ?",
            [embeddingVector(0.5), $question->id],
        )))->toBeTrue();
});
