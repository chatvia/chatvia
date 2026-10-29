<?php

declare(strict_types=1);

namespace Domain\Llm\Actions\Catalog;

use Domain\Llm\Data\Catalog\RemoteModelData;
use Domain\Llm\Enums\AiProvider;
use Domain\Llm\Enums\ModelListStatus;
use Domain\Llm\Events\LlmUnlisted;
use Domain\Llm\Models\Llm;
use Domain\Llm\Models\LlmProvider;
use Domain\Llm\Services\ModelListing;
use Support\Radar\IssueRaised;
use Support\Radar\IssueTypeKey;

/**
 * Reads a provider's model list to see whether it still serves the catalog's
 * models. A model the provider refused a call to and does not list is marked
 * unlisted, so it is no longer offered and the app's own tasks fall back to
 * another model; a marked model the provider lists again is cleared.
 *
 * Only a refused call marks a model: some lists leave out models that work
 * (aliases, deployments typed by name). A list that could not be read
 * changes nothing.
 */
final readonly class CheckLlmListingAction
{
    public function __construct(
        private ModelListing $listing,
    ) {}

    public function execute(LlmProvider $provider, ?Llm $refused = null): void
    {
        $list = $this->listing->list($provider);

        if ($list->status !== ModelListStatus::Listed) {
            return;
        }

        $listed = collect($list->models)->map(fn (RemoteModelData $model): string => $model->id);

        $provider->llms()
            ->whereNotNull('unlisted_at')
            ->whereIn('name', $listed)
            ->get()
            ->each(fn (Llm $llm): bool => $llm->update(['unlisted_at' => null]));

        if (! $refused instanceof Llm || $refused->unlisted_at !== null || $listed->contains($refused->name)) {
            return;
        }

        $refused->update(['unlisted_at' => now()]);

        LlmUnlisted::dispatch($refused->id);

        $account = AiProvider::forChatKind($provider->kind);

        if ($account instanceof AiProvider) {
            IssueRaised::dispatch($account->value, IssueTypeKey::MODEL_GONE, "{$account->label()} no longer lists the model {$refused->name}, so it is no longer offered.", model: $refused->name, subject: $refused->name);
        }
    }
}
