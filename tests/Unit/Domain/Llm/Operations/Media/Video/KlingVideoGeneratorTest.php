<?php

use Domain\Llm\Data\Video\VideoPromptData;
use Domain\Llm\Enums\VideoShape;
use Domain\Llm\Exceptions\VideoGenerationFailedException;
use Domain\Llm\Operations\Media\Video\KlingVideoGenerator;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

/**
 * Kling videos: the model in the path, the task looked up by id, and its
 * content rules (codes 1300 and 1301, or a failed task naming its risk
 * control) as refusals.
 */
beforeEach(function (): void {
    config([
        'services.kling.api_key' => 'k-key',
        'services.kling.endpoint' => 'https://api-singapore.kling.test',
        'services.kling.video_model' => 'kling-3.0-turbo',
    ]);
});

it('starts a video with the model in the path', function (): void {
    Http::fake(['*' => Http::response(['code' => 0, 'message' => 'SUCCEED', 'data' => ['id' => 'task-1', 'status' => 'submitted']])]);

    $task = app(KlingVideoGenerator::class)->start(new VideoPromptData('A paper boat', VideoShape::Portrait, 20));

    expect($task)->toBe('task-1');

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api-singapore.kling.test/text-to-video/kling-3.0-turbo'
        && $request->hasHeader('Authorization', 'Bearer k-key')
        && $request['settings'] === ['aspect_ratio' => '9:16', 'duration' => 15, 'resolution' => '720p']);
});

it('waits while the task runs, then fetches its video', function (): void {
    Http::fake([
        'api-singapore.kling.test/tasks*' => Http::sequence()
            ->push(['code' => 0, 'data' => [['id' => 'task-1', 'status' => 'processing']]])
            ->push(['code' => 0, 'data' => [['id' => 'task-1', 'status' => 'succeeded', 'outputs' => [['type' => 'video', 'url' => 'https://cdn.kling.test/v.mp4']]]]]),
        'cdn.kling.test/*' => Http::response('kling-bytes', 200, ['Content-Type' => 'video/mp4']),
    ]);

    $generator = app(KlingVideoGenerator::class);

    expect($generator->check('task-1'))->toBeNull();

    $video = $generator->check('task-1');

    expect(File::get($video->path))->toBe('kling-bytes');
    File::delete($video->path);

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api-singapore.kling.test/tasks?task_ids=task-1');
});

it('treats a blocked prompt as a refusal', function (): void {
    Http::fake(['*' => Http::response(['code' => 1301, 'message' => 'Trigger the platform\'s content security policy'], 400)]);

    expect(fn () => app(KlingVideoGenerator::class)->start(new VideoPromptData('No')))
        ->toThrow(fn (VideoGenerationFailedException $e) => expect($e->refused)->toBeTrue());
});

it('tells a task stopped by risk control from one that failed', function (string $message, bool $refused): void {
    Http::fake(['*' => Http::response(['code' => 0, 'data' => [['id' => 'task-2', 'status' => 'failed', 'message' => $message]]])]);

    expect(fn () => app(KlingVideoGenerator::class)->check('task-2'))
        ->toThrow(fn (VideoGenerationFailedException $e) => expect($e->refused)->toBe($refused));
})->with([
    'risk control' => ['Failure to pass the risk control system', true],
    'other' => ['Internal error', false],
]);
