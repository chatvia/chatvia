<?php

use Domain\Llm\Data\Image\ImagePromptData;
use Domain\Llm\Enums\ImageShape;
use Domain\Llm\Exceptions\ImageGenerationFailedException;
use Domain\Llm\Operations\Media\FalImageGenerator;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/**
 * fal.ai pictures: one call that waits for the picture (`fal.run`), the
 * shape in the field the model takes, and the picture fetched from its
 * link. A blocked prompt or a flagged picture is a refusal.
 */
beforeEach(function (): void {
    config(['services.fal.api_key' => 'fal-key', 'services.fal.image_model' => 'fal-ai/flux-2']);
});

it('draws the prompt and fetches the picture', function (): void {
    Http::fake([
        'fal.run/*' => Http::response(['images' => [['url' => 'https://v3.fal.media/files/cat.jpg', 'content_type' => 'image/jpeg']], 'has_nsfw_concepts' => [false]]),
        'v3.fal.media/*' => Http::response('jpg-bytes', 200, ['Content-Type' => 'image/jpeg']),
    ]);

    $generator = app(FalImageGenerator::class);
    $image = $generator->generate(new ImagePromptData('A cat on a roof', ImageShape::Landscape));

    expect($image->bytes)->toBe('jpg-bytes')
        ->and($image->mimeType)->toBe('image/jpeg');

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://fal.run/fal-ai/flux-2'
        && $request->hasHeader('Authorization', 'Key fal-key')
        && $request['prompt'] === 'A cat on a roof'
        && $request['image_size'] === 'landscape_4_3');
});

it('names the shape as a ratio for the models that take one', function (): void {
    config(['services.fal.image_model' => 'fal-ai/nano-banana-2']);
    Http::fake([
        'fal.run/*' => Http::response(['images' => [['url' => 'https://v3.fal.media/files/a.png']]]),
        'v3.fal.media/*' => Http::response('png', 200, ['Content-Type' => 'image/png']),
    ]);

    app(FalImageGenerator::class)->generate(new ImagePromptData('A fox', ImageShape::Portrait));

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://fal.run/fal-ai/nano-banana-2'
        && $request['aspect_ratio'] === '2:3'
        && ! isset($request['image_size']));
});

it('treats a blocked prompt or a flagged picture as a refusal', function (int $status, array $body): void {
    Http::fake(['fal.run/*' => Http::response($body, $status)]);

    expect(fn () => app(FalImageGenerator::class)->generate(new ImagePromptData('No')))
        ->toThrow(fn (ImageGenerationFailedException $e) => expect($e->refused)->toBeTrue());
})->with([
    'blocked prompt' => [422, ['detail' => [['type' => 'content_policy_violation', 'msg' => 'The content could not be processed.']]]],
    'flagged picture' => [200, ['images' => [['url' => 'https://v3.fal.media/files/black.png']], 'has_nsfw_concepts' => [true]]],
]);

it('fails, without a refusal, on an error or no picture', function (int $status, array $body): void {
    Http::fake(['fal.run/*' => Http::response($body, $status)]);

    expect(fn () => app(FalImageGenerator::class)->generate(new ImagePromptData('A fox')))
        ->toThrow(fn (ImageGenerationFailedException $e) => expect($e->refused)->toBeFalse());
})->with([
    'bad key' => [401, ['detail' => 'Invalid key']],
    'no picture' => [200, ['images' => []]],
]);
