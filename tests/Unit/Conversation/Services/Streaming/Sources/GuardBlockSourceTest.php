<?php

use Domain\Agent\Models\Agent;
use Domain\Conversation\Data\Messages\UserTurnData;
use Domain\Conversation\Data\Turn\StreamRequestData;
use Domain\Conversation\Data\Turn\StreamRequestMessageData;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Services\ChatContext;
use Domain\Conversation\Services\Streaming\Sources\GuardBlockSource;
use Domain\Conversation\Services\Streaming\Sse\EventType;
use Domain\Conversation\Services\Streaming\Sse\StreamEvent;
use Domain\Conversation\Services\Turn\AssistantReplyRecorder;
use Domain\Llm\Models\Llm;
use Illuminate\Support\Str;

/**
 * A blocked message gets the refusal; when a blocked topic handed the
 * conversation to the team, it gets the topic's hand-off message and a
 * `handoff` event that puts the widget in waiting mode.
 */
function guardBlockContext(): ChatContext
{
    $llm = Llm::factory()->create();
    $requestData = new StreamRequestData(
        agent: Agent::factory()->create(),
        message: new StreamRequestMessageData(model: $llm, message: 'I want to complain'),
        externalSessionId: (string) Str::uuid(),
        conversationId: (string) Str::uuid(),
    );

    $context = new ChatContext(
        requestData: $requestData,
        payload: UserTurnData::from([
            'external_session_id' => $requestData->externalSessionId,
            'external_conversation_id' => $requestData->conversationId,
            'external_message_id' => (string) Str::uuid(),
            'model' => $llm->external_name,
            'message' => 'I want to complain',
            'sent_at' => now(),
        ]),
        llmMessages: [],
        userMessageId: (string) Str::uuid(),
        assistantMessageId: (string) Str::uuid(),
    );
    $context->guardBlocked = true;
    $context->guardRefusal = 'Sorry, I can’t help with that request.';
    $context->guardHandoffMessage = 'Let me connect you with someone from our team.';

    return $context;
}

function guardBlockSource(): GuardBlockSource
{
    return new GuardBlockSource(new class extends AssistantReplyRecorder
    {
        public function __construct() {}

        public function recordRefusal(ChatContext $context): void {}
    });
}

it('sends the refusal when the conversation was not handed off', function (): void {
    $events = collect(iterator_to_array(guardBlockSource()->events(guardBlockContext()), preserve_keys: false));

    expect($events->first(fn (StreamEvent $e): bool => $e->type === EventType::Delta)->data['text'])->toBe('Sorry, I can’t help with that request.')
        ->and($events->contains(fn (StreamEvent $e): bool => $e->type === EventType::Meta))->toBeFalse();
});

it('sends the hand-off message and a pending handoff event when the team takes over', function (): void {
    $conversation = Conversation::factory()->create();

    $events = collect(iterator_to_array(guardBlockSource()->events(guardBlockContext(), $conversation), preserve_keys: false));
    $meta = $events->first(fn (StreamEvent $e): bool => $e->type === EventType::Meta);

    expect($events->first(fn (StreamEvent $e): bool => $e->type === EventType::Delta)->data['text'])->toBe('Let me connect you with someone from our team.')
        ->and($meta->data['key'])->toBe('handoff')
        ->and($meta->data['value'])->toMatchArray(['status' => 'pending', 'conversation_id' => $conversation->external_id]);
});
