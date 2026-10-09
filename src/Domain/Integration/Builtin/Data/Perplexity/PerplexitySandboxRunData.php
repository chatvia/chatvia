<?php

namespace Domain\Integration\Builtin\Data\Perplexity;

/**
 * What the Perplexity sandbox ran for a `run_code` task: the language and
 * code it wrote, its final status and each execution's output.
 */
final readonly class PerplexitySandboxRunData
{
    /**
     * @param  list<PerplexitySandboxExecutionData>  $executions
     */
    public function __construct(
        public string $language,
        public string $code,
        public string $status,
        public array $executions,
    ) {}
}
