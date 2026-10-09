<?php

namespace Domain\Conversation\Services\Streaming\Sources;

use Domain\Conversation\Services\ChatContext;
use Domain\Conversation\Services\Streaming\Exceptions\ClientDisconnected;
use Domain\Conversation\Services\Streaming\Sse\StreamEvent;
use Domain\Conversation\Services\Turn\AssistantReplyRecorder;
use Generator;
use Throwable;

/**
 * Streams a guardrail refusal for a message the input guards blocked
 * (prompt injection, off-topic or toxic). Same canned-answer envelope as
 * {@see QaSource}, but the refusal is recorded for the history only and never
 * billed — no model ran. The block itself was already logged when the turn's
 * context was built.
 */
class GuardBlockSource
{
    public function __construct(
        private readonly AssistantReplyRecorder $replyRecorder,
    ) {}

    /**
     * @return Generator<int, StreamEvent>
     */
    public function events(ChatContext $context): Generator
    {
        $assistantId = $context->assistantMessageId;
        $refusal = $context->guardRefusal ?? __('Sorry, I can’t help with that request.');

        $context->appendStreamedMessage($refusal);

        try {
            yield StreamEvent::start($assistantId, [
                'assistant_message_id' => $assistantId,
                'user_message_id' => $context->userMessageId,
                'user_message' => $context->payload->message,
                'request_id' => $context->requestData->requestId,
                'language' => $context->queryLanguage,
                'replayed' => false,
            ]);

            yield StreamEvent::delta($assistantId, $refusal);

            $context->markEndTime();
            $this->replyRecorder->recordRefusal($context);

            yield StreamEvent::done($assistantId, 'complete', $context->getResponseTimeMs());
        } catch (ClientDisconnected) {
            $this->recordAfterFailure($context);
        } catch (Throwable $e) {
            $this->recordAfterFailure($context);
            report($e);
        }
    }

    /** Keep the refusal even when the stream broke; never throw from here. */
    private function recordAfterFailure(ChatContext $context): void
    {
        $context->markEndTime();

        try {
            $this->replyRecorder->recordRefusal($context);
        } catch (Throwable $e) {
            report($e);
        }
    }
}
