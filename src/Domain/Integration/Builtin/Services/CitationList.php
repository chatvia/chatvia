<?php

namespace Domain\Integration\Builtin\Services;

use Domain\Integration\Builtin\Data\Perplexity\PerplexityCitationData;

/**
 * Collects the sources an answer cites: each URL once, in the order first
 * seen, skipping empty URLs. Used while parsing a Perplexity response.
 */
final class CitationList
{
    /** @var array<string, PerplexityCitationData> */
    private array $citations = [];

    public function add(string $url, string $title = ''): void
    {
        if ($url === '' || isset($this->citations[$url])) {
            return;
        }

        $this->citations[$url] = new PerplexityCitationData($title, $url);
    }

    public function isEmpty(): bool
    {
        return $this->citations === [];
    }

    /**
     * @return list<PerplexityCitationData>
     */
    public function all(): array
    {
        return array_values($this->citations);
    }
}
