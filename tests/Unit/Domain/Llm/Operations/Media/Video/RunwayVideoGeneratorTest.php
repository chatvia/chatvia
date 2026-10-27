<?php

use Domain\Llm\Data\Video\VideoPromptData;
use Domain\Llm\Enums\VideoResolution;
use Domain\Llm\Enums\VideoShape;
use Domain\Llm\Exceptions\VideoGenerationFailedException;
use Domain\Llm\Operations\Media\Video\RunwayVideoGenerator;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

/**
 * Runway videos: the shape as a pixel size (portrait swaps the sides), the
 * API version on every call, a `SAFETY` failure as a refusal, and the task
 * deleted once its video is saved.
 */
beforeEach(function (): void {
    config(['services.runway.api_key' => 'rw-key', 'services.runway.video_model' => 'gen4.5']);
});

it('starts a video at a pixel size the model makes', function (): void {
    Http::fake(['*' => Http::response(['id' => 'task-1'])]);

    $task = app(RunwayVideoGenerator::class)->start(new VideoPromptData('A neon street', VideoShape::Portrait, 12, VideoResolution::Hd1080));

    expect($task)->toBe('task-1');

    Http::assertSent(fn (Request $request): bool => $request->url() === RunwayVideoGenerator::ENDPOINT.'/text_to_video'
        && $request->hasHeader('Authorization', 'Bearer rw-key')
        && $request->hasHeader('X-Runway-Version', RunwayVideoGenerator::API_VERSION)
        && $request['model'] === 'gen4.5'
        && $request['promptText'] === 'A neon street'
        && $request['ratio'] === '720:1280'
        && $request['duration'] === 10);
});

it('asks Veo for 1080p and one of its lengths', function (): void {
    config(['services.runway.video_model' => 'veo3.1_fast']);
    Http::fake(['*' => Http::response(['id' => 'task-2'])]);

    app(RunwayVideoGenerator::class)->start(new VideoPromptData('A neon street', VideoShape::Landscape, 7, VideoResolution::Hd1080));

    Http::assertSent(fn (Request $request): bool => $request['ratio'] === '1920:1080' && $request['duration'] === 8);
});

it('waits while the task runs, then fetches its video and deletes the task', function (): void {
    Http::fake([
        'api.dev.runwayml.com/v1/tasks/task-1' => Http::sequence()
            ->push(['id' => 'task-1', 'status' => 'RUNNING'])
            ->push(['id' => 'task-1', 'status' => 'SUCCEEDED', 'output' => ['https://dnznrvs05pmza.cloudfront.test/v.mp4']])
            ->push([], 204),
        'dnznrvs05pmza.cloudfront.test/*' => Http::response('rw-bytes', 200, ['Content-Type' => 'video/mp4']),
    ]);

    $generator = app(RunwayVideoGenerator::class);

    expect($generator->check('task-1'))->toBeNull();

    $video = $generator->check('task-1');
    $generator->forget('task-1');

    expect(File::get($video->path))->toBe('rw-bytes');
    File::delete($video->path);

    Http::assertSent(fn (Request $request): bool => $request->method() === 'DELETE' && $request->url() === RunwayVideoGenerator::ENDPOINT.'/tasks/task-1');
});

it('treats a safety failure as a refusal, and other failures as failures', function (string $code, bool $refused): void {
    Http::fake(['*' => Http::response(['id' => 'task-3', 'status' => 'FAILED', 'failure' => 'Stopped.', 'failureCode' => $code])]);

    expect(fn () => app(RunwayVideoGenerator::class)->check('task-3'))
        ->toThrow(fn (VideoGenerationFailedException $e) => expect($e->refused)->toBe($refused));
})->with([
    'safety' => ['SAFETY.INPUT.TEXT', true],
    'internal' => ['INTERNAL.BAD_OUTPUT.CODE01', false],
]);
