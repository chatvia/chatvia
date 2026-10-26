<?php

use Domain\Llm\Data\Image\ImagePromptData;
use Domain\Llm\Enums\ImageShape;
use Domain\Llm\Exceptions\ImageGenerationFailedException;
use Domain\Llm\Operations\Media\OpenAiImageGenerator;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Spectra\Spectra;

/**
 * OpenAI pictures (Settings → AI models → Image generation): one Responses
 * call that must use the image tool, not stored, with the configured image
 * model and the shape's size. The picture is the tool call's base64 result;
 * a blocked prompt is a refusal, anything else a failure.
 */
beforeEach(function (): void {
    config([
        'services.openai.api_key' => 'sk-test',
        'services.openai.api_endpoint' => 'https://api.openai.test/v1',
        'services.openai.image_model' => 'gpt-image-2.5-flare',
    ]);
});

function oaigResponse(array $output): array
{
    return ['id' => 'resp_1', 'status' => 'completed', 'output' => $output];
}

it('draws the prompt with the image tool and returns the PNG', function (): void {
    Http::fake(['api.openai.test/v1/responses' => Http::response(oaigResponse([
        ['type' => 'image_generation_call', 'id' => 'ig_1', 'status' => 'completed', 'result' => base64_encode('png-bytes')],
    ]))]);

    $generator = app(OpenAiImageGenerator::class);
    $image = $generator->generate(new ImagePromptData('A red fox in snow', ImageShape::Landscape));

    expect($image->bytes)->toBe('png-bytes')
        ->and($image->mimeType)->toBe('image/png')
        ->and($image->extension())->toBe('png')
        ->and($generator->name())->toBe('openai gpt-image-2.5-flare')
        ->and($generator->isEnabled())->toBeTrue();

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.openai.test/v1/responses'
        && $request->hasHeader('Authorization', 'Bearer sk-test')
        && $request['store'] === false
        && $request['input'] === 'A red fox in snow'
        && $request['tools'][0]['type'] === 'image_generation'
        && $request['tools'][0]['model'] === 'gpt-image-2.5-flare'
        && $request['tools'][0]['size'] === '1536x1024'
        && $request['tool_choice'] === ['type' => 'image_generation']);
});

it('tags the call as image generation', function (): void {
    config(['spectra.enabled' => true]);
    $tags = [];
    Http::fake(['*' => function () use (&$tags) {
        $tags = app(Spectra::class)->startRequest('openai', 'gpt-image-2.5-flare')->tags;

        return Http::response(oaigResponse([['type' => 'image_generation_call', 'result' => base64_encode('png')]]));
    }]);

    app(OpenAiImageGenerator::class)->generate(new ImagePromptData('A fox'));

    expect($tags)->toContain('image-generation');
});

it('treats a blocked prompt as a refusal', function (): void {
    Http::fake(['*' => Http::response(['error' => ['message' => 'Your request was rejected by the safety system.', 'code' => 'moderation_blocked']], 400)]);

    try {
        app(OpenAiImageGenerator::class)->generate(new ImagePromptData('Something not allowed'));
        $this->fail('No exception');
    } catch (ImageGenerationFailedException $e) {
        expect($e->refused)->toBeTrue()
            ->and($e->getMessage())->toContain('rejected by the safety system');
    }
});

it('treats a refusal message instead of a picture as a refusal', function (): void {
    Http::fake(['*' => Http::response(oaigResponse([
        ['type' => 'message', 'role' => 'assistant', 'content' => [['type' => 'refusal', 'refusal' => 'I cannot help with that.']]],
    ]))]);

    expect(fn () => app(OpenAiImageGenerator::class)->generate(new ImagePromptData('No')))
        ->toThrow(fn (ImageGenerationFailedException $e) => expect($e->refused)->toBeTrue());
});

it('fails, without a refusal, on another error or no picture', function (int $status, array $body, string $message): void {
    Http::fake(['*' => Http::response($body, $status)]);

    expect(fn () => app(OpenAiImageGenerator::class)->generate(new ImagePromptData('A fox')))
        ->toThrow(fn (ImageGenerationFailedException $e) => expect($e->refused)->toBeFalse()->and($e->getMessage())->toContain($message));
})->with([
    'bad key' => [401, ['error' => ['message' => 'Incorrect API key provided', 'code' => 'invalid_api_key']], 'Incorrect API key provided'],
    'no picture' => [200, ['status' => 'completed', 'output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => 'Here you go']]]]], 'sent no image'],
]);
