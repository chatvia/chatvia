<?php

declare(strict_types=1);

namespace Domain\Llm\Services;

use Domain\Llm\Enums\LlmProviderKind;
use Domain\Llm\Providers\Contracts\ProviderAdapter;

/**
 * The adapter of each provider kind ({@see LlmProviderKind::adapter()}), and
 * the list of them, in picker order. Every question about a
 * provider (its address, key, client, models, account) goes
 * through here.
 */
class ProviderAdapters
{
    /** @var array<string, ProviderAdapter> */
    private array $resolved = [];

    public function for(LlmProviderKind $kind): ProviderAdapter
    {
        return $this->resolved[$kind->value] ??= app($kind->adapter());
    }

    /**
     * Every kind's adapter, in picker order.
     *
     * @return list<ProviderAdapter>
     */
    public function all(): array
    {
        return array_map($this->for(...), LlmProviderKind::cases());
    }
}
