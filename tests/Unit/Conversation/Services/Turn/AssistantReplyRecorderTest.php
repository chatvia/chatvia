<?php

use App\Identity\Workspace\Actions\MessageLimits\SetMessageLimitAction;
use App\Identity\Workspace\Models\MessageLimit;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Data\Memory\ChatScopeData;
use Domain\Conversation\Data\Messages\UserTurnData;
use Domain\Conversation\Data\Turn\StreamRequestData;
use Domain\Conversation\Data\Turn\StreamRequestMessageData;
use Domain\Conversation\Enums\ConversationSource;
use Domain\Conversation\Events\AssistantReplyCompleted;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Session;
use Domain\Conversation\Services\ChatContext;
use Domain\Conversation\Services\Memory\ChatMemory;
use Domain\Conversation\Services\Turn\AssistantReplyRecorder;
use Domain\Llm\Models\Llm;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

/**
 * A completed answer is stored and announced, so it counts against the
 * workspace's monthly message limit; a guardrail refusal is stored but never
 * counted.
 */
beforeEach(function (): void {
    config(['cache.stores.chat' => ['driver' => 'array']]);
    Bus::fake();
});

function recorderContext(): ChatContext
{
    $agent = Agent::factory()->create();
    $session = Session::factory()->create(['agent_id' => $agent->id]);
    $conversation = Conversation::factory()->withoutTitle()->create([
        'chat_session_id' => $session->id,
        'agent_id' => $agent->id,
        'source' => ConversationSource::HostedChat,
    ]);

    $request = new StreamRequestData(
        agent: $agent,
        message: new StreamRequestMessageData(model: Llm::factory()->create(), message: 'Summarise the PDF'),
        externalSessionId: $session->external_id,
        conversationId: $conversation->external_id,
    );

    $context = new ChatContext(
        requestData: $request,
        payload: new UserTurnData(
            external_session_id: $session->external_id,
            external_conversation_id: $conversation->external_id,
            external_message_id: (string) Str::uuid(),
            model: 'm',
            message: 'Summarise the PDF',
            sent_at: now(),
        ),
        llmMessages: [],
        userMessageId: null,
        assistantMessageId: (string) Str::uuid(),
    );
    $context->appendStreamedMessage('Here is the summary.');

    return $context;
}

function recorderHistory(ChatContext $context): array
{
    $request = $context->requestData;

    return app(ChatMemory::class)
        ->withScope(new ChatScopeData($request->agent, $request->externalSessionId, $request->conversationId))
        ->getMessagesForConversation();
}

it('stores a completed answer and announces it', function (): void {
    Event::fake([AssistantReplyCompleted::class]);
    $context = recorderContext();

    app(AssistantReplyRecorder::class)->recordCompletedReply($context);

    Event::assertDispatched(AssistantReplyCompleted::class, fn (AssistantReplyCompleted $event): bool => $event->workspaceId === $context->requestData->agent->user_id
        && $event->assistantMessageId === $context->assistantMessageId);

    expect(recorderHistory($context)[0]['content'])->toBe('Here is the summary.')
        ->and(recorderHistory($context)[0]['role'])->toBe('assistant');
});

it('counts the reply against the workspace monthly message limit', function (): void {
    $context = recorderContext();
    app(SetMessageLimitAction::class)->execute($context->requestData->agent->user, 10);

    app(AssistantReplyRecorder::class)->recordCompletedReply($context);

    expect(MessageLimit::query()->where('user_id', $context->requestData->agent->user_id)->value('messages_used'))->toBe(1);
});

it('stores a refusal without counting it', function (): void {
    Event::fake([AssistantReplyCompleted::class]);
    $context = recorderContext();

    app(AssistantReplyRecorder::class)->recordRefusal($context);

    Event::assertNotDispatched(AssistantReplyCompleted::class);
    expect(recorderHistory($context))->toHaveCount(1);
});
