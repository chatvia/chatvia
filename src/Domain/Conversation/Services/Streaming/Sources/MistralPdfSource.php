<?php

namespace Domain\Conversation\Services\Streaming\Sources;

use Domain\Agent\Models\Agent;
use Domain\Conversation\Models\Attachment;
use Domain\Conversation\Models\ToolApprovalCheckpoint;
use Domain\Conversation\Services\ChatContext;
use Domain\Conversation\Services\ChatService;
use Domain\Conversation\Services\ChatStreamService;
use Domain\Conversation\Services\Streaming\Exceptions\ClientDisconnected;
use Domain\Conversation\Services\Streaming\Exceptions\UpstreamStreamClosed;
use Domain\Conversation\Services\Streaming\Requests\RequestDedupeStore;
use Domain\Conversation\Services\Streaming\Sse\ErrorCode;
use Domain\Conversation\Services\Streaming\Sse\StreamEvent;
use Domain\Conversation\Services\Tooling\Routing\ToolSelector;
use Domain\Conversation\Services\Tooling\ToolLoopRunner;
use Domain\Llm\Providers\Mistral;
use Generator;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Spectra\Facades\Spectra;
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
 * Tool calling IS supported: candidate tools are selected via the same
 * ToolSelector as LlmSource and the loop is driven by the shared
 * ToolLoopRunner, so MCP / webhook tools still fire (and pause for approval)
 * inside PDF conversations.
 */
class MistralPdfSource
{
    public function __construct(
        private readonly Mistral $mistral,
        private readonly ChatService $chatService,
        private readonly RequestDedupeStore $dedupe,
        private readonly ToolSelector $toolSelector,
        private readonly ToolLoopRunner $runner,
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $llmMessages  Conversation history (last entry is the new user turn).
     * @param  Collection<int, Attachment>  $pdfs  Every PDF attached to this conversation; each is sent as a `document_url` chunk on the latest user turn.
     * @return Generator<int, StreamEvent>
     */
    public function events(ChatContext $context, array $llmMessages, Collection $pdfs): Generator
    {
        $assistantId = $context->assistantMessageId;

        Spectra::withTraceId($assistantId);

        yield StreamEvent::start($assistantId, [
            'assistant_message_id' => $assistantId,
            'user_message_id' => $context->userMessageId,
            'request_id' => $context->requestData->requestId,
            'language' => $context->queryLanguage,
            'replayed' => false,
        ]);

        try {
            $documentUrls = $pdfs->pluck('url')->filter()->values()->all();
            $llmMessages = $this->withDocumentUrls($llmMessages, $documentUrls);

            $agent = $context->requestData->agent;
            $toolDefinitions = $this->prepareToolDefinitions($agent, $context);

            if ($toolDefinitions !== []) {
                $this->injectToolSystemNote($llmMessages, (bool) config('chatvia.agent.tools.approval_enabled', true));
            }

            $paused = yield from $this->runner->run(
                context: $context,
                provider: $this->mistral,
                modelName: $this->qaModel(),
                providerSlug: 'mistral',
                llmMessages: $llmMessages,
                toolDefinitions: $toolDefinitions,
                extraSettings: $agent->llmSettings(),
                startIteration: 0,
            );
            if ($paused) {
                return;
            }

            yield from $this->finishTurn($context);
        } catch (ClientDisconnected) {
            $context->markEndTime();
            $this->safeFinalize($context);
            $this->rememberForReplay($context, partial: true);

            return;
        } catch (RequestException|UpstreamStreamClosed $e) {
            Log::error('Mistral PDF stream upstream error', [
                'exception' => $e::class,
                'message' => $e->getMessage(),
                'response_body' => $this->upstreamBody($e),
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
     * Resume a paused PDF turn after the user approved/denied a gated tool
     * call. Mirrors {@see LlmSource::resume} but stays on Mistral so the PDF
     * context (already in the snapshot's latest user turn) keeps flowing.
     *
     * @param  array<string, string>  $decisions  tool_call_id => 'approve'|'deny'
     * @return Generator<int, StreamEvent>
     */
    public function resume(ChatContext $context, ToolApprovalCheckpoint $checkpoint, array $decisions): Generator
    {
        $assistantId = $context->assistantMessageId;

        Spectra::withTraceId($assistantId);

        yield StreamEvent::start($assistantId, [
            'assistant_message_id' => $assistantId,
            'user_message_id' => null,
            'request_id' => $context->requestData->requestId,
            'language' => $context->queryLanguage,
            'replayed' => false,
            'resumed' => true,
        ]);

        try {
            $agent = $context->requestData->agent;
            $llmMessages = $context->llmMessages;

            yield from $this->runner->applyPendingCalls($context, $llmMessages, $checkpoint->pending_calls, $decisions);

            $toolDefinitions = $this->prepareToolDefinitions($agent, $context);

            $paused = yield from $this->runner->run(
                context: $context,
                provider: $this->mistral,
                modelName: $this->qaModel(),
                providerSlug: 'mistral',
                llmMessages: $llmMessages,
                toolDefinitions: $toolDefinitions,
                extraSettings: $agent->llmSettings(),
                startIteration: $checkpoint->iteration + 1,
            );
            if ($paused) {
                return;
            }

            yield from $this->finishTurn($context);
        } catch (ClientDisconnected) {
            $context->markEndTime();
            $this->safeFinalize($context);

            return;
        } catch (RequestException|UpstreamStreamClosed $e) {
            Log::error('Mistral PDF resume upstream error', [
                'exception' => $e::class,
                'message' => $e->getMessage(),
                'response_body' => $this->upstreamBody($e),
            ]);
            report($e);

            yield StreamEvent::error(
                $assistantId,
                ErrorCode::UpstreamUnavailable,
                'Mistral could not be reached. Please try again.',
            );
            yield StreamEvent::done($assistantId, 'error');
        } catch (Throwable $e) {
            Log::error('Mistral PDF resume crashed', [
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);
            report($e);

            yield StreamEvent::error(
                $assistantId,
                ErrorCode::Internal,
                'Something went wrong while continuing the response.',
            );
            yield StreamEvent::done($assistantId, 'error');
        }
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

    private function qaModel(): string
    {
        return (string) config('services.mistral.qa_model', 'mistral-small-latest');
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

    /**
     * @return array<int, array<string, mixed>>
     */
    private function prepareToolDefinitions(Agent $agent, ChatContext $context): array
    {
        return $this->toolSelector->selectForTurn(
            $agent,
            $context->payload->message,
            $context->requestData->conversationId,
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $llmMessages
     */
    private function injectToolSystemNote(array &$llmMessages, bool $approvalEnabled): void
    {
        $note = trim(prompt('tools/approval', ['approval' => $approvalEnabled]));
        if ($note === '') {
            return;
        }

        foreach ($llmMessages as $i => $message) {
            if (($message['role'] ?? null) === 'system') {
                $llmMessages[$i]['content'] = rtrim((string) ($message['content'] ?? ''))."\n\n".$note;

                return;
            }
        }

        array_unshift($llmMessages, ['role' => 'system', 'content' => $note]);
    }

    /**
     * @return Generator<int, StreamEvent>
     */
    private function finishTurn(ChatContext $context): Generator
    {
        $assistantId = $context->assistantMessageId;

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
