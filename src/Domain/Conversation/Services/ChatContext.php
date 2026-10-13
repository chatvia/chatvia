<?php

namespace Domain\Conversation\Services;

use Domain\Conversation\Data\Messages\UserTurnData;
use Domain\Conversation\Data\Tooling\EmittedToolCallData;
use Domain\Conversation\Data\Turn\QaMatchData;
use Domain\Conversation\Data\Turn\RetrievedSourceData;
use Domain\Conversation\Data\Turn\StreamRequestData;
use Domain\Conversation\Services\Streaming\ToolCallDeltas;
use Illuminate\Support\Collection;

/**
 * The working state of one chat turn while it streams: the request, the
 * visitor's message, the history sent to the LLM, what knowledge was found,
 * and everything the stream adds as it goes (the visible answer, usage, tool
 * calls, artifacts). Built by {@see Turn\TurnContextFactory}, enriched by
 * {@see Turn\KnowledgeEnricher}, filled by the streaming sources and the tool
 * loop, and finally recorded by {@see Turn\AssistantReplyRecorder}.
 *
 * It is deliberately mutable: the streaming code appends to it on every
 * chunk, and the request and identifiers stay read-only.
 */
class ChatContext
{
    public string $streamedMessage = '';

    public ?string $generationId = null;

    /**
     * Inline token usage from the streaming response's final chunk
     * (`{prompt_tokens, completion_tokens, total_tokens}`), captured by the
     * transformer. The authoritative billed count — preferred over the
     * OpenRouter post-hoc lookup and the TikToken estimate when logging usage.
     *
     * @var array<string, int>|null
     */
    public ?array $usage = null;

    /** @var Collection<int, RetrievedSourceData> */
    public Collection $sources;

    public ?float $confidenceScore = null;

    public ?QaMatchData $qaMatch = null;

    /**
     * Tool calls accumulated from the LLM stream within the current tool-loop
     * iteration, as they arrive in pieces. Each entry has the partial shape
     * `['id' => string, 'name' => string, 'arguments' => string]`. Filled by
     * OpenAiCompatibleTransformer, drained by ToolLoopRunner through
     * {@see takeToolCalls()} between iterations.
     *
     * @var array<int, array{id: string, name: string, arguments: string}>
     */
    public array $pendingToolCalls = [];

    /**
     * Names of the tools offered to the model on the current tool-loop
     * iteration. Set by ToolLoopRunner before each stream. Empty means no
     * tools were offered, so the transformer must treat every reply as plain
     * text — a JSON answer is then never mistaken for a tool call.
     *
     * @var array<int, string>
     */
    public array $offeredToolNames = [];

    /**
     * Structured tool-result artifacts accumulated across the whole assistant
     * turn (every tool call, every iteration). Each entry has the shape
     * `['kind' => string, 'data' => mixed]` — e.g. `['kind' => 'echarts_option',
     * 'data' => [...spec...]]`. Appended by LlmSource after each tool dispatch,
     * then persisted into the assistant message's `metadata.artifacts` so the
     * chart re-renders on page reload.
     *
     * @var array<int, array{kind: string, data: mixed}>
     */
    public array $artifacts = [];

    /**
     * Tool names invoked during this assistant turn. Appended by LlmSource
     * as each tool_call is dispatched, then persisted into the assistant
     * message's `metadata.tool_calls` so the StickyToolResolver can keep
     * them available on follow-up turns (e.g. `search_issues` → `get_issue`
     * where the second prompt is "tell me more about #3").
     *
     * @var array<int, string>
     */
    public array $toolCallNames = [];

    /**
     * Tool calls the model emitted but that were deliberately NOT executed —
     * populated only when the loop runs in capture-only mode (evals). Each entry
     * is `['name' => string, 'arguments' => string]` (arguments is the raw JSON
     * string the model produced). Lets a caller grade the agent's INTENDED tool
     * call without paying for (or causing the side effects of) a real dispatch.
     *
     * @var array<int, array{name: string, arguments: string}>
     */
    public array $capturedToolCalls = [];

    /**
     * Set by the input guardrail when the visitor's message must be refused. The
     * turn then routes to GuardBlockSource instead of the LLM — no model call,
     * and it does not count against the message limit.
     */
    public bool $guardBlocked = false;

    public ?string $guardRefusal = null;

    private readonly float $startTime;

    private ?float $endTime = null;

    /**
     * @param  array<int, array<string, mixed>>  $llmMessages  the history sent to the LLM (OpenAI message shape)
     */
    public function __construct(
        public readonly StreamRequestData $requestData,
        public readonly UserTurnData $payload,
        public readonly array $llmMessages,
        public readonly ?string $userMessageId,
        public readonly string $assistantMessageId,
        public readonly ?string $queryLanguage = null,
        ?float $startTime = null,
    ) {
        $this->sources = collect();
        $this->startTime = $startTime ?? microtime(true);
    }

    public function getStartTime(): float
    {
        return $this->startTime;
    }

    public function appendStreamedMessage(string $content): void
    {
        $this->streamedMessage .= $content;
    }

    /**
     * Reset the visible text buffer between tool-loop iterations so the
     * final saved assistant message only contains the post-tool reply.
     */
    public function resetStreamedMessage(): void
    {
        $this->streamedMessage = '';
    }

    /**
     * Apply a tool_call delta from the upstream LLM stream. OpenAI sends
     * tool calls as partial chunks: the first chunk has `id` + (typically)
     * `function.name`, subsequent chunks only have `function.arguments` as
     * a JSON-string fragment to be appended.
     *
     * @param  array<string, mixed>  $delta
     */
    public function applyToolCallDelta(array $delta): void
    {
        $this->pendingToolCalls = ToolCallDeltas::merge($this->pendingToolCalls, $delta);
    }

    /**
     * Drain and return the complete tool calls, leaving the buffer empty for
     * the next iteration.
     *
     * @return array<int, EmittedToolCallData>
     */
    public function takeToolCalls(): array
    {
        $calls = array_map(EmittedToolCallData::fromStreamBuffer(...), array_values($this->pendingToolCalls));
        $this->pendingToolCalls = [];

        return $calls;
    }

    /**
     * @param  Collection<int, RetrievedSourceData>  $sources
     */
    public function setSources(Collection $sources): void
    {
        $this->sources = $sources;
    }

    public function setConfidenceScore(?float $confidenceScore): void
    {
        $this->confidenceScore = $confidenceScore;
    }

    public function setQaMatch(?QaMatchData $qaMatch): void
    {
        $this->qaMatch = $qaMatch;
    }

    public function hasQaMatch(): bool
    {
        return $this->qaMatch instanceof QaMatchData;
    }

    public function markEndTime(): void
    {
        // Only set endTime once (first call wins)
        $this->endTime ??= microtime(true);
    }

    public function getResponseTimeMs(): int
    {
        // If endTime hasn't been set yet, use current time
        $endTime = $this->endTime ?? microtime(true);

        return (int) round(($endTime - $this->startTime) * 1000);
    }
}
