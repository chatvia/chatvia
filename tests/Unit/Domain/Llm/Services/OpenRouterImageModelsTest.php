<?php

use Domain\Llm\Services\OpenRouterImageModels;
use Illuminate\Support\Facades\Http;

/**
 * The OpenRouter image models of the Image generation card: the curated ones
 * first, then every other one OpenRouter lists with image output, and which
 * of them write text too.
 */
it('lists the curated models first, then the others OpenRouter lists', function (): void {
    Http::fake(['openrouter.ai/api/v1/models*' => Http::response(['data' => [
        ['id' => 'google/gemini-nano-banana-2.1', 'name' => 'Nano Banana 2.1', 'architecture' => ['output_modalities' => ['image', 'text']]],
        ['id' => 'black-forest-labs/flux-3-image', 'name' => 'FLUX 3', 'architecture' => ['output_modalities' => ['image']]],
    ]])]);

    $models = app(OpenRouterImageModels::class);

    expect(array_keys($models->options()))->toBe([...array_keys(OpenRouterImageModels::CURATED), 'black-forest-labs/flux-3-image'])
        ->and($models->options()['black-forest-labs/flux-3-image'])->toBe('FLUX 3')
        ->and($models->writesText('google/gemini-nano-banana-2.1'))->toBeTrue()
        ->and($models->writesText('black-forest-labs/flux-3-image'))->toBeFalse();

    Http::assertSent(fn ($request): bool => $request->url() === 'https://openrouter.ai/api/v1/models?output_modalities=image');
});
