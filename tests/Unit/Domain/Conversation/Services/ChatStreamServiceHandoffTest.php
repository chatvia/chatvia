<?php

use Domain\Agent\Models\Agent;
use Domain\Conversation\Enums\HandoffStatus;
use Domain\Conversation\Events\HandedOff;
use Domain\Conversation\Events\HandoffRequested;
use Domain\Conversation\Events\MessagePosted;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Session;
use Domain\Conversation\Services\Streaming\Sse\EventType;
use Domain\Guardrail\Data\GuardResultData;
use Domain\Guardrail\Services\GuardrailService;
use Domain\Llm\Models\Llm;
use GuzzleHttp\Promise\Create;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Tests\Support\Conversation\StreamTurn;

/**
 * Handoff on a web turn. While a teammate holds a conversation, a visitor's
 * message is relayed to them instead of being answered by the AI, but only in
 * the visitor's own conversation. A blocked topic set to hand off puts a Chat
 * Widget conversation in the queue; the surface comes from the conversation
 * row, as the widget's per-message requests carry none, so a hosted-chat
 * conversation (no live chat) gets the plain refusal.
 */
beforeEach(function (): void {
    Bus::fake();
    $this->agent = Agent::factory()->create(['llm_id' => Llm::factory()->create()->id]);
});

/** The guardrails block the message as a topic that hands off to the team. */
function csshBlockAsHandoffTopic(): void
{
    $guardrails = Mockery::mock(GuardrailService::class);
    $guardrails->shouldReceive('redactPii')->andReturnArg(1);
    $guardrails->shouldReceive('inspectInputAsync')->andReturn(Create::promiseFor(new GuardResultData(
        'I want to cancel my contract',
        blocked: true,
        refusalMessage: 'Sorry, I can’t help with that request.',
        handoffMessage: 'Let me connect you with someone from our team.',
    )));
    app()->instance(GuardrailService::class, $guardrails);
}

describe('a conversation a teammate holds', function (): void {
    beforeEach(function (): void {
        Event::fake([MessagePosted::class]);
        $this->conversation = Conversation::factory()->forAgent($this->agent)->handedOff(HandoffStatus::Human, $this->agent->workspace->owner)->create();
    });

    it('relays the visitor message to the teammate', function (): void {
        $events = StreamTurn::run($this->agent, 'Are you there?', [
            'externalSessionId' => $this->conversation->session->external_id,
            'conversationId' => $this->conversation->external_id,
        ]);

        expect(StreamTurn::ofType($events, EventType::Meta)[0]->data['value']['status'])->toBe('human');

        Event::assertDispatched(MessagePosted::class, fn (MessagePosted $event): bool => $event->conversationId === $this->conversation->external_id
            && $event->content === 'Are you there?');
    });

    it('does not relay into a conversation of another visitor session', function (): void {
        // The conversation is looked up inside the visitor's own session, so a
        // visitor who knows another conversation's id cannot post into it.
        // The relay check runs before the per-turn gates, so stopping there
        // keeps the unrelayed turn from calling the model.
        StreamTurn::stopAtTheRateLimit($this->agent);

        StreamTurn::run($this->agent, 'Are you there?', [
            'externalSessionId' => Session::factory()->create(['agent_id' => $this->agent->id])->external_id,
            'conversationId' => $this->conversation->external_id,
        ]);

        Event::assertNotDispatched(MessagePosted::class);
    });
});

describe('a blocked topic that hands off', function (): void {
    beforeEach(function (): void {
        Event::fake([HandedOff::class, HandoffRequested::class]);
        csshBlockAsHandoffTopic();
    });

    it('hands a widget conversation off', function (): void {
        $conversation = Conversation::factory()->forAgent($this->agent)->widget()->create();

        // As the widget endpoint builds the turn: the per-message request
        // names no source; only the conversation row knows its surface.
        $events = StreamTurn::run($this->agent, 'I want to cancel my contract', [
            'externalSessionId' => $conversation->session->external_id,
            'conversationId' => $conversation->external_id,
            'saveUserMessageImmediately' => true,
        ]);
        $meta = StreamTurn::ofType($events, EventType::Meta)[0];

        expect($meta->data['key'])->toBe('handoff')
            ->and($meta->data['value']['status'])->toBe(HandoffStatus::Pending->value)
            ->and($conversation->fresh()->handoff_status)->toBe(HandoffStatus::Pending)
            ->and(StreamTurn::ofType($events, EventType::Delta)[0]->data['text'])->toBe('Let me connect you with someone from our team.');
    });

    it('keeps the refusal on the hosted chat, which has no live chat', function (): void {
        $conversation = Conversation::factory()->forAgent($this->agent)->hostedChat()->create();

        $events = StreamTurn::run($this->agent, 'I want to cancel my contract', [
            'externalSessionId' => $conversation->session->external_id,
            'conversationId' => $conversation->external_id,
            'saveUserMessageImmediately' => true,
        ]);

        expect(StreamTurn::ofType($events, EventType::Meta))->toBe([])
            ->and($conversation->fresh()->handoff_status)->toBe(HandoffStatus::Ai)
            ->and(StreamTurn::ofType($events, EventType::Delta)[0]->data['text'])->toBe('Sorry, I can’t help with that request.');
    });
});
