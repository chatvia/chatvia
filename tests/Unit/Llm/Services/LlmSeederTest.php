<?php

use Database\Seeders\LlmSeeder;
use Domain\Llm\Enums\LlmProviderEnum;
use Domain\Llm\Models\Llm;
use Domain\Llm\Models\LlmProvider;

/**
 * A demo install starts with the catalog of `database/data/llms.json` (each
 * provider calling with its account's key, Settings → AI providers), but the
 * seeder never touches a catalog an admin has already set up.
 */
it('adds the starter catalog to an install without providers', function (): void {
    LlmProvider::query()->delete();
    config(['services.scalewayai.api_key' => 'scw-key']);

    $this->seed(LlmSeeder::class);

    $provider = LlmProvider::query()->sole();
    $starter = collect(json_decode(file_get_contents(database_path('data/llms.json')), true));

    expect($provider->kind)->toBe(LlmProviderEnum::SCALEWAY)
        ->and($provider->api_key)->toBeNull()
        ->and($provider->isConnected())->toBeTrue()
        ->and(Llm::query()->pluck('internal_name')->sort()->values()->all())->toBe($starter->pluck('internal_name')->sort()->values()->all());
});

it('leaves a catalog an admin set up alone', function (): void {
    LlmProvider::query()->delete();
    $own = testLlm('my-model');

    $this->seed(LlmSeeder::class);

    expect(LlmProvider::query()->pluck('id')->all())->toBe([$own->llm_provider_id])
        ->and(Llm::query()->pluck('internal_name')->all())->toBe(['my-model']);
});
