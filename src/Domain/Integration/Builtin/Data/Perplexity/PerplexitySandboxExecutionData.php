<?php

namespace Domain\Integration\Builtin\Data\Perplexity;

/**
 * The output of one program execution inside a Perplexity sandbox run.
 */
final readonly class PerplexitySandboxExecutionData
{
    public function __construct(
        public string $stdout,
        public string $stderr,
        public mixed $exitCode,
    ) {}
}
