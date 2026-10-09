<?php

namespace Domain\Integration\Builtin\Data\Perplexity;

/**
 * One question for a Perplexity Sonar model (the chat-completions API).
 */
final readonly class PerplexitySonarRequestData
{
    public function __construct(
        public string $model,
        public string $query,
        /** Optional system steer sent before the question. */
        public ?string $systemPrompt,
        /** Upstream timeout in seconds; deeper models need longer. */
        public int $timeout,
    ) {}
}
