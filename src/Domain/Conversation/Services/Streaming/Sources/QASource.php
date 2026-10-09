<?php

declare(strict_types=1);

namespace Domain\Conversation\Services\Streaming\Sources;

use Domain\Conversation\Data\Turn\QaMatchData;
use Domain\Conversation\Services\ChatContext;
use Domain\Conversation\Services\Streaming\Exceptions\ClientDisconnected;
use Domain\Conversation\Services\Streaming\Sse\StreamEvent;
use Domain\Conversation\Services\Turn\AssistantReplyRecorder;
use Domain\Knowledge\Source\Qa\Models\AgentQuestionAnswer;
use Generator;
use Throwable;

/**
 * Answers a turn with a curated Q&A entry whose question matched exactly, in
 * the same envelope as an LLM answer. One delta carries the whole answer —
 * no chunking and no server-side delay; a typing effect is the client's job.
 * The reply is recorded and billed like any other answer.
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
        $qaMatch = $context->qaMatch;
        $answer = (string) $qaMatch?->answer;
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

            if ($context->requestData->streamSources && $qaMatch instanceof QaMatchData) {
                yield StreamEvent::sources($assistantId, [[
                    'id' => $qaMatch->id,
                    'chunk' => $answer,
                    'chunkable_type' => AgentQuestionAnswer::class,
                    'chunkable_id' => $qaMatch->id,
                    'metadata' => ['questions' => $qaMatch->questions, 'source' => 'qa'],
                    'search_type' => 'qa',
                    'confidence_score' => $context->confidenceScore,
                ]]);
            }

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
