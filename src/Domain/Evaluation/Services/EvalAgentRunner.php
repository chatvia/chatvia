<?php

namespace Domain\Evaluation\Services;

use Domain\Agent\Models\Agent;
use Domain\Conversation\Services\ChatService;
use Domain\Conversation\Services\Data\StreamRequestData;
use Domain\Conversation\Services\Data\StreamRequestMessageData;
use Domain\Conversation\Services\Tooling\Routing\ToolSelector;
use Domain\Conversation\Services\Tooling\ToolLoopRunner;
use Domain\Evaluation\Data\AgentRunResultData;
use Domain\Llm\Contracts\LlmProviderInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use Spectra\Facades\Spectra;
use Throwable;

/**
 * Runs a single eval prompt through an agent's REAL pipeline (system prompt +
 * RAG + tool loop) off the SSE/HTTP path, and returns the captured turn state.
 *
 * It reuses {@see ChatService} (context build + RAG + system prompt) and drives
 * {@see ToolLoopRunner::run()} directly — deliberately NOT going through
 * LlmSource::finishTurn(), so an eval NEVER persists a chat message or charges a
 * message credit. For the headless run, approval is disabled (no human to
 * approve gated tools) and audit is disabled (eval traffic must not pollute the
 * production tool-call log); both config overrides are restored in `finally` so
 * they can't leak to the next job in the same queue worker.
 *
 * Tools are offered to the model but NEVER executed: the loop runs in
 * capture-only mode, so when the agent decides to call a tool the call (name +
 * arguments) is recorded and the turn stops. Running real webhooks / MCP calls
 * on every eval would be expensive and have real-world side effects; instead the
 * captured call is what the `tool_call` scorer grades.
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

        $originalApproval = config('chatvia.agent.tools.approval_enabled');
        $originalAudit = config('chatvia.governance.audit_enabled');
        $originalGuardrails = config('chatvia.guardrails.enabled');
        Config::set('chatvia.agent.tools.approval_enabled', false);
        Config::set('chatvia.governance.audit_enabled', false);
        // Evals measure the agent's answer quality, not the guards — they have
        // their own log. Keep guardrails off so an eval case can't be blocked or
        // pay for the classifier.
        Config::set('chatvia.guardrails.enabled', false);

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
            return new AgentRunResultData(
                answer: '',
                sources: [],
                toolsCalled: [],
                tokens: null,
                latencyMs: (int) round((microtime(true) - $started) * 1000),
                traceId: $requestData->requestId ?? (string) Str::uuid(),
                error: $e->getMessage(),
            );
        } finally {
            Config::set('chatvia.agent.tools.approval_enabled', $originalApproval);
            Config::set('chatvia.governance.audit_enabled', $originalAudit);
            Config::set('chatvia.guardrails.enabled', $originalGuardrails);
        }
    }

    /**
     * A lightweight, deduped view of the retrieved RAG sources (for the
     * groundedness signal). Knowledge chunks carry their document label at
     * `metadata.filename` (see ChatService::enrichSourcesWithMetadata); the
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
