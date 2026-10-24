<?php

declare(strict_types=1);

namespace Domain\Llm\Actions\Catalog;

use Domain\Llm\Models\LlmProvider;

/**
 * Reads the list of every connected provider that has a model marked
 * unlisted, so a model the provider serves again comes back on its own
 * ({@see CheckLlmListingAction}). Returns how many providers it asked.
 */
final readonly class RecheckUnlistedLlmsAction
{
    public function __construct(
        private CheckLlmListingAction $check,
    ) {}

    public function execute(): int
    {
        $providers = LlmProvider::query()
            ->where('is_enabled', true)
            ->whereHas('llms', fn ($query) => $query->whereNotNull('unlisted_at'))
            ->get()
            ->filter(fn (LlmProvider $provider): bool => $provider->isConnected());

        $providers->each(fn (LlmProvider $provider) => $this->check->execute($provider));

        return $providers->count();
    }
}
