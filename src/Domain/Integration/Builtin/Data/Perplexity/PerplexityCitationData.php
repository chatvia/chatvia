<?php

namespace Domain\Integration\Builtin\Data\Perplexity;

/**
 * One source a Perplexity answer cites. The title is '' when the provider
 * only gave a URL.
 */
final readonly class PerplexityCitationData
{
    public function __construct(
        public string $title,
        public string $url,
    ) {}
}
