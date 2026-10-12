<?php

namespace Database\Seeders;

use Domain\Llm\Enums\LlmProviderEnum;
use Domain\Llm\Models\Llm;
use Domain\Llm\Models\LlmProvider;
use Domain\Llm\Services\ProviderAdapters;
use Illuminate\Database\Seeder;

/**
 * The starter catalog of a demo database (DatabaseSeeder): the providers and
 * models in `database/data/llms.json`, added only while no provider exists,
 * so it never changes a catalog an admin has set up. A provider's key is
 * taken from the matching Settings → AI service when one is saved there. A
 * real install gets its catalog from the admin setup instead.
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

        if (! file_exists($jsonPath)) {
            $this->command->error('LLM data file not found at: '.$jsonPath);

            return;
        }

        $llms = json_decode(file_get_contents($jsonPath), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->command->error('Failed to parse LLM data file: '.json_last_error_msg());

            return;
        }

        foreach ($llms as $llm) {
            // The provider with the key and address of its Settings → AI
            // service, when it is one.
            $adapter = app(ProviderAdapters::class)->for(LlmProviderEnum::from($llm['provider'] ?? 'scaleway'));
            $settings = $adapter->fromSettings();

            $provider = LlmProvider::query()->firstOrCreate(['kind' => $adapter->kind()], [
                'name' => $adapter->label(),
                'base_url' => $settings->base_url,
                'api_key' => $settings->api_key,
            ]);

            $llm['features'] = [];
            $llm['is_reasoning'] = $llm['reasoning'] ?? false;
            unset($llm['reasoning'], $llm['provider']);

            Llm::query()->updateOrCreate(
                ['llm_provider_id' => $provider->id, 'internal_name' => $llm['internal_name']],
                $llm,
            );
        }

        $this->command->info('Added the starter catalog: '.count($llms).' LLM models.');
    }
}
