<?php

declare(strict_types=1);

namespace Domain\Integration\Builtin\Data\Perplexity;

/**
 * One run of the Perplexity Agent API with a single built-in tool enabled
 * (web_search, fetch_url, finance_search, people_search or sandbox).
 */
final readonly class PerplexityAgentRequestData
{
    public function __construct(
        /** The Agent API tool type to enable, e.g. 'web_search'. */
        public string $toolType,
        /** The natural-language task for the agent. */
        public string $input,
        /** Optional steer sent as the Agent API `instructions` field. */
        public ?string $instructions,
        /** Upstream timeout in seconds; sandbox runs need far longer than search. */
        public int $timeout,
    ) {}
}
