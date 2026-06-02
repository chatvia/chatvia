<?php

namespace Domain\Chat\Services\Streaming\Sources;

use Domain\Chat\Services\ChatContext;
use Domain\Chat\Services\ChatService;
use Domain\Chat\Services\Streaming\Exceptions\ClientDisconnected;
use Domain\Chat\Services\Streaming\Sse\StreamEvent;
use Domain\Source\QA\Models\AgentQuestionAnswer;
use Generator;
use Throwable;

class QASource
{
    public function __construct(
        private readonly ChatService $chatService,
    ) {}

    /**
     * Stream a Q&A match using the same envelope as LLM streaming.
     *
     * One delta carries the full answer — no chunking, no server-side
     * sleep. If a typing effect is desired, the client renders it.
     *
     * @return Generator<StreamEvent>
     */
    public function events(ChatContext $context): Generator
    {
        $qaMatch = $context->qaMatch;
        $answer = (string) $qaMatch->answer;
        $assistantId = $context->assistantMessageId;

        $context->appendStreamedMessage($answer);

        try {
            yield StreamEvent::start($assistantId, [
                'assistant_message_id' => $assistantId,
                'user_message_id' => $context->userMessageId,
                'request_id' => $context->requestData->requestId,
                'language' => $context->queryLanguage,
                'replayed' => false,
            ]);

            yield StreamEvent::delta($assistantId, $answer);

            if ($context->requestData->streamSources) {
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
            $this->chatService->handleChatCompletion($context);

            yield StreamEvent::done(
                $assistantId,
                'complete',
                $context->getResponseTimeMs(),
            );
        } catch (ClientDisconnected) {
            $context->markEndTime();
            $this->safeFinalize($context);
        } catch (Throwable $e) {
            $context->markEndTime();
            $this->safeFinalize($context);
            report($e);
        }
    }

    private function safeFinalize(ChatContext $context): void
    {
        try {
            $this->chatService->handleChatCompletion($context);
        } catch (Throwable $e) {
            report($e);
        }
    }
}
