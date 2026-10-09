<?php

use Domain\Agent\Models\Agent;
use Domain\Billing\Jobs\UpdateUserCreditsJob;
use Domain\Conversation\Data\Memory\ChatScopeData;
use Domain\Conversation\Data\Messages\UserTurnData;
use Domain\Conversation\Data\Turn\StreamRequestData;
use Domain\Conversation\Data\Turn\StreamRequestMessageData;
use Domain\Conversation\Enums\ConversationSource;
use Domain\Conversation\Events\AssistantReplyCompleted;
use Domain\Conversation\Models\Attachment;
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
 * A completed answer is stored and announced for billing (with the pages of
 * the conversation's PDFs); a guardrail refusal is stored but never billed.
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

it('stores a completed answer and announces it with the PDF pages to charge', function (): void {
    Event::fake([AssistantReplyCompleted::class]);
    $context = recorderContext();
    $conversation = Conversation::query()->firstWhere('external_id', $context->requestData->conversationId);
    Attachment::create(['chat_conversation_id' => $conversation->id, 'filepath' => 'a.pdf', 'filename' => 'a.pdf', 'mime_type' => 'application/pdf', 'size_bytes' => 1, 'pages' => 3]);
    Attachment::create(['chat_conversation_id' => $conversation->id, 'filepath' => 'b.pdf', 'filename' => 'b.pdf', 'mime_type' => 'application/pdf', 'size_bytes' => 1, 'pages' => 2]);

    app(AssistantReplyRecorder::class)->recordCompletedReply($context);

    Event::assertDispatched(AssistantReplyCompleted::class, fn (AssistantReplyCompleted $event): bool => $event->workspaceId === $context->requestData->agent->user_id
        && $event->llmId === $context->requestData->message->model->id
        && $event->assistantMessageId === $context->assistantMessageId
        && $event->documentPages === 5);

    expect(recorderHistory($context)[0]['content'])->toBe('Here is the summary.')
        ->and(recorderHistory($context)[0]['role'])->toBe('assistant');
});

it('charges the reply through the billing listener', function (): void {
    $context = recorderContext();

    app(AssistantReplyRecorder::class)->recordCompletedReply($context);

    Bus::assertDispatched(UpdateUserCreditsJob::class, fn (UpdateUserCreditsJob $job): bool => $job->userId === $context->requestData->agent->user_id
        && $job->modelId === $context->requestData->message->model->id
        && $job->additionalMessages === 0);
});

it('stores a refusal without charging for it', function (): void {
    Event::fake([AssistantReplyCompleted::class]);
    $context = recorderContext();

    app(AssistantReplyRecorder::class)->recordRefusal($context);

    Event::assertNotDispatched(AssistantReplyCompleted::class);
    expect(recorderHistory($context))->toHaveCount(1);
});
