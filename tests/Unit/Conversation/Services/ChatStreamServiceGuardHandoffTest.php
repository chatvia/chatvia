<?php

use Domain\Agent\Models\Agent;
use Domain\Conversation\Data\Turn\StreamRequestData;
use Domain\Conversation\Data\Turn\StreamRequestMessageData;
use Domain\Conversation\Enums\ConversationSource;
use Domain\Conversation\Enums\HandoffStatus;
use Domain\Conversation\Events\HandedOff;
use Domain\Conversation\Events\HandoffRequested;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Session;
use Domain\Conversation\Services\ChatStreamService;
use Domain\Conversation\Services\Streaming\Sse\EventType;
use Domain\Conversation\Services\Streaming\Sse\StreamEvent;
use Domain\Guardrail\Data\GuardResultData;
use Domain\Guardrail\Services\GuardrailService;
use Domain\Llm\Models\Llm;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;

/**
 * A blocked topic set to hand off puts a Chat Widget conversation in the
 * handoff queue. The surface comes from the conversation row — the widget's
 * per-message requests carry no source — so a hosted-chat conversation (no
 * live chat) gets the plain refusal instead.
 */
beforeEach(function (): void {
    Bus::fake();
    Event::fake([HandedOff::class, HandoffRequested::class]);
});

/** @return array{0: Agent, 1: Session, 2: Conversation} */
function ghfConversation(ConversationSource $source): array
{
    $agent = Agent::factory()->create(['llm_id' => Llm::factory()->create()->id]);
    $session = Session::factory()->create(['agent_id' => $agent->id]);
    $conversation = Conversation::factory()->withoutTitle()->create([
        'chat_session_id' => $session->id,
        'agent_id' => $agent->id,
        'source' => $source,
    ]);

    return [$agent, $session, $conversation];
}

/** @return list<StreamEvent> */
function ghfStream(Agent $agent, Session $session, Conversation $conversation): array
{
    $guardrails = Mockery::mock(GuardrailService::class);
    $guardrails->shouldReceive('inspectInput')->andReturn(new GuardResultData(
        'I want to cancel my contract',
        blocked: true,
        refusalMessage: 'Sorry, I can’t help with that request.',
        handoffMessage: 'Let me connect you with someone from our team.',
    ));
    app()->instance(GuardrailService::class, $guardrails);

    // As the widget endpoint builds the turn: the per-message request names
    // no source — only the conversation row knows its surface.
    return iterator_to_array(app(ChatStreamService::class)->stream(new StreamRequestData(
        agent: $agent,
        message: new StreamRequestMessageData(model: $agent->llm, message: 'I want to cancel my contract'),
        externalSessionId: $session->external_id,
        conversationId: $conversation->external_id,
        saveUserMessageImmediately: true,
    )), preserve_keys: false);
}

it('hands a widget conversation off when a blocked topic says so', function (): void {
    [$agent, $session, $conversation] = ghfConversation(ConversationSource::Widget);

    $events = collect(ghfStream($agent, $session, $conversation));
    $meta = $events->first(fn (StreamEvent $e): bool => $e->type === EventType::Meta);

    expect($meta?->data['key'])->toBe('handoff')
        ->and($meta->data['value']['status'])->toBe(HandoffStatus::Pending->value)
        ->and($conversation->fresh()->handoff_status)->toBe(HandoffStatus::Pending)
        ->and($events->first(fn (StreamEvent $e): bool => $e->type === EventType::Delta)->data['text'])
        ->toBe('Let me connect you with someone from our team.');
});

it('keeps the refusal on the hosted chat, which has no live chat', function (): void {
    [$agent, $session, $conversation] = ghfConversation(ConversationSource::HostedChat);

    $events = collect(ghfStream($agent, $session, $conversation));

    expect($events->contains(fn (StreamEvent $e): bool => $e->type === EventType::Meta))->toBeFalse()
        ->and($conversation->fresh()->handoff_status)->not->toBe(HandoffStatus::Pending)
        ->and($events->first(fn (StreamEvent $e): bool => $e->type === EventType::Delta)->data['text'])
        ->toBe('Sorry, I can’t help with that request.');
});
