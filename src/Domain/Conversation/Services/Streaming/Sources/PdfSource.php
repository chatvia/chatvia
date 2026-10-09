<?php

namespace Domain\Conversation\Services\Streaming\Sources;

use Domain\Conversation\Enums\MessageRole;
use Domain\Conversation\Models\Attachment;
use Domain\Conversation\Services\ChatContext;
use Domain\Conversation\Services\ChatStreamService;
use Domain\Conversation\Services\Streaming\Requests\RequestDedupeStore;
use Domain\Conversation\Services\Streaming\Sse\ErrorCode;
use Domain\Conversation\Services\Streaming\Sse\StreamEvent;
use Domain\Conversation\Services\Tooling\Routing\ToolSelector;
use Domain\Conversation\Services\Tooling\ToolLoopRunner;
use Domain\Conversation\Services\Turn\AssistantReplyRecorder;
use Domain\Guardrail\Services\GuardrailRecorder;
use Domain\Guardrail\Services\GuardrailService;
use Domain\Llm\Contracts\LlmProviderInterface;
use Domain\Llm\Services\DocumentLlm;
use Domain\Llm\Usage\TokenUsageContext;
use Generator;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Collection;
use Throwable;

/**
 * Streams chat completions through the configured document-reading LLM
 * ({@see DocumentLlm}) with every PDF attached to the conversation injected as
 * a `document_url` chunk on the latest user turn.
 *
 * Activated by {@see ChatStreamService} whenever
 * the current conversation has any PDF attachments. The agent's configured
 * LLM is intentionally bypassed — the document LLM answers from the whole PDF
 * directly, no separate OCR + retrieval step. When no such LLM is configured
 * the turn ends with a clear "not available" error.
 *
 * Tool calling IS supported and the answer passes the same output guardrails:
 * the envelope, tool loop and finish step are shared with LlmSource through
 * {@see ToolLoopSource}, so MCP / webhook tools still fire (and pause for
 * approval) inside PDF conversations. A resumed turn stays on the document LLM
 * so the PDF context (already in the snapshot's latest user turn) keeps flowing.
 */
class PdfSource extends ToolLoopSource
{
    /** Longest provider error body written to the log. */
    private const int MAX_LOGGED_BODY_LENGTH = 2000;

    public function __construct(
        private readonly DocumentLlm $documentLlm,
        AssistantReplyRecorder $replyRecorder,
        RequestDedupeStore $dedupe,
        ToolSelector $toolSelector,
        ToolLoopRunner $runner,
        GuardrailService $guardrails,
        GuardrailRecorder $guardrailRecorder,
        TokenUsageContext $tokenUsage,
    ) {
        parent::__construct($replyRecorder, $dedupe, $toolSelector, $runner, $guardrails, $guardrailRecorder, $tokenUsage);
    }

    /**
     * @param  array<int, array<string, mixed>>  $llmMessages  Conversation history (last entry is the new user turn).
     * @param  Collection<int, Attachment>  $pdfs  Every PDF attached to this conversation; each is sent as a `document_url` chunk on the latest user turn.
     * @return Generator<int, StreamEvent>
     */
    public function events(ChatContext $context, array $llmMessages, Collection $pdfs): Generator
    {
        if (! $this->documentLlm->isAvailable()) {
            yield StreamEvent::start($context->assistantMessageId, [
                'assistant_message_id' => $context->assistantMessageId,
                'user_message_id' => $context->userMessageId,
                'request_id' => $context->requestData->requestId,
                'language' => $context->queryLanguage,
                'replayed' => false,
            ]);
            yield StreamEvent::error($context->assistantMessageId, ErrorCode::Internal, __('Chatting with PDF files is not available.'));
            yield StreamEvent::done($context->assistantMessageId, 'error');

            return;
        }

        $documentUrls = $pdfs->pluck('url')->filter()->values()->all();

        yield from $this->streamTurn($context, $this->withDocumentUrls($llmMessages, $documentUrls));
    }

    protected function provider(): LlmProviderInterface
    {
        return $this->documentLlm->provider();
    }

    protected function modelName(ChatContext $context): string
    {
        return (string) config('chatvia.ai.models.document_chat');
    }

    protected function providerSlug(ChatContext $context): string
    {
        return $this->documentLlm->slug();
    }

    protected function logLabel(): string
    {
        return 'PDF chat';
    }

    protected function upstreamUnavailableMessage(): string
    {
        return __('The selected model could not be reached. Please try again.');
    }

    protected function turnFailedMessage(): string
    {
        return __('Something went wrong while answering from the PDF.');
    }

    /**
     * The provider's actual complaint, so the log shows more than "400 Bad Request".
     *
     * @return array<string, mixed>
     */
    protected function upstreamLogContext(Throwable $e): array
    {
        return ['response_body' => $this->upstreamBody($e)];
    }

    /**
     * Rewrite the last user turn so its `content` is a list of typed chunks
     * with every attached PDF appended as a `document_url`. The provider reads
     * documents fresh on every request (no server-side state), so the URLs
     * travel on each new turn rather than only the first.
     *
     * @param  array<int, array<string, mixed>>  $messages
     * @param  array<int, string>  $documentUrls
     * @return array<int, array<string, mixed>>
     */
    private function withDocumentUrls(array $messages, array $documentUrls): array
    {
        $lastUserIndex = array_key_last(array_filter(
            $messages,
            static fn (array $message): bool => ($message['role'] ?? '') === MessageRole::User->value,
        ));

        if ($documentUrls === [] || $lastUserIndex === null) {
            return $messages;
        }

        $text = $messages[$lastUserIndex]['content'] ?? '';

        $messages[$lastUserIndex] = [
            'role' => MessageRole::User->value,
            'content' => [
                ['type' => 'text', 'text' => is_string($text) ? $text : ''],
                ...array_map(static fn (string $url): array => ['type' => 'document_url', 'document_url' => $url], $documentUrls),
            ],
        ];

        return $messages;
    }

    /**
     * Pull the response body off a Guzzle exception so the log shows
     * the provider's actual complaint instead of just "400 Bad Request".
     */
    private function upstreamBody(Throwable $e): ?string
    {
        if (! $e instanceof RequestException) {
            return null;
        }
        $response = $e->getResponse();
        if ($response === null) {
            return null;
        }

        $body = (string) $response->getBody();

        return mb_strlen($body) > self::MAX_LOGGED_BODY_LENGTH
            ? mb_substr($body, 0, self::MAX_LOGGED_BODY_LENGTH).'…[truncated]'
            : $body;
    }
}
