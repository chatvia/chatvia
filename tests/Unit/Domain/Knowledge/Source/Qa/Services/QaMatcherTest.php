<?php

use Domain\Agent\Models\Agent;
use Domain\Knowledge\Embedding\Contracts\EmbeddingsProviderInterface;
use Domain\Knowledge\Source\Qa\Actions\CreateAgentQaAction;
use Domain\Knowledge\Source\Qa\Actions\EmbedQaQuestionsAction;
use Domain\Knowledge\Source\Qa\Data\QaEntryData;
use Domain\Knowledge\Source\Qa\Models\AgentQuestionAnswer;
use Domain\Knowledge\Source\Qa\Services\QaMatcher;
use Illuminate\Support\Facades\Bus;
use Tests\Support\Knowledge\Embedding\FakeEmbeddingsProvider;

/**
 * A saved answer is sent for a message that is one of its questions as a
 * whole, or very close to one in meaning (with a measured bar). Vectors are
 * placed by hand: each text maps to a direction, so the similarities are known.
 */

/** @param  array<string, list<float>>  $directions */
function qamEmbeddings(array $directions): void
{
    app()->instance(EmbeddingsProviderInterface::class, FakeEmbeddingsProvider::directions($directions));
}

function qamEntry(Agent $agent): AgentQuestionAnswer
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
    qamEmbeddings(['What are your opening hours?' => [1.0, 0.0]]);
    $agent = Agent::factory()->create();
    $qa = qamEntry($agent);

    $embeddings = Mockery::mock(EmbeddingsProviderInterface::class);
    $embeddings->shouldNotReceive('embedQuery');
    app()->instance(EmbeddingsProviderInterface::class, $embeddings);

    expect(app(QaMatcher::class)->match($agent, 'what are your OPENING hours', 'what are your OPENING hours')?->id)->toBe($qa->id);
});

it('matches by meaning only above the bar', function (array $direction, bool $matches): void {
    qamEmbeddings([
        'What are your opening hours?' => [1.0, 0.0],
        'When do you open?' => $direction,
    ]);
    $agent = Agent::factory()->create();
    $qa = qamEntry($agent);

    $match = app(QaMatcher::class)->match($agent, 'When do you open?', 'When do you open?');

    expect($match?->id)->toBe($matches ? $qa->id : null);
})->with([
    'very close (cosine 0.95)' => [[0.95, 0.3122], true],
    'related, not the same (cosine 0.6)' => [[0.6, 0.8], false],
]);

it('matches only as whole questions when the embedding model was never measured', function (): void {
    config(['chatvia.ai.models.embedding' => 'openai']);
    qamEmbeddings(['What are your opening hours?' => [1.0, 0.0], 'When do you open?' => [1.0, 0.0]]);
    $agent = Agent::factory()->create();
    qamEntry($agent);

    expect(app(QaMatcher::class)->match($agent, 'When do you open?', 'When do you open?'))->toBeNull();
});
