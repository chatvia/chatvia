<?php

namespace Domain\Llm\Data\Chat;

use Spatie\LaravelData\Data;

/**
 * The token counts a provider reports for one completion (its `usage` block).
 * This is what the provider bills, so costs are computed from it.
 */
final class ChatUsageData extends Data
{
    public function __construct(
        public int $promptTokens,
        public int $completionTokens,
        public int $totalTokens,
    ) {}

    /**
     * @param  array<string, mixed>|null  $usage  the raw `usage` block, if any
     */
    public static function fromUsage(?array $usage): ?self
    {
        if ($usage === null || $usage === []) {
            return null;
        }

        $prompt = (int) ($usage['prompt_tokens'] ?? 0);
        $completion = (int) ($usage['completion_tokens'] ?? 0);

        return new self(
            promptTokens: $prompt,
            completionTokens: $completion,
            totalTokens: (int) ($usage['total_tokens'] ?? $prompt + $completion),
        );
    }
}
