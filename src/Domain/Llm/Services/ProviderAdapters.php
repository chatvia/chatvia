<?php

declare(strict_types=1);

namespace Domain\Llm\Services;

use Domain\Llm\Enums\LlmProviderEnum;
use Domain\Llm\Exceptions\LlmProviderUnavailableException;
use Domain\Llm\Providers\Contracts\ProviderAdapter;

/**
 * The adapter of each provider kind ({@see LlmProviderEnum::adapter()}), and
 * the list of kinds the app can call, in picker order. Every question about a
 * provider (its address, key, client, models, Settings → AI service) goes
 * through here.
 */
class ProviderAdapters
{
    /** @var array<string, ProviderAdapter> */
    private array $resolved = [];

    /**
     * @throws LlmProviderUnavailableException when the app has no adapter for the kind yet
     */
    public function for(LlmProviderEnum $kind): ProviderAdapter
    {
        $class = $kind->adapter() ?? throw LlmProviderUnavailableException::unsupported($kind);

        return $this->resolved[$kind->value] ??= app($class);
    }

    public function has(LlmProviderEnum $kind): bool
    {
        return $kind->adapter() !== null;
    }

    /**
     * Every kind the app can call, in picker order.
     *
     * @return list<ProviderAdapter>
     */
    public function all(): array
    {
        return array_values(array_map(
            $this->for(...),
            array_filter(LlmProviderEnum::cases(), $this->has(...)),
        ));
    }
}
