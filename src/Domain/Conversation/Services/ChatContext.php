<?php

namespace Domain\Conversation\Services;

use Carbon\CarbonImmutable;
use Domain\Conversation\Data\Messages\MessageStepData;
use Domain\Conversation\Data\Messages\UserTurnData;
use Domain\Conversation\Data\Tooling\EmittedToolCallData;
use Domain\Conversation\Data\Tooling\PendingToolCallData;
use Domain\Conversation\Data\Turn\QaMatchData;
use Domain\Conversation\Data\Turn\RetrievedSourceData;
use Domain\Conversation\Data\Turn\StreamRequestData;
use Domain\Conversation\Enums\MessageStepKind;
use Domain\Conversation\Services\Streaming\ToolCallDeltas;
use Domain\Llm\Data\Query\RewrittenQueryData;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

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
     * The step chain of this turn, in order: what the model wrote before
     * each round of tool calls and the calls the chat showed. Persisted into
     * the assistant message's `metadata.steps` so the chain comes back on
     * reload. A turn resumed after approval starts from the steps saved with
     * its checkpoint.
     *
     * @var array<int, MessageStepData>
     */
    public array $steps = [];

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

    /**
     * Set with $guardBlocked when the message was about a blocked topic that
     * hands the conversation to a person: what the visitor reads when the
     * handoff starts (they get $guardRefusal when it cannot).
     */
    public ?string $guardHandoffMessage = null;

    /**
     * Whether the model's thinking is sent to the client as `reasoning`
     * events this turn. Set by the tool loop before it calls the model
     * ({@see ToolLoopSource}): only for a surface that shows it, an effort
     * other than Off, and an answer that is not held for the guardrails.
     * Thinking never goes into $streamedMessage either way.
     */
    public bool $showsReasoning = false;

    private readonly CarbonImmutable $startTime;

    private ?CarbonImmutable $endTime = null;

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
        ?CarbonImmutable $startTime = null,
        /**
         * The question the knowledge is searched with: the visitor's message,
         * or, for a follow-up, the full question written from the chat.
         */
        public readonly ?string $searchQuery = null,
        /**
         * One short question to ask the visitor when their message is too
         * vague to search ({@see RewrittenQueryData::$clarifyingQuestion});
         * used only when nothing in the knowledge matched.
         */
        public readonly ?string $clarifyingQuestion = null,
    ) {
        $this->sources = collect();
        $this->startTime = $startTime ?? CarbonImmutable::now();
    }

    public function getStartTime(): CarbonImmutable
    {
        return $this->startTime;
    }

    public function appendStreamedMessage(string $content): void
    {
        $this->streamedMessage .= $content;
    }

    /** A line the model wrote before calling tools; blank text adds nothing. */
    public function addTextStep(string $text): void
    {
        $text = Str::trim($text);

        if ($text !== '') {
            $this->steps[] = MessageStepData::text($text);
        }
    }

    public function addToolStep(PendingToolCallData $call): void
    {
        $this->steps[] = MessageStepData::tool($call);
    }

    /** Everything the model wrote before its tool calls, one step per paragraph. */
    public function stepText(): string
    {
        return collect($this->steps)
            ->filter(static fn (MessageStepData $step): bool => $step->kind === MessageStepKind::Text)
            ->map(static fn (MessageStepData $step): string => $step->text ?? '')
            ->implode("\n\n");
    }

    /** Keep only the tool calls of the chain. */
    public function dropTextSteps(): void
    {
        $this->steps = array_values(array_filter(
            $this->steps,
            static fn (MessageStepData $step): bool => $step->kind !== MessageStepKind::Text,
        ));
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
        $this->endTime ??= CarbonImmutable::now();
    }

    public function getResponseTimeMs(): int
    {
        // If endTime hasn't been set yet, use current time
        return (int) round($this->startTime->diffInMilliseconds($this->endTime ?? CarbonImmutable::now(), absolute: true));
    }
}
