<?php

namespace Domain\Conversation\Services\Streaming\Sources;

use Domain\Conversation\Services\ChatContext;
use Domain\Conversation\Services\ChatService;
use Domain\Conversation\Services\Streaming\Exceptions\ClientDisconnected;
use Domain\Conversation\Services\Streaming\Sse\StreamEvent;
use Generator;
use Throwable;

/**
 * Streams a guardrail refusal for an input the guards blocked (prompt-injection,
 * off-topic, or toxic). Mirrors QASource's canned-answer envelope, but persists
 * via {@see ChatService::persistAssistantMessage} so a refused turn is recorded
 * in history yet NEVER billed — no LLM ran. The block itself was already logged
 * by the input guard in ChatService::initializeChatContext.
 *
 * @see QASource
 */
class GuardBlockSource
{
    public function __construct(
        private readonly ChatService $chatService,
    ) {}

    /**
     * @return Generator<StreamEvent>
     */
    public function events(ChatContext $context): Generator
    {
        $assistantId = $context->assistantMessageId;
        $refusal = $context->guardRefusal ?? 'Sorry, I can’t help with that request.';

        $context->appendStreamedMessage($refusal);

        try {
            yield StreamEvent::start($assistantId, [
                'assistant_message_id' => $assistantId,
                'user_message_id' => $context->userMessageId,
                'request_id' => $context->requestData->requestId,
                'language' => $context->queryLanguage,
                'replayed' => false,
            ]);

            yield StreamEvent::delta($assistantId, $refusal);

            $context->markEndTime();
            $this->chatService->persistAssistantMessage($context);

            yield StreamEvent::done($assistantId, 'complete', $context->getResponseTimeMs());
        } catch (ClientDisconnected) {
            $context->markEndTime();
            $this->safePersist($context);
        } catch (Throwable $e) {
            $context->markEndTime();
            $this->safePersist($context);
            report($e);
        }
    }

    private function safePersist(ChatContext $context): void
    {
        try {
            $this->chatService->persistAssistantMessage($context);
        } catch (Throwable $e) {
            report($e);
        }
    }
}
