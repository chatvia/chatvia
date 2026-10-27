<?php

use Domain\Llm\Data\Video\VideoPromptData;
use Domain\Llm\Enums\VideoResolution;
use Domain\Llm\Enums\VideoShape;
use Domain\Llm\Exceptions\VideoGenerationFailedException;
use Domain\Llm\Operations\Media\Video\ElevenLabsVideoGenerator;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

/**
 * ElevenLabs videos (`/flows/video`): the length the model makes, the video
 * fetched from its signed link without the key, and a moderated video as a
 * refusal.
 */
beforeEach(function (): void {
    config([
        'services.elevenlabs.api_key' => 'el-key',
        'services.elevenlabs.endpoint' => 'https://api.elevenlabs.test/v1',
        'services.elevenlabs.video_model' => 'veo-3.1-fast-generate-001',
    ]);
});

it('starts a video at the nearest length the model makes', function (): void {
    Http::fake(['*' => Http::response(['id' => 'gen-1', 'status' => 'pending'])]);

    $task = app(ElevenLabsVideoGenerator::class)->start(new VideoPromptData('A red kite', VideoShape::Landscape, 5));

    expect($task)->toBe('gen-1');

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.elevenlabs.test/v1/flows/video'
        && $request->hasHeader('xi-api-key', 'el-key')
        && $request['model_id'] === 'veo-3.1-fast-generate-001'
        && $request['duration_secs'] === 6
        && $request['aspect_ratio'] === '16:9'
        && $request['resolution'] === '720p');
});

it('makes Veo 1080p at its longest length', function (): void {
    Http::fake(['*' => Http::response(['id' => 'gen-2', 'status' => 'pending'])]);

    app(ElevenLabsVideoGenerator::class)->start(new VideoPromptData('A red kite', VideoShape::Landscape, 4, VideoResolution::Hd1080));

    Http::assertSent(fn (Request $request): bool => $request['duration_secs'] === 8 && $request['resolution'] === '1080p');
});

it('waits while it is made, then fetches it without the key', function (): void {
    Http::fake([
        'api.elevenlabs.test/v1/flows/video/gen-1' => Http::sequence()
            ->push(['id' => 'gen-1', 'status' => 'generating'])
            ->push(['id' => 'gen-1', 'status' => 'completed', 'content_url' => 'https://cdn.elevenlabs.test/v.mp4', 'content_mime_type' => 'video/mp4']),
        'cdn.elevenlabs.test/*' => Http::response('el-bytes', 200, ['Content-Type' => 'video/mp4']),
    ]);

    $generator = app(ElevenLabsVideoGenerator::class);

    expect($generator->check('gen-1'))->toBeNull();

    $video = $generator->check('gen-1');

    expect(File::get($video->path))->toBe('el-bytes');
    File::delete($video->path);

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://cdn.elevenlabs.test/v.mp4' && ! $request->hasHeader('xi-api-key'));
});

it('treats a moderated video as a refusal, and other failures as failures', function (string $reason, bool $refused): void {
    Http::fake(['*' => Http::response(['id' => 'gen-3', 'status' => 'failed', 'failure_reason' => $reason, 'error_message' => 'No.'])]);

    expect(fn () => app(ElevenLabsVideoGenerator::class)->check('gen-3'))
        ->toThrow(fn (VideoGenerationFailedException $e) => expect($e->refused)->toBe($refused));
})->with([
    'moderated' => ['moderated', true],
    'model error' => ['model_error', false],
]);
