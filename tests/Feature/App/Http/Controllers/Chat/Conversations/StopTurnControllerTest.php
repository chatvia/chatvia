<?php

use Domain\Agent\Models\Agent;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Services\Streaming\Exceptions\TurnStopped;
use Domain\Conversation\Services\Streaming\Requests\StreamRequestDataFactory;
use Domain\Conversation\Services\Turn\RunningTurns;
use Domain\Conversation\Services\Turn\TurnStops;
use Illuminate\Support\Sleep;
use Tests\Support\Conversation\HostedChatVisitor;
use Tests\Support\Conversation\TestChatContext;

/**
 * The hosted chat's Stop button ends the visitor's own turn, named by the
 * request id it streamed with, and answers once that turn has ended.
 */
beforeEach(function (): void {
    Sleep::fake();
    $this->agent = Agent::factory()->create(['is_public' => true]);
});

/** The scoped id the stream gave the visitor's turn. */
function stcScopedId(Agent $agent, string $conversationId, string $requestId): string
{
    $sessionId = Conversation::query()->where('external_id', $conversationId)->firstOrFail()->session->external_id;

    return (string) app(StreamRequestDataFactory::class)->chatRequestId($agent, $sessionId, $conversationId, $requestId);
}

it('stops the visitor\'s turn', function (): void {
    $id = HostedChatVisitor::startConversation($this->agent);

    $this->postJson("/chat/{$this->agent->slug}/conversations/{$id}/stop", ['request_id' => 'r1'])->assertNoContent();

    $turn = TestChatContext::make(requestId: stcScopedId($this->agent, $id, 'r1'));
    expect(fn () => app(TurnStops::class)->throwIfStopped($turn))->toThrow(TurnStopped::class);
    Sleep::assertNeverSlept();
});

it('answers once the turn has ended, waiting a few seconds at most', function (): void {
    $id = HostedChatVisitor::startConversation($this->agent);
    app(RunningTurns::class)->track($id, fn (): Generator => (function (): Generator {
        yield 'a';
    })());

    $this->postJson("/chat/{$this->agent->slug}/conversations/{$id}/stop", ['request_id' => 'r1'])->assertNoContent();

    Sleep::assertSleptTimes(50);
});

it('is not found for another visitor\'s conversation', function (): void {
    HostedChatVisitor::startConversation($this->agent);
    $theirs = Conversation::factory()->create(['agent_id' => $this->agent->id]);

    $this->postJson("/chat/{$this->agent->slug}/conversations/{$theirs->external_id}/stop", ['request_id' => 'r1'])->assertNotFound();
});

it('asks which turn to stop', function (): void {
    $id = HostedChatVisitor::startConversation($this->agent);

    $this->postJson("/chat/{$this->agent->slug}/conversations/{$id}/stop", [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['request_id']);
});
