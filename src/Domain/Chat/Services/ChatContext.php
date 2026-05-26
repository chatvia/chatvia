<?php

namespace Domain\Chat\Services;

use Domain\Chat\Data\ChatInputPayload;
use Domain\Chat\Services\Data\StreamRequestData;
use Illuminate\Support\Collection;

class ChatContext
{
    public string $streamedMessage = '';

    public ?string $generationId = null;

    public Collection $sources;

    public ?float $confidenceScore = null;

    public ?object $qaMatch = null;

    /**
     * Tool calls accumulated from the LLM stream within the current tool-loop
     * iteration. Each entry has the partial shape `['id' => string,
     * 'name' => string, 'arguments' => string]`. Mutated by
     * OpenAiCompatibleTransformer, drained by LlmSource between iterations.
     *
     * @var array<int, array{id: string, name: string, arguments: string}>
     */
    public array $pendingToolCalls = [];

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

    private float $startTime;

    private ?float $endTime = null;

    public function __construct(
        public readonly StreamRequestData $requestData,
        public readonly ChatInputPayload $payload,
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
        $index = $delta['index'] ?? 0;
        $current = $this->pendingToolCalls[$index] ?? [
            'id' => '',
            'name' => '',
            'arguments' => '',
        ];

        if (isset($delta['id']) && is_string($delta['id'])) {
            $current['id'] = $delta['id'];
        }

        $function = $delta['function'] ?? null;
        if (is_array($function)) {
            if (isset($function['name']) && is_string($function['name'])) {
                $current['name'] = $function['name'];
            }
            if (isset($function['arguments']) && is_string($function['arguments'])) {
                $current['arguments'] .= $function['arguments'];
            }
        }

        $this->pendingToolCalls[$index] = $current;
    }

    /**
     * Drain and return the accumulated tool calls, leaving the buffer empty
     * for the next iteration.
     *
     * @return array<int, array{id: string, name: string, arguments: string}>
     */
    public function takeToolCalls(): array
    {
        $calls = array_values($this->pendingToolCalls);
        $this->pendingToolCalls = [];

        return $calls;
    }

    public function setSources(Collection $sources): void
    {
        $this->sources = $sources;
    }

    public function setConfidenceScore(?float $confidenceScore): void
    {
        $this->confidenceScore = $confidenceScore;
    }

    public function setQAMatch(?object $qaMatch): void
    {
        $this->qaMatch = $qaMatch;
    }

    public function hasQAMatch(): bool
    {
        return $this->qaMatch !== null;
    }

    public function markEndTime(): void
    {
        // Only set endTime once (first call wins)
        if ($this->endTime === null) {
            $this->endTime = microtime(true);
        }
    }

    public function getResponseTimeMs(): int
    {
        // If endTime hasn't been set yet, use current time
        $endTime = $this->endTime ?? microtime(true);

        return (int) round(($endTime - $this->startTime) * 1000);
    }
}
