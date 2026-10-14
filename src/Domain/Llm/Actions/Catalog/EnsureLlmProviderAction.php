<?php

declare(strict_types=1);

namespace Domain\Llm\Actions\Catalog;

use Domain\Llm\Enums\LlmProviderEnum;
use Domain\Llm\Models\LlmProvider;
use Domain\Llm\Services\ProviderAdapters;
use InvalidArgumentException;

/**
 * The one provider row of a kind with an account (OpenRouter, Scaleway AI,
 * Mistral…), created the first time one of its models is added. The row
 * holds no key or address: it calls with its account's. A server of your
 * own is added with its address instead ({@see SaveCustomLlmServerAction}).
 */
final readonly class EnsureLlmProviderAction
{
    public function __construct(
        private ProviderAdapters $adapters,
    ) {}

    public function execute(LlmProviderEnum $kind): LlmProvider
    {
        if ($kind === LlmProviderEnum::CUSTOM) {
            throw new InvalidArgumentException('A server of your own needs an address: save it as a custom server.');
        }

        return LlmProvider::query()
            ->where('kind', $kind)
            ->orderBy('id')
            ->firstOr(fn (): LlmProvider => LlmProvider::query()->create([
                'kind' => $kind,
                'name' => $this->adapters->for($kind)->label(),
            ]));
    }
}
