<?php

declare(strict_types=1);

use Domain\Llm\Operations\Media\FalImageGenerator;
use Domain\Llm\Operations\Media\Video\ElevenLabsVideoGenerator;
use Domain\Llm\Operations\Media\Video\FalVideoGenerator;
use Domain\Llm\Operations\Media\Video\GoogleVideoGenerator;
use Domain\Llm\Operations\Media\Video\KlingVideoGenerator;
use Domain\Llm\Operations\Media\Video\RunwayVideoGenerator;
use Domain\Llm\Operations\Media\Video\SeedanceVideoGenerator;
use Domain\Llm\Services\MediaModelCatalog;
use Domain\Llm\Services\OpenRouterImageModels;
use Illuminate\Support\Facades\Http;

/**
 * The image and video models per service: the same ids each generator
 * knows, the service and model the admin picked, and the short names
 * chatters see.
 */
it('lists the same video models each generator knows', function (string $service, array $models): void {
    expect(array_keys(MediaModelCatalog::VIDEO_MODELS[$service]))->toEqualCanonicalizing(array_keys($models));
})->with([
    'google' => ['google', GoogleVideoGenerator::MODELS],
    'elevenlabs' => ['elevenlabs', ElevenLabsVideoGenerator::MODELS],
    'kling' => ['kling', KlingVideoGenerator::MODELS],
    'byteplus' => ['byteplus', SeedanceVideoGenerator::MODELS],
    'runway' => ['runway', RunwayVideoGenerator::MODELS],
    'fal' => ['fal', FalVideoGenerator::MODELS],
]);

it('lists the same fal.ai image models the generator knows', function (): void {
    expect(array_keys(MediaModelCatalog::IMAGE_MODELS['fal']))->toBe(array_keys(FalImageGenerator::MODELS));
});

it('reads the listed models of any service, dropping the ones no service offers', function (): void {
    config([
        'chatvia.ai.image_models' => ['google:gemini-3-pro-image', 'fal:fal-ai/unknown', 'kling:kling-4.0', 'gpt-image-2', 'openai:gpt-image-2', 'google:gemini-3-pro-image'],
        'chatvia.ai.default_image_model' => 'openai:gpt-image-2',
        'chatvia.ai.video_models' => [],
    ]);
    $catalog = app(MediaModelCatalog::class);

    expect(collect($catalog->imageModels())->map->key()->all())->toBe(['google:gemini-3-pro-image', 'openai:gpt-image-2'])
        ->and($catalog->defaultImage()?->key())->toBe('openai:gpt-image-2')
        ->and($catalog->imageEnabled())->toBeTrue()
        ->and($catalog->videoEnabled())->toBeFalse()
        ->and($catalog->defaultVideo())->toBeNull();
});

it('uses the first listed model when the default is not listed', function (): void {
    config(['chatvia.ai.video_models' => ['kling:kling-4.0', 'fal:fal-ai/veo3.1'], 'chatvia.ai.default_video_model' => 'runway:gen4.5']);

    expect(app(MediaModelCatalog::class)->defaultVideo()?->key())->toBe('kling:kling-4.0');
});

it('names a model without the admin\'s notes, adding the service when two share a name', function (): void {
    config(['chatvia.ai.video_models' => ['kling:kling-4.0', 'runway:veo3.1', 'fal:fal-ai/veo3.1']]);

    expect(collect(app(MediaModelCatalog::class)->videoModels())->map->name->all())->toBe([
        'Kling 4.0',
        'Google Veo 3.1 (Runway)',
        'Google Veo 3.1 (fal.ai)',
    ]);
});

it('keeps any OpenRouter model, naming its own picks before their note and its other models whole', function (): void {
    Http::fake(['openrouter.ai/*' => Http::response(['data' => [['id' => 'black-forest-labs/flux-2', 'name' => 'Black Forest Labs: FLUX.2', 'architecture' => ['output_modalities' => ['image']]]]])]);
    $curated = array_key_first(OpenRouterImageModels::CURATED);
    config(['chatvia.ai.image_models' => ["openrouter:{$curated}", 'openrouter:black-forest-labs/flux-2', 'openrouter:acme/gone:free']]);

    expect(collect(app(MediaModelCatalog::class)->imageModels())->map->name->all())->toBe([
        'Gemini Nano Banana 2.1',
        'Black Forest Labs: FLUX.2',
        'acme/gone:free',
    ]);
});
