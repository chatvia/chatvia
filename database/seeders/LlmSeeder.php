<?php

namespace Database\Seeders;

use Domain\Llm\Actions\Catalog\EnsureLlmProviderAction;
use Domain\Llm\Enums\LlmProviderKind;
use Domain\Llm\Models\Llm;
use Domain\Llm\Models\LlmProvider;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use JsonException;

/**
 * The starter catalog of a demo database (DatabaseSeeder): the providers and
 * models in `database/data/llms.json`, added only while no provider exists,
 * so it never changes a catalog an admin has set up. A provider calls with
 * its account's key (Settings → AI providers). A real install gets its
 * catalog from the admin setup and Settings → AI models instead.
 */
class LlmSeeder extends Seeder
{
    public function run(): void
    {
        if (LlmProvider::query()->exists()) {
            $this->command->info('LLM providers already set up, skipping the starter catalog.');

            return;
        }

        $jsonPath = database_path('data/llms.json');

        if (! File::exists($jsonPath)) {
            $this->command->error("LLM data file not found at: {$jsonPath}");

            return;
        }

        try {
            $llms = File::json($jsonPath, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            $this->command->error("Failed to parse LLM data file: {$e->getMessage()}");

            return;
        }

        foreach ($llms as $llm) {
            // One row per provider; it calls with its account's key
            // (Settings → AI providers).
            $provider = app(EnsureLlmProviderAction::class)->execute(LlmProviderKind::from($llm['provider'] ?? 'scaleway'));

            // Replaced by the models.dev entry once the catalog is read.
            $llm['capabilities'] = ['reasoning' => $llm['reasoning'] ?? false];
            unset($llm['reasoning'], $llm['provider']);

            Llm::query()->updateOrCreate(
                ['llm_provider_id' => $provider->id, 'name' => $llm['name']],
                $llm,
            );
        }

        $this->command->info('Added the starter catalog: '.count($llms).' LLM models.');
    }
}
