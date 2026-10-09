<?php

declare(strict_types=1);

namespace Domain\Integration\Builtin\Data\Perplexity;

/**
 * A cited answer from Perplexity: the answer text, every distinct source URL
 * in the order first cited, and — for Agent API sandbox runs — what the
 * sandbox executed.
 */
final readonly class PerplexityAnswerData
{
    /**
     * @param  list<PerplexityCitationData>  $citations
     */
    public function __construct(
        public string $answer,
        public array $citations,
        public ?PerplexitySandboxRunData $sandbox = null,
    ) {}
}
