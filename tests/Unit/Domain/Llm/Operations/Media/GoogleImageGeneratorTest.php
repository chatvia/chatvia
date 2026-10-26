<?php

use Domain\Llm\Data\Image\ImagePromptData;
use Domain\Llm\Enums\ImageShape;
use Domain\Llm\Exceptions\ImageGenerationFailedException;
use Domain\Llm\Operations\Media\GoogleImageGenerator;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/**
 * Google pictures: Gemini's own `:generateContent` with image output and the
 * shape as an aspect ratio. The picture is an `inlineData` part; a blocked
 * prompt or picture is a refusal, a missing one a failure.
 */
beforeEach(function (): void {
    config([
        'services.gemini.api_key' => 'g-key',
        'services.gemini.api_endpoint' => 'https://generativelanguage.test/v1beta',
        'services.gemini.image_model' => 'gemini-nano-banana-2.1',
    ]);
});

it('draws the prompt and reads the inline picture', function (): void {
    Http::fake(['*' => Http::response(['candidates' => [['finishReason' => 'STOP', 'content' => ['parts' => [
        ['text' => 'Here is your picture.'],
        ['inlineData' => ['mimeType' => 'image/png', 'data' => base64_encode('png-bytes')]],
    ]]]]])]);

    $generator = app(GoogleImageGenerator::class);
    $image = $generator->generate(new ImagePromptData('A cat on a roof', ImageShape::Square));

    expect($image->bytes)->toBe('png-bytes')
        ->and($image->mimeType)->toBe('image/png')
        ->and($generator->name())->toBe('google gemini-nano-banana-2.1');

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://generativelanguage.test/v1beta/models/gemini-nano-banana-2.1:generateContent'
        && $request->hasHeader('x-goog-api-key', 'g-key')
        && $request['contents'][0]['parts'][0]['text'] === 'A cat on a roof'
        && $request['generationConfig']['responseModalities'] === ['TEXT', 'IMAGE']
        && $request['generationConfig']['imageConfig'] === ['aspectRatio' => '1:1']);
});

it('treats a blocked prompt or picture as a refusal', function (array $body): void {
    Http::fake(['*' => Http::response($body)]);

    expect(fn () => app(GoogleImageGenerator::class)->generate(new ImagePromptData('No')))
        ->toThrow(fn (ImageGenerationFailedException $e) => expect($e->refused)->toBeTrue());
})->with([
    'blocked prompt' => [['promptFeedback' => ['blockReason' => 'PROHIBITED_CONTENT']]],
    'blocked picture' => [['candidates' => [['finishReason' => 'IMAGE_SAFETY', 'content' => ['parts' => []]]]]],
]);

it('fails, without a refusal, on an error or no picture', function (int $status, array $body): void {
    Http::fake(['*' => Http::response($body, $status)]);

    expect(fn () => app(GoogleImageGenerator::class)->generate(new ImagePromptData('A fox')))
        ->toThrow(fn (ImageGenerationFailedException $e) => expect($e->refused)->toBeFalse());
})->with([
    'bad key' => [400, ['error' => ['code' => 400, 'message' => 'API key not valid.', 'status' => 'INVALID_ARGUMENT']]],
    'no picture' => [200, ['candidates' => [['finishReason' => 'NO_IMAGE', 'content' => ['parts' => [['text' => 'Sorry']]]]]]],
]);
