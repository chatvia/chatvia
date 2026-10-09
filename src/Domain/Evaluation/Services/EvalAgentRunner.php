<?php

namespace Domain\Evaluation\Services;

use Domain\Agent\Models\Agent;
use Domain\Conversation\Data\Turn\StreamRequestData;
use Domain\Conversation\Data\Turn\StreamRequestMessageData;
use Domain\Conversation\Services\ChatService;
use Domain\Conversation\Services\Tooling\Routing\ToolSelector;
use Domain\Conversation\Services\Tooling\ToolLoopRunner;
use Domain\Evaluation\Data\AgentRunResultData;
use Domain\Llm\Contracts\LlmProviderInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Spectra\Facades\Spectra;
use Throwable;

/**
 * Runs a single eval prompt through an agent's REAL pipeline (system prompt +
 * RAG + tool loop) off the SSE/HTTP path, and returns the captured turn state.
 *
 * It reuses {@see ChatService} (context build + RAG + system prompt) and drives
 * {@see ToolLoopRunner::run()} directly — deliberately NOT going through
 * LlmSource::finishTurn(), so the runner itself never persists a chat message
 * or charges a credit. Billing happens one level up: ScoreEvalCaseAction
 * charges one message credit per case that produced an answer.
 *
 * Tools are offered to the model but NEVER executed: the loop runs in
 * capture-only mode, so when the agent decides to call a tool the call (name +
 * arguments) is recorded and the turn stops. Running real webhooks / MCP calls
 * on every eval would be expensive and have real-world side effects; instead the
 * captured call is what the `tool_call` scorer grades. Because no tool runs,
 * nothing reaches the approval gate or the tool-call audit log.
 *
 * Guardrails are switched off for this run only, through the request DTO
 * (`guardrailsEnabled: false`), never through global config — evals measure
 * answer quality, and a guard must not block a case or bill a classifier call.
 *
 * A fresh, unique conversationId per run means no prior history, so
 * ChatService::initializeChatContext never persists the user message either.
 */
class EvalAgentRunner
{
    public function __construct(
        private readonly ChatService $chatService,
        private readonly ToolLoopRunner $runner,
        private readonly ToolSelector $toolSelector,
        private readonly LlmProviderInterface $provider,
    ) {}

    public function run(Agent $agent, string $input): AgentRunResultData
    {
        $agent->loadMissing(['agentPrompt', 'llm']);
        $model = $agent->llm;
        $started = microtime(true);

        if ($model === null) {
            return new AgentRunResultData('', [], [], null, 0, (string) Str::uuid(), 'Agent has no LLM configured.');
        }

        // external session + conversation ids map to Postgres `uuid` columns
        // (sessions.external_id / chat_conversations.external_id), so they MUST be
        // valid UUIDs — a prefixed string throws SQLSTATE 22P02 on the lookup.
        // A fresh random UUID is also what keeps the run isolated: the conversation
        // doesn't exist, so initializeChatContext never persists the user message
        // (saveUserMessageImmediately is false and the history holds only the system
        // prompt). `source: 'eval'` + the eval_ requestId tag the traffic for Spectra.
        $requestData = new StreamRequestData(
            agent: $agent,
            message: new StreamRequestMessageData(model: $model, message: $input),
            externalSessionId: (string) Str::uuid(),
            conversationId: (string) Str::uuid(),
            source: 'eval',
            requestId: 'eval_'.Str::uuid(),
            guardrailsEnabled: false,
        );

        try {
            $context = $this->chatService->initializeChatContext($requestData);
            $context = $this->chatService->enrichContextWithKnowledge($context);
            $llmMessages = $this->chatService->prepareLlmMessages($context);

            $toolDefinitions = $this->toolSelector->selectForTurn(
                $agent,
                $context->payload->message,
                $context->requestData->conversationId,
            );

            Spectra::withTraceId($context->assistantMessageId);

            iterator_to_array(
                $this->runner->run(
                    context: $context,
                    provider: $this->provider,
                    modelName: $model->internal_name,
                    providerSlug: $model->provider->value,
                    llmMessages: $llmMessages,
                    toolDefinitions: $toolDefinitions,
                    extraSettings: $agent->llmSettings(),
                    startIteration: 0,
                    captureToolCallsOnly: true,
                ),
                preserve_keys: false,
            );

            return new AgentRunResultData(
                answer: $context->streamedMessage,
                sources: $this->summarizeSources($context->sources),
                toolsCalled: array_values(array_unique($context->toolCallNames)),
                tokens: $context->usage['total_tokens'] ?? null,
                latencyMs: (int) round((microtime(true) - $started) * 1000),
                traceId: $context->assistantMessageId,
                toolCalls: $context->capturedToolCalls,
            );
        } catch (Throwable $e) {
            // The case is recorded as failed with this message; report it too so
            // a broken pipeline shows up in error tracking, not only in eval rows.
            report($e);

            return new AgentRunResultData(
                answer: '',
                sources: [],
                toolsCalled: [],
                tokens: null,
                latencyMs: (int) round((microtime(true) - $started) * 1000),
                traceId: $requestData->requestId ?? (string) Str::uuid(),
                error: $e->getMessage(),
            );
        }
    }

    /**
     * A lightweight, deduped view of the retrieved RAG sources (for the
     * groundedness signal). Knowledge chunks carry their document label at
     * `metadata.filename` (see KnowledgeEnricher); the
     * title/name/url fallbacks keep it tolerant of other source shapes. Many
     * chunks come from one document, so collapse to distinct labels.
     *
     * @param  Collection<int, mixed>  $sources
     * @return list<string>
     */
    private function summarizeSources(Collection $sources): array
    {
        return $sources
            ->map(fn ($source): ?string => data_get($source, 'metadata.filename')
                ?? data_get($source, 'title')
                ?? data_get($source, 'name')
                ?? data_get($source, 'url'))
            ->filter()
            ->map(fn ($value): string => (string) $value)
            ->reject(fn (string $label): bool => $label === 'Unknown')
            ->unique()
            ->values()
            ->all();
    }
}
