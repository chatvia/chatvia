<?php

declare(strict_types=1);

namespace Domain\Llm\Actions\Catalog;

use Domain\Llm\Data\Catalog\SyncedLlmFactsData;
use Domain\Llm\Models\Llm;
use Domain\Llm\Services\ModelsDevCatalog;

/**
 * Brings every enabled model's capabilities up to date from the models.dev
 * catalog: whether it reasons, its effort values, whether it takes a
 * temperature. The catalog is the reference for a model it lists; a model it
 * does not list keeps what the admin set.
 */
final readonly class SyncLlmFactsAction
{
    public function __construct(
        private ModelsDevCatalog $catalog,
    ) {}

    public function execute(): SyncedLlmFactsData
    {
        $this->catalog->refresh();

        $updated = 0;
        $notListed = 0;

        Llm::query()->with('provider')->where('is_enabled', true)->get()->each(function (Llm $llm) use (&$updated, &$notListed): void {
            $entry = $this->catalog->entry($llm->provider->kind, $llm->internal_name);

            if ($entry === null) {
                $notListed++;

                return;
            }

            $llm->capabilities = $entry;

            if ($llm->isDirty()) {
                $llm->save();
                $updated++;
            }
        });

        return new SyncedLlmFactsData($updated, $notListed);
    }
}
