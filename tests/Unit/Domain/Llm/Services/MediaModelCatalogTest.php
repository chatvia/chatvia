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

it('reads the service in use and the admin\'s model', function (): void {
    config([
        'chatvia.ai.image_generator' => 'google',
        'services.gemini.image_model' => 'gemini-3-pro-image',
        'chatvia.ai.video_generator' => 'auto',
    ]);
    $catalog = app(MediaModelCatalog::class);

    expect($catalog->imageService())->toBe('google')
        ->and($catalog->defaultImageModel())->toBe('gemini-3-pro-image')
        ->and($catalog->imageModels())->toBe(MediaModelCatalog::IMAGE_MODELS['google'])
        ->and($catalog->videoService())->toBe('none')
        ->and($catalog->defaultVideoModel())->toBe('')
        ->and($catalog->videoModels())->toBe([]);
});

it('names a model without the admin\'s notes', function (): void {
    config(['chatvia.ai.image_generator' => 'fal', 'chatvia.ai.video_generator' => 'kling']);
    $catalog = app(MediaModelCatalog::class);

    expect($catalog->imageName('fal-ai/qwen-image'))->toBe('Qwen-Image')
        ->and($catalog->imageName('fal-ai/unknown'))->toBe('fal-ai/unknown')
        ->and($catalog->videoName('kling-4.0'))->toBe('Kling 4.0');
});

it('names OpenRouter\'s own picks before their note, and its other models whole', function (): void {
    Http::fake(['openrouter.ai/*' => Http::response(['data' => [['id' => 'black-forest-labs/flux-2', 'name' => 'Black Forest Labs: FLUX.2', 'architecture' => ['output_modalities' => ['image']]]]])]);
    config(['chatvia.ai.image_generator' => 'openrouter']);
    $catalog = app(MediaModelCatalog::class);

    expect($catalog->imageName(array_key_first(OpenRouterImageModels::CURATED)))->toBe('Gemini Nano Banana 2.1')
        ->and($catalog->imageName('black-forest-labs/flux-2'))->toBe('Black Forest Labs: FLUX.2');
});
