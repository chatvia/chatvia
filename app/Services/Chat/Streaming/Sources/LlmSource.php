<?php

namespace App\Services\Chat\Streaming\Sources;

use App\Contracts\LLMProviderInterface;
use App\Services\Chat\ChatContext;
use App\Services\Chat\ChatService;
use App\Services\Chat\Streaming\ErrorCode;
use App\Services\Chat\Streaming\Exceptions\ClientDisconnected;
use App\Services\Chat\Streaming\RequestDedupeStore;
use App\Services\Chat\Streaming\StreamEvent;
use App\Services\Chat\Streaming\StreamTransformerResolver;
use Generator;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;
use Throwable;

class LlmSource
{
    public function __construct(
        private readonly LLMProviderInterface $llmProvider,
        private readonly StreamTransformerResolver $transformers,
        private readonly ChatService $chatService,
        private readonly RequestDedupeStore $dedupe,
    ) {}

    /**
     * Stream an LLM response using the v1 envelope.
     *
     * Order of operations:
     *   1. yield `start`
     *   2. open upstream stream + delegate per-chunk parsing to the
     *      provider-specific StreamTransformer (yields `delta` + `meta`)
     *   3. yield `sources` if requested
     *   4. finalize persistence (assistant message cache + DB job)
     *   5. record the result in the dedupe store keyed by request_id
     *   6. yield `done` AFTER persistence — so a client refetch on `done`
     *      always sees the message
     *
     * @return Generator<StreamEvent>
     */
    public function events(ChatContext $context, array $llmMessages): Generator
    {
        $assistantId = $context->assistantMessageId;

        yield StreamEvent::start($assistantId, [
            'assistant_message_id' => $assistantId,
            'user_message_id' => $context->userMessageId,
            'request_id' => $context->requestData->requestId,
            'language' => $context->queryLanguage,
            'replayed' => false,
        ]);

        try {
            $response = $this->llmProvider->stream(
                $context->requestData->message->model->internal_name,
                $llmMessages,
                $context->requestData->agent->llmSettings()
            );

            $transformer = $this->transformers->for($this->llmProvider);

            yield from $transformer->transform($response, $context);

            if ($context->requestData->streamSources && $context->sources->isNotEmpty()) {
                yield StreamEvent::sources($assistantId, $context->sources->toArray());
            }

            $context->markEndTime();
            $this->chatService->handleChatCompletion($context);
            $this->rememberForReplay($context);

            yield StreamEvent::done(
                $assistantId,
                'complete',
                $context->getResponseTimeMs(),
            );
        } catch (ClientDisconnected) {
            $context->markEndTime();
            $this->safeFinalize($context);
            $this->rememberForReplay($context, partial: true);

            // The client is gone — no point yielding more.
            return;
        } catch (RequestException $e) {
            Log::error('Chat stream upstream error', [
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);
            report($e);

            yield StreamEvent::error(
                $assistantId,
                ErrorCode::UpstreamUnavailable,
                'The selected model could not be reached. Please try again.',
            );
            yield StreamEvent::done($assistantId, 'error');
        } catch (Throwable $e) {
            Log::error('Chat stream crashed', [
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);
            report($e);

            yield StreamEvent::error(
                $assistantId,
                ErrorCode::Internal,
                'Something went wrong while generating the response.',
            );
            yield StreamEvent::done($assistantId, 'error');
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

    private function rememberForReplay(ChatContext $context, bool $partial = false): void
    {
        $requestId = $context->requestData->requestId;
        if (! $requestId || $context->streamedMessage === '') {
            return;
        }

        try {
            $this->dedupe->remember(
                requestId: $requestId,
                assistantMessageId: $context->assistantMessageId,
                content: $context->streamedMessage,
                language: $context->queryLanguage,
                sources: $context->sources->toArray(),
            );
        } catch (Throwable $e) {
            // Replay is a nice-to-have; never let it fail the stream.
            report($e);
        }
    }
}
