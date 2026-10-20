<?php

declare(strict_types=1);

namespace App\Admin\Settings\Listeners;

use App\Admin\Settings\AiSettings;
use App\Admin\Settings\Jobs\ReindexEmbeddingsJob;
use Illuminate\Support\Collection;
use Spatie\LaravelSettings\Events\SavingSettings;

/**
 * When the embedding service or its model changes (Settings → AI models, the
 * admin setup, `chatvia:settings:set`), embeds everything stored as vectors again:
 * knowledge and tools. Search compares the question's vector with the stored
 * ones, and vectors of two models cannot be compared. A change of the model
 * of a service not in use changes nothing.
 *
 * It runs while the settings are being saved, when the old values are still
 * known, and starts the work after the response, once the new values apply.
 */
class ReindexForNewEmbeddingModel
{
    public function handle(SavingSettings $event): void
    {
        if (! $event->settings instanceof AiSettings || ! $event->originalValues instanceof Collection) {
            return;
        }

        if (AiSettings::embeddingFor($event->originalValues->all()) !== AiSettings::embeddingFor($event->properties->all())) {
            ReindexEmbeddingsJob::dispatchAfterResponse();
        }
    }
}
