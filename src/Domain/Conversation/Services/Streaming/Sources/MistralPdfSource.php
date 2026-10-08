<?php

namespace Domain\Conversation\Services\Streaming\Sources;

use Domain\Conversation\Models\Attachment;
use Domain\Conversation\Services\ChatContext;
use Domain\Conversation\Services\ChatService;
use Domain\Conversation\Services\ChatStreamService;
use Domain\Conversation\Services\Streaming\Requests\RequestDedupeStore;
use Domain\Conversation\Services\Streaming\Sse\StreamEvent;
use Domain\Conversation\Services\Tooling\Routing\ToolSelector;
use Domain\Conversation\Services\Tooling\ToolLoopRunner;
use Domain\Guardrail\Services\GuardrailRecorder;
use Domain\Guardrail\Services\GuardrailService;
use Domain\Llm\Contracts\LlmProviderInterface;
use Domain\Llm\Providers\Mistral;
use Generator;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Collection;
use Throwable;

/**
 * Streams chat completions through Mistral's chat-completions API with every
 * PDF attached to the conversation injected as a `document_url` chunk on the
 * latest user turn.
 *
 * Activated by {@see ChatStreamService} whenever
 * the current conversation has any PDF attachments. The agent's configured
 * LLM is intentionally bypassed — Mistral handles native PDF Q&A directly, no
 * separate OCR + retrieval step.
 *
 * Tool calling IS supported and the answer passes the same output guardrails:
 * the envelope, tool loop and finish step are shared with LlmSource through
 * {@see ToolLoopSource}, so MCP / webhook tools still fire (and pause for
 * approval) inside PDF conversations. A resumed turn stays on Mistral so the
 * PDF context (already in the snapshot's latest user turn) keeps flowing.
 */
class MistralPdfSource extends ToolLoopSource
{
    public function __construct(
        private readonly Mistral $mistral,
        ChatService $chatService,
        RequestDedupeStore $dedupe,
        ToolSelector $toolSelector,
        ToolLoopRunner $runner,
        GuardrailService $guardrails,
        GuardrailRecorder $guardrailRecorder,
    ) {
        parent::__construct($chatService, $dedupe, $toolSelector, $runner, $guardrails, $guardrailRecorder);
    }

    /**
     * @param  array<int, array<string, mixed>>  $llmMessages  Conversation history (last entry is the new user turn).
     * @param  Collection<int, Attachment>  $pdfs  Every PDF attached to this conversation; each is sent as a `document_url` chunk on the latest user turn.
     * @return Generator<int, StreamEvent>
     */
    public function events(ChatContext $context, array $llmMessages, Collection $pdfs): Generator
    {
        $documentUrls = $pdfs->pluck('url')->filter()->values()->all();

        yield from $this->streamTurn($context, $this->withDocumentUrls($llmMessages, $documentUrls));
    }

    protected function provider(): LlmProviderInterface
    {
        return $this->mistral;
    }

    protected function modelName(ChatContext $context): string
    {
        return (string) config('services.mistral.qa_model', 'mistral-small-latest');
    }

    protected function providerSlug(ChatContext $context): string
    {
        return 'mistral';
    }

    protected function logLabel(): string
    {
        return 'Mistral PDF';
    }

    protected function upstreamUnavailableMessage(): string
    {
        return __('Mistral could not be reached. Please try again.');
    }

    protected function turnFailedMessage(): string
    {
        return __('Something went wrong while answering from the PDF.');
    }

    /**
     * Mistral's actual complaint, so the log shows more than "400 Bad Request".
     *
     * @return array<string, mixed>
     */
    protected function upstreamLogContext(Throwable $e): array
    {
        return ['response_body' => $this->upstreamBody($e)];
    }

    /**
     * Rewrite the last user turn so its `content` is a list of typed chunks
     * with every attached PDF appended as a `document_url`. Mistral processes
     * documents fresh on every request (no server-side state), so the URLs
     * travel on each new turn rather than only the first.
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

    /**
     * Pull the response body off a Guzzle exception so the log shows
     * Mistral's actual complaint instead of just "400 Bad Request".
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

        return mb_strlen($body) > 2000 ? mb_substr($body, 0, 2000).'…[truncated]' : $body;
    }
}
