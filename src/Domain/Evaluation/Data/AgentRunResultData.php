<?php

namespace Domain\Evaluation\Data;

/**
 * The captured state of one off-request agent run for an eval — the answer plus
 * the RAG sources + tools the agent used, tokens, and the Spectra trace id.
 * `error` is set (and answer empty) when the run threw.
 */
final readonly class AgentRunResultData
{
    /**
     * @param  list<string>  $sources
     * @param  list<string>  $toolsCalled
     */
    public function __construct(
        public string $answer,
        public array $sources,
        public array $toolsCalled,
        public ?int $tokens,
        public int $latencyMs,
        public string $traceId,
        public ?string $error = null,
    ) {}

    public function failed(): bool
    {
        return $this->error !== null;
    }
}
