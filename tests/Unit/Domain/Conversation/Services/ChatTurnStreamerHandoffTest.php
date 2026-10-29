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
use Domain\Guardrail\Services\GuardrailInspector;
use Domain\Llm\Models\Llm;
use GuzzleHttp\Promise\Create;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Tests\Support\Conversation\StreamTurn;

/**
 * Handoff on a web turn. While a teammate holds a conversation, a visitor's
 * message is relayed to them instead of being answered by the AI, but only in
 * the visitor's own conversation, and with or without a license key (a held
 * chat finishes). A blocked topic set to hand off puts a Chat Widget
 * conversation in the queue when the install has a key; the surface comes
 * from the conversation row, as the widget's per-message requests carry none,
 * so a hosted-chat conversation (no live chat) gets the plain refusal.
 */
beforeEach(function (): void {
    Bus::fake();
    $this->agent = Agent::factory()->create(['llm_id' => Llm::factory()->create()->id]);
});

/** The guardrails block the message as a topic that hands off to the team. */
function csshBlockAsHandoffTopic(): void
{
    $guardrails = Mockery::mock(GuardrailInspector::class);
    $guardrails->shouldReceive('redactPii')->andReturnArg(1);
    $guardrails->shouldReceive('inspectInputAsync')->andReturn(Create::promiseFor(new GuardResultData(
        'I want to cancel my contract',
        blocked: true,
        refusalMessage: 'Sorry, I can’t help with that request.',
        handoffMessage: 'Let me connect you with someone from our team.',
    )));
    app()->instance(GuardrailInspector::class, $guardrails);
}

it('relays the visitor message to the teammate who holds the conversation, with or without a license key', function (bool $licensed): void {
    Event::fake([MessagePosted::class]);
    $conversation = Conversation::factory()->forAgent($this->agent)->handedOff(HandoffStatus::Human, $this->agent->workspace->owner)->create();
    $licensed ? withLicense() : withoutLicense();

    $events = StreamTurn::run($this->agent, 'Are you there?', [
        'externalSessionId' => $conversation->session->external_id,
        'conversationId' => $conversation->external_id,
    ]);

    expect(StreamTurn::ofType($events, EventType::Meta)[0]->data['value']['status'])->toBe('human');
    Event::assertDispatched(MessagePosted::class, fn (MessagePosted $event): bool => $event->conversationId === $conversation->external_id
        && $event->content === 'Are you there?');
})->with([
    'with a license key' => true,
    'without one' => false,
]);

it('does not relay into a held conversation of another visitor session', function (): void {
    Event::fake([MessagePosted::class]);
    $conversation = Conversation::factory()->forAgent($this->agent)->handedOff(HandoffStatus::Human, $this->agent->workspace->owner)->create();
    StreamTurn::stopAtTheRateLimit($this->agent);

    StreamTurn::run($this->agent, 'Are you there?', [
        'externalSessionId' => Session::factory()->create(['agent_id' => $this->agent->id])->external_id,
        'conversationId' => $conversation->external_id,
    ]);

    Event::assertNotDispatched(MessagePosted::class);
});

it('hands a widget conversation off on a blocked handoff topic only with a license key, never a hosted-chat one', function (): void {
    Event::fake([HandedOff::class, HandoffRequested::class]);
    csshBlockAsHandoffTopic();
    withLicense();
    $run = fn (Conversation $conversation): array => StreamTurn::run($this->agent, 'I want to cancel my contract', [
        'externalSessionId' => $conversation->session->external_id,
        'conversationId' => $conversation->external_id,
        'saveUserMessageImmediately' => true,
    ]);
    $widget = Conversation::factory()->forAgent($this->agent)->widget()->create();
    $hosted = Conversation::factory()->forAgent($this->agent)->hostedChat()->create();
    $unlicensed = Conversation::factory()->forAgent($this->agent)->widget()->create();

    $handedOff = $run($widget);
    $hostedEvents = $run($hosted);
    withoutLicense();
    $unlicensedEvents = $run($unlicensed);

    expect(StreamTurn::ofType($handedOff, EventType::Meta)[0]->data['key'])->toBe('handoff')
        ->and($widget->fresh()->handoff_status)->toBe(HandoffStatus::Pending)
        ->and(StreamTurn::ofType($handedOff, EventType::Delta)[0]->data['text'])->toBe('Let me connect you with someone from our team.')
        ->and(StreamTurn::ofType($hostedEvents, EventType::Meta))->toBe([])
        ->and($hosted->fresh()->handoff_status)->toBe(HandoffStatus::Ai)
        ->and(StreamTurn::ofType($hostedEvents, EventType::Delta)[0]->data['text'])->toBe('Sorry, I can’t help with that request.')
        ->and(StreamTurn::ofType($unlicensedEvents, EventType::Meta))->toBe([])
        ->and($unlicensed->fresh()->handoff_status)->toBe(HandoffStatus::Ai)
        ->and(StreamTurn::ofType($unlicensedEvents, EventType::Delta)[0]->data['text'])->toBe('Sorry, I can’t help with that request.');
    Event::assertDispatchedTimes(HandoffRequested::class, 1);
});
