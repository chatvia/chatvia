<?php

declare(strict_types=1);

namespace Domain\Conversation\Services\Streaming\Sources;

use Domain\Conversation\Services\ChatContext;
use Domain\Conversation\Services\Streaming\Exceptions\ClientDisconnected;
use Domain\Conversation\Services\Streaming\Sse\StreamEvent;
use Domain\Conversation\Services\Turn\AssistantReplyRecorder;
use Generator;
use Throwable;

/**
 * Answers a turn with a curated Q&A entry that matched the message, in the
 * same envelope as an LLM answer. One delta carries the whole answer — no
 * chunking and no server-side delay; a typing effect is the client's job. A
 * saved answer is not a document, so it lists no sources. The reply is
 * recorded and billed like any other answer.
 */
class QaSource
{
    public function __construct(
        private readonly AssistantReplyRecorder $replyRecorder,
    ) {}

    /**
     * @return Generator<int, StreamEvent>
     */
    public function events(ChatContext $context): Generator
    {
        $answer = $context->qaMatch?->answer ?? '';
        $assistantId = $context->assistantMessageId;

        $context->appendStreamedMessage($answer);

        try {
            yield StreamEvent::start($assistantId, [
                'assistant_message_id' => $assistantId,
                'user_message_id' => $context->userMessageId,
                'user_message' => $context->payload->message,
                'request_id' => $context->requestData->requestId,
                'language' => $context->queryLanguage,
                'replayed' => false,
            ]);

            yield StreamEvent::delta($assistantId, $answer);

            $context->markEndTime();
            $this->replyRecorder->recordCompletedReply($context);

            yield StreamEvent::done($assistantId, 'complete', $context->getResponseTimeMs());
        } catch (ClientDisconnected) {
            $this->recordAfterFailure($context);
        } catch (Throwable $e) {
            $this->recordAfterFailure($context);
            report($e);
        }
    }

    /** Keep the answer even when the stream broke; never throw from here. */
    private function recordAfterFailure(ChatContext $context): void
    {
        $context->markEndTime();

        try {
            $this->replyRecorder->recordCompletedReply($context);
        } catch (Throwable $e) {
            report($e);
        }
    }
}
