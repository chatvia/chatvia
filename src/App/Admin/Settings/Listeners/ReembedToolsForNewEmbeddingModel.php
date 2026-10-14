<?php

declare(strict_types=1);

namespace App\Admin\Settings\Listeners;

use App\Admin\Settings\AiSettings;
use Domain\Integration\Tool\Jobs\ReembedToolVectorsJob;
use Illuminate\Support\Collection;
use Spatie\LaravelSettings\Events\SavingSettings;

/**
 * When an admin changes the embedding provider or an embedding model
 * (Settings → AI models, or the admin setup), embeds every tool again: tool routing
 * compares the visitor's question with the tools' vectors, and vectors of two
 * models cannot be compared. Knowledge sources need no step here: the panel
 * locks the embedding once a source is trained.
 *
 * It runs while the settings are being saved, when the old values are still
 * known, and starts the work after the response, once the new values apply.
 */
class ReembedToolsForNewEmbeddingModel
{
    public function handle(SavingSettings $event): void
    {
        if (! $event->settings instanceof AiSettings || ! $event->originalValues instanceof Collection) {
            return;
        }

        foreach ($event->properties as $property => $value) {
            $shapesVectors = $property === 'embedding_provider' || str_ends_with((string) $property, '_embedding_model');

            if ($shapesVectors && $value !== $event->originalValues->get($property)) {
                ReembedToolVectorsJob::dispatchAfterResponse();

                return;
            }
        }
    }
}
