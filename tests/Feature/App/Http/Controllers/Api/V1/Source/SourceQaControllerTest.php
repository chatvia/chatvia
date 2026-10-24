<?php

use Domain\Knowledge\Source\Qa\Models\AgentQuestionAnswer;
use Illuminate\Support\Facades\Queue;
use Tests\Support\Api\ActsAsApiOwner;

/**
 * An agent's Q&A entries through the public API: adding one with its
 * questions and answer, and deleting it by its public id.
 */
uses(ActsAsApiOwner::class);

beforeEach(function (): void {
    $this->actAsApiOwner();
});

it('adds a Q&A entry and deletes it by its public id', function (): void {
    Queue::fake();
    $uri = "/api/v1/agents/{$this->agent->external_id}/sources/qas";

    $id = $this->withToken($this->token)
        ->postJson($uri, [
            'title' => 'Refunds',
            'questions' => ['Can I get a refund?', 'How do refunds work?'],
            'answer' => 'Refunds are available within 30 days of purchase.',
        ])
        ->assertCreated()
        ->assertJsonPath('data.title', 'Refunds')
        ->assertJsonPath('data.questions.1', 'How do refunds work?')
        ->json('data.id');

    expect(AgentQuestionAnswer::query()->where('agent_id', $this->agent->id)->count())->toBe(1);

    $this->withToken($this->token)->deleteJson("{$uri}/{$id}")->assertNoContent();

    expect(AgentQuestionAnswer::query()->where('agent_id', $this->agent->id)->exists())->toBeFalse();
});
