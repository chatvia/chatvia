<?php

use Domain\Llm\Data\Video\VideoPromptData;
use Domain\Llm\Enums\VideoResolution;
use Domain\Llm\Enums\VideoShape;
use Domain\Llm\Exceptions\VideoGenerationFailedException;
use Domain\Llm\Operations\Media\Video\SeedanceVideoGenerator;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Seedance videos through BytePlus ModelArk: a generation task with the
 * text as its content, the video at `content.video_url`, flagged content as
 * a refusal, and the task deleted once its video is saved.
 */
beforeEach(function (): void {
    config([
        'services.byteplus.api_key' => 'ark-key',
        'services.byteplus.endpoint' => 'https://ark.byteplus.test/api/v3',
        'services.byteplus.video_model' => 'dreamina-seedance-2-0-fast-260128',
    ]);
});

it('starts a task, keeping 720p on the fast model', function (): void {
    Http::fake(['*' => Http::response(['id' => 'cgt-1'])]);

    $task = app(SeedanceVideoGenerator::class)->start(new VideoPromptData('A lantern festival', VideoShape::Landscape, 2, VideoResolution::Hd1080));

    expect($task)->toBe('cgt-1');

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://ark.byteplus.test/api/v3/contents/generations/tasks'
        && $request->hasHeader('Authorization', 'Bearer ark-key')
        && $request['content'] === [['type' => 'text', 'text' => 'A lantern festival']]
        && $request['ratio'] === '16:9'
        && $request['duration'] === 4
        && $request['resolution'] === '720p'
        && $request['watermark'] === false);
});

it('waits while the task runs, then fetches its video and deletes the task', function (): void {
    Http::fake([
        'ark.byteplus.test/api/v3/contents/generations/tasks/cgt-1' => Http::sequence()
            ->push(['id' => 'cgt-1', 'status' => 'running'])
            ->push(['id' => 'cgt-1', 'status' => 'succeeded', 'content' => ['video_url' => 'https://tos.byteplus.test/v.mp4']])
            ->push([], 200),
        'tos.byteplus.test/*' => Http::response('seed-bytes', 200, ['Content-Type' => 'video/mp4']),
    ]);

    $generator = app(SeedanceVideoGenerator::class);

    expect($generator->check('cgt-1'))->toBeNull();

    $video = $generator->check('cgt-1');
    $generator->forget('cgt-1');

    expect(File::get($video->path))->toBe('seed-bytes');
    File::delete($video->path);

    Http::assertSent(fn (Request $request): bool => $request->method() === 'DELETE' && Str::endsWith($request->url(), '/tasks/cgt-1'));
});

it('treats flagged content as a refusal, and other failures as failures', function (string $code, bool $refused): void {
    Http::fake(['*' => Http::response(['id' => 'cgt-2', 'status' => 'failed', 'error' => ['code' => $code, 'message' => 'Stopped.']])]);

    expect(fn () => app(SeedanceVideoGenerator::class)->check('cgt-2'))
        ->toThrow(fn (VideoGenerationFailedException $e) => expect($e->refused)->toBe($refused));
})->with([
    'flagged' => ['OutputVideoSensitiveContentDetected', true],
    'no pack' => ['ModelNotOpen', false],
]);
