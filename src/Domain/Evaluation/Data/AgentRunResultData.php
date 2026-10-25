<?php

declare(strict_types=1);

namespace Domain\Evaluation\Data;

/**
 * The captured state of one off-request agent run for an eval — the answer plus
 * the RAG sources + tools the agent used, tokens, and the run's trace id (its
 * assistant message id, also stamped on guardrail events).
 * `error` is set (and answer empty) when the run threw.
 *
 * `toolsCalled` is the deduped list of tool NAMES; `toolCalls` keeps the full
 * intended call (name + raw JSON arguments) the agent emitted but that the eval
 * deliberately did NOT execute — the input to the tool_call scorer.
 */
final readonly class AgentRunResultData
{
    /**
     * @param  list<string>  $sources
     * @param  list<string>  $toolsCalled
     * @param  list<array{name: string, arguments: string}>  $toolCalls
     * @param  list<string>  $sourceRefs  the sources behind the answer as `{morph alias}:{id}`, for eval coverage
     * @param  list<string>  $blocks  the text of each numbered reference block the agent was given, in order (what `[n]` citations point to)
     */
    public function __construct(
        public string $answer,
        public array $sources,
        public array $toolsCalled,
        public ?int $tokens,
        public int $latencyMs,
        public string $traceId,
        public ?string $error = null,
        public array $toolCalls = [],
        public array $sourceRefs = [],
        public array $blocks = [],
    ) {}

    public function failed(): bool
    {
        return $this->error !== null;
    }
}
