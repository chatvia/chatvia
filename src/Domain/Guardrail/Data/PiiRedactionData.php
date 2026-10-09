<?php

declare(strict_types=1);

namespace Domain\Guardrail\Data;

/**
 * The result of masking PII in a text: the masked text, the distinct PII
 * types found (filter keys), and how many spans were masked.
 */
final readonly class PiiRedactionData
{
    /**
     * @param  list<string>  $types
     */
    public function __construct(
        public string $text,
        public array $types = [],
        public int $count = 0,
    ) {}

    public function found(): bool
    {
        return $this->count > 0;
    }
}
