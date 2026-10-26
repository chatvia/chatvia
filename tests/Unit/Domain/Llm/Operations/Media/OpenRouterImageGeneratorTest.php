<?php

use Domain\Llm\Data\Image\ImagePromptData;
use Domain\Llm\Enums\ImageShape;
use Domain\Llm\Exceptions\ImageGenerationFailedException;
use Domain\Llm\Operations\Media\OpenRouterImageGenerator;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * OpenRouter pictures: chat completions with image output, the shape as an
 * aspect ratio, and the picture read from `message.images` as a data URL. A
 * model that writes only pictures is asked for pictures only. Moderation
 * (403) and the model's safety filter are refusals.
 */
beforeEach(function (): void {
    config([
        'services.openrouter.api_key' => 'or-key',
        'services.openrouter.image_model' => 'google/gemini-nano-banana-2.1',
    ]);
});

function origModels(): array
{
    return ['data' => [
        ['id' => 'google/gemini-nano-banana-2.1', 'name' => 'Nano Banana 2.1', 'architecture' => ['output_modalities' => ['image', 'text']]],
        ['id' => 'black-forest-labs/flux-3-image', 'name' => 'FLUX 3', 'architecture' => ['output_modalities' => ['image']]],
    ]];
}

function origReply(array $message, string $finish = 'stop'): array
{
    return ['choices' => [['finish_reason' => $finish, 'message' => ['role' => 'assistant', 'content' => ''] + $message]]];
}

it('draws the prompt and reads the picture from the data URL', function (): void {
    Http::fake([
        'openrouter.ai/api/v1/models*' => Http::response(origModels()),
        'openrouter.ai/api/v1/chat/completions' => Http::response(origReply(['images' => [
            ['type' => 'image_url', 'image_url' => ['url' => 'data:image/webp;base64,'.base64_encode('webp-bytes')]],
        ]])),
    ]);

    $generator = app(OpenRouterImageGenerator::class);
    $image = $generator->generate(new ImagePromptData('A lighthouse', ImageShape::Portrait));

    expect($image->bytes)->toBe('webp-bytes')
        ->and($image->mimeType)->toBe('image/webp')
        ->and($image->extension())->toBe('webp')
        ->and($generator->name())->toBe('openrouter google/gemini-nano-banana-2.1');

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://openrouter.ai/api/v1/chat/completions'
        && $request->hasHeader('Authorization', 'Bearer or-key')
        && $request['model'] === 'google/gemini-nano-banana-2.1'
        && $request['modalities'] === ['image', 'text']
        && $request['image_config'] === ['aspect_ratio' => '2:3']
        && $request['messages'] === [['role' => 'user', 'content' => 'A lighthouse']]);
});

it('asks a model that only draws for pictures only', function (): void {
    config(['services.openrouter.image_model' => 'black-forest-labs/flux-3-image']);
    Http::fake([
        'openrouter.ai/api/v1/models*' => Http::response(origModels()),
        'openrouter.ai/api/v1/chat/completions' => Http::response(origReply(['images' => [['image_url' => ['url' => 'data:image/png;base64,'.base64_encode('png')]]]])),
    ]);

    app(OpenRouterImageGenerator::class)->generate(new ImagePromptData('A lighthouse'));

    Http::assertSent(fn (Request $request): bool => Str::endsWith($request->url(), '/chat/completions') && $request['modalities'] === ['image']);
});

it('treats moderation and the safety filter as refusals', function (int $status, array $body): void {
    Http::fake([
        'openrouter.ai/api/v1/models*' => Http::response(origModels()),
        'openrouter.ai/api/v1/chat/completions' => Http::response($body, $status),
    ]);

    expect(fn () => app(OpenRouterImageGenerator::class)->generate(new ImagePromptData('No')))
        ->toThrow(fn (ImageGenerationFailedException $e) => expect($e->refused)->toBeTrue());
})->with([
    'moderation' => [403, ['error' => ['code' => 403, 'message' => 'Your input was flagged', 'metadata' => ['reasons' => ['violence']]]]],
    'safety filter' => [200, ['choices' => [['finish_reason' => 'content_filter', 'native_finish_reason' => 'IMAGE_SAFETY', 'message' => ['content' => '']]]]],
]);

it('fails, without a refusal, on another error or no picture', function (int $status, array $body): void {
    Http::fake([
        'openrouter.ai/api/v1/models*' => Http::response(origModels()),
        'openrouter.ai/api/v1/chat/completions' => Http::response($body, $status),
    ]);

    expect(fn () => app(OpenRouterImageGenerator::class)->generate(new ImagePromptData('A fox')))
        ->toThrow(fn (ImageGenerationFailedException $e) => expect($e->refused)->toBeFalse());
})->with([
    'out of credits' => [402, ['error' => ['code' => 402, 'message' => 'Insufficient credits']]],
    'text only' => [200, origReply(['content' => 'I made it'])],
]);
