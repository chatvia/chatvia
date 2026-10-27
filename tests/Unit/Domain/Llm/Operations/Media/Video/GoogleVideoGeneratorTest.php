<?php

use Domain\Llm\Data\Video\GeneratedVideoData;
use Domain\Llm\Data\Video\VideoPromptData;
use Domain\Llm\Enums\VideoResolution;
use Domain\Llm\Enums\VideoShape;
use Domain\Llm\Exceptions\VideoGenerationFailedException;
use Domain\Llm\Operations\Media\Video\GoogleVideoGenerator;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Google videos on the Gemini API: Gemini Omni Flash as a background
 * interaction (deleted once its video is saved), Veo 3 as a long-running
 * operation whose video is fetched with the key. Blocked videos are
 * refusals.
 */
beforeEach(function (): void {
    config([
        'services.gemini.api_key' => 'g-key',
        'services.gemini.api_endpoint' => 'https://generativelanguage.test/v1beta',
        'services.gemini.video_model' => 'gemini-omni-1.1-flash',
    ]);
});

it('starts Omni as a background interaction', function (): void {
    Http::fake(['*' => Http::response(['id' => 'int-1', 'status' => 'in_progress'])]);

    $task = app(GoogleVideoGenerator::class)->start(new VideoPromptData('Waves at dusk', VideoShape::Portrait, 8, VideoResolution::Hd1080));

    expect($task)->toBe('interactions/int-1');

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://generativelanguage.test/v1beta/interactions'
        && $request->hasHeader('x-goog-api-key', 'g-key')
        && $request['model'] === 'gemini-omni-1.1-flash'
        && $request['background'] === true
        && Str::startsWith($request['input'], 'Waves at dusk')
        && $request['response_format'] === ['type' => 'video', 'aspect_ratio' => '9:16', 'resolution' => '1080p']);
});

it('waits while the interaction runs, then saves its video and deletes it', function (): void {
    Http::fakeSequence('generativelanguage.test/v1beta/interactions/int-1')
        ->push(['id' => 'int-1', 'status' => 'in_progress'])
        ->push(['id' => 'int-1', 'status' => 'completed', 'steps' => [
            ['type' => 'model_output', 'content' => [['type' => 'video', 'mime_type' => 'video/mp4', 'data' => base64_encode('mp4-bytes')]]],
        ]])
        ->push([], 200);

    $generator = app(GoogleVideoGenerator::class);

    expect($generator->check('interactions/int-1'))->toBeNull();

    $video = $generator->check('interactions/int-1');

    expect($video)->toBeInstanceOf(GeneratedVideoData::class)
        ->and(File::get($video->path))->toBe('mp4-bytes')
        ->and($video->mimeType)->toBe('video/mp4');

    $generator->forget('interactions/int-1');
    File::delete($video->path);

    Http::assertSent(fn (Request $request): bool => $request->method() === 'DELETE' && $request->url() === 'https://generativelanguage.test/v1beta/interactions/int-1');
});

it('runs Veo 3 as an operation and fetches its video with the key', function (): void {
    config(['services.gemini.video_model' => 'veo-3.0-fast-generate-001']);
    Http::fake([
        'generativelanguage.test/v1beta/models/veo-3.0-fast-generate-001/operations/op-1' => Http::response(['done' => true, 'response' => ['generateVideoResponse' => ['generatedSamples' => [['video' => ['uri' => 'https://generativelanguage.test/v1beta/files/f1:download?alt=media']]]]]]),
        'generativelanguage.test/v1beta/models/*' => Http::response(['name' => 'models/veo-3.0-fast-generate-001/operations/op-1']),
        'generativelanguage.test/v1beta/files/*' => Http::response('veo-bytes', 200, ['Content-Type' => 'video/mp4']),
    ]);

    $generator = app(GoogleVideoGenerator::class);
    $task = $generator->start(new VideoPromptData('Waves', VideoShape::Portrait, 4, VideoResolution::Hd1080));
    $video = $generator->check($task);

    expect($task)->toBe('models/veo-3.0-fast-generate-001/operations/op-1')
        ->and(File::get($video->path))->toBe('veo-bytes');

    File::delete($video->path);

    Http::assertSent(fn (Request $request): bool => Str::endsWith($request->url(), ':predictLongRunning')
        && $request['parameters'] === ['aspectRatio' => '9:16', 'durationSeconds' => 8, 'resolution' => '720p']);
    Http::assertSent(fn (Request $request): bool => Str::contains($request->url(), '/files/f1') && $request->hasHeader('x-goog-api-key', 'g-key'));
});

it('treats a filtered Veo video as a refusal', function (): void {
    Http::fake(['*' => Http::response(['done' => true, 'response' => ['generateVideoResponse' => ['raiMediaFilteredCount' => 1, 'raiMediaFilteredReasons' => ['The video was blocked by safety filters.']]]])]);

    expect(fn () => app(GoogleVideoGenerator::class)->check('models/veo-3.0-generate-001/operations/op-2'))
        ->toThrow(fn (VideoGenerationFailedException $e) => expect($e->refused)->toBeTrue());
});

it('fails, without a refusal, when the interaction fails', function (): void {
    Http::fake(['*' => Http::response(['id' => 'int-2', 'status' => 'failed', 'error' => ['message' => 'Internal error']])]);

    expect(fn () => app(GoogleVideoGenerator::class)->check('interactions/int-2'))
        ->toThrow(fn (VideoGenerationFailedException $e) => expect($e->refused)->toBeFalse());
});
