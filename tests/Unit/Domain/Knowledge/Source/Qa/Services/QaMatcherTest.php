<?php

use Domain\Agent\Models\Agent;
use Domain\Knowledge\Embedding\Contracts\EmbeddingsProviderInterface;
use Domain\Knowledge\Source\Qa\Actions\CreateAgentQaAction;
use Domain\Knowledge\Source\Qa\Actions\EmbedQaQuestionsAction;
use Domain\Knowledge\Source\Qa\Data\QaEntryData;
use Domain\Knowledge\Source\Qa\Models\AgentQuestionAnswer;
use Domain\Knowledge\Source\Qa\Models\AgentQuestionAnswerQuestion;
use Domain\Knowledge\Source\Qa\Services\QaMatcher;
use Illuminate\Support\Facades\Bus;
use Tests\Support\Knowledge\Embedding\FakeEmbeddingsProvider;

/**
 * A saved answer is sent for a message that is one of its questions as a
 * whole, or very close to one in meaning (with a measured bar). Vectors are
 * placed by hand: each text maps to a direction, so the similarities are known.
 */

/** @param  array<string, list<float>>  $directions */
function qaMatcherEmbeddings(array $directions): void
{
    app()->instance(EmbeddingsProviderInterface::class, FakeEmbeddingsProvider::directions($directions));
}

function qaMatcherEntry(Agent $agent): AgentQuestionAnswer
{
    Bus::fake();
    $qa = app(CreateAgentQaAction::class)->execute($agent, new QaEntryData('Hours', ['What are your opening hours?'], 'We are open from nine to five.'));
    app(EmbedQaQuestionsAction::class)->execute($qa);

    return $qa;
}

beforeEach(function (): void {
    config(['chatvia.ai.models.embedding' => 'qwen', 'chatvia.vector.search.qa_similarity_threshold' => null]);
});

it('matches a whole question first, without an embedding call', function (): void {
    qaMatcherEmbeddings(['What are your opening hours?' => [1.0, 0.0]]);
    $agent = Agent::factory()->create();
    $qa = qaMatcherEntry($agent);

    $embeddings = Mockery::mock(EmbeddingsProviderInterface::class);
    $embeddings->shouldNotReceive('embedQuery');
    app()->instance(EmbeddingsProviderInterface::class, $embeddings);

    expect(app(QaMatcher::class)->match($agent, 'what are your OPENING hours', 'what are your OPENING hours')?->id)->toBe($qa->id);
});

it('matches by meaning only above the bar', function (array $direction, bool $matches): void {
    qaMatcherEmbeddings([
        'What are your opening hours?' => [1.0, 0.0],
        'When do you open?' => $direction,
    ]);
    $agent = Agent::factory()->create();
    $qa = qaMatcherEntry($agent);

    $match = app(QaMatcher::class)->match($agent, 'When do you open?', 'When do you open?');

    expect($match?->id)->toBe($matches ? $qa->id : null);
})->with([
    'very close (cosine 0.95)' => [[0.95, 0.3122], true],
    'related, not the same (cosine 0.6)' => [[0.6, 0.8], false],
]);

it('matches only as whole questions when the embedding model was never measured', function (): void {
    config(['chatvia.ai.models.embedding' => 'openai']);
    qaMatcherEmbeddings(['What are your opening hours?' => [1.0, 0.0], 'When do you open?' => [1.0, 0.0]]);
    $agent = Agent::factory()->create();
    qaMatcherEntry($agent);

    expect(app(QaMatcher::class)->match($agent, 'When do you open?', 'When do you open?'))->toBeNull();
});

it('embeds only the questions that have no embedding yet', function (): void {
    qaMatcherEmbeddings(['What are your opening hours?' => [1.0, 0.0]]);
    $agent = Agent::factory()->create();
    $qa = qaMatcherEntry($agent);

    $embeddings = Mockery::mock(EmbeddingsProviderInterface::class);
    $embeddings->shouldNotReceive('embedBatch');
    app()->instance(EmbeddingsProviderInterface::class, $embeddings);

    app(EmbedQaQuestionsAction::class)->execute($qa);

    expect(AgentQuestionAnswerQuestion::query()->where('agent_question_answer_id', $qa->id)->whereNull('embedding')->exists())->toBeFalse();
});
