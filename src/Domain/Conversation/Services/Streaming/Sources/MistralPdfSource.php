<?php

namespace Domain\Conversation\Services\Streaming\Sources;

use Domain\Conversation\Models\Attachment;
use Domain\Conversation\Services\ChatContext;
use Domain\Conversation\Services\ChatService;
use Domain\Conversation\Services\ChatStreamService;
use Domain\Conversation\Services\Streaming\Exceptions\ClientDisconnected;
use Domain\Conversation\Services\Streaming\Exceptions\UpstreamStreamClosed;
use Domain\Conversation\Services\Streaming\Requests\RequestDedupeStore;
use Domain\Conversation\Services\Streaming\Sse\ErrorCode;
use Domain\Conversation\Services\Streaming\Sse\StreamEvent;
use Domain\Conversation\Services\Streaming\Transformers\StreamTransformerResolver;
use Domain\Llm\Providers\Mistral;
use Generator;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Streams chat completions through Mistral's chat-completions API with a
 * PDF attached as a `document_url` content chunk on the latest user turn.
 *
 * Activated by {@see ChatStreamService} whenever the
 * current conversation has `attached_pdf` set (i.e. the visitor uploaded a
 * PDF earlier in this conversation). Bypasses the agent's configured LLM
 * because Mistral handles native PDF Q&A — no separate OCR + retrieval step.
 *
 * No tool loop: the PDF context is delivered directly to the model, so the
 * tool-router / MCP path is intentionally skipped for these turns.
 */
class MistralPdfSource
{
    public function __construct(
        private readonly Mistral $mistral,
        private readonly StreamTransformerResolver $transformers,
        private readonly ChatService $chatService,
        private readonly RequestDedupeStore $dedupe,
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $llmMessages  Conversation history (last entry is the new user turn).
     * @param  Collection<int, Attachment>  $pdfs  Every PDF attached to this conversation; each is sent as a `document_url` chunk on the latest user turn.
     * @return Generator<StreamEvent>
     */
    public function events(ChatContext $context, array $llmMessages, Collection $pdfs): Generator
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
            $documentUrls = $pdfs->pluck('url')->filter()->values()->all();
            $messages = $this->withDocumentUrls($llmMessages, $documentUrls);
            $model = (string) config('services.mistral.qa_model', 'mistral-small-latest');

            $response = $this->mistral->stream($model, $messages);

            // Mistral chat-completions emits OpenAI-compatible SSE — reuse the
            // existing transformer so deltas, meta, and finish reasons flow
            // through the same envelope as native-LLM turns.
            $transformer = $this->transformers->for($this->mistral);
            yield from $transformer->transform($response, $context);

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

            return;
        } catch (RequestException|UpstreamStreamClosed $e) {
            Log::error('Mistral PDF stream upstream error', [
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);
            report($e);

            yield StreamEvent::error(
                $assistantId,
                ErrorCode::UpstreamUnavailable,
                'Mistral could not be reached. Please try again.',
            );
            yield StreamEvent::done($assistantId, 'error');
        } catch (Throwable $e) {
            Log::error('Mistral PDF stream crashed', [
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);
            report($e);

            yield StreamEvent::error(
                $assistantId,
                ErrorCode::Internal,
                'Something went wrong while answering from the PDF.',
            );
            yield StreamEvent::done($assistantId, 'error');
        }
    }

    /**
     * Rewrite the last user turn so its `content` is a list of typed chunks
     * with every attached PDF appended as a `document_url`. Mistral
     * processes documents fresh on every request (no server-side state),
     * so the URLs travel on each new turn rather than only the first.
     *
     * @param  array<int, array<string, mixed>>  $messages
     * @param  array<int, string>  $documentUrls
     * @return array<int, array<string, mixed>>
     */
    private function withDocumentUrls(array $messages, array $documentUrls): array
    {
        if ($documentUrls === []) {
            return $messages;
        }

        $lastUserIdx = null;
        foreach ($messages as $i => $m) {
            if (($m['role'] ?? '') === 'user') {
                $lastUserIdx = $i;
            }
        }
        if ($lastUserIdx === null) {
            return $messages;
        }

        $existing = $messages[$lastUserIdx]['content'] ?? '';
        $text = is_string($existing) ? $existing : '';

        $content = [['type' => 'text', 'text' => $text]];
        foreach ($documentUrls as $url) {
            $content[] = ['type' => 'document_url', 'document_url' => $url];
        }

        $messages[$lastUserIdx] = [
            'role' => 'user',
            'content' => $content,
        ];

        return $messages;
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
            report($e);
        }
    }
}
