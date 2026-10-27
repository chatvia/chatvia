<?php

use Domain\Llm\Data\Video\VideoPromptData;
use Domain\Llm\Enums\VideoResolution;
use Domain\Llm\Enums\VideoShape;
use Domain\Llm\Exceptions\VideoGenerationFailedException;
use Domain\Llm\Operations\Media\Video\FalVideoGenerator;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

/**
 * fal.ai videos through its queue: the request's queue path is the task,
 * each model gets its length and size in its own fields (Wan by frames),
 * and a blocked prompt is a refusal.
 */
beforeEach(function (): void {
    config(['services.fal.api_key' => 'fal-key', 'services.fal.video_model' => 'fal-ai/wan/v2.2-a14b/text-to-video']);
});

it('queues Wan with its length in frames, and keeps the queue path', function (): void {
    Http::fake(['queue.fal.run/*' => Http::response([
        'request_id' => 'req-1',
        'response_url' => 'https://queue.fal.run/fal-ai/wan/requests/req-1',
        'status_url' => 'https://queue.fal.run/fal-ai/wan/requests/req-1/status',
    ])]);

    $task = app(FalVideoGenerator::class)->start(new VideoPromptData('A koi pond', VideoShape::Landscape, 5));

    expect($task)->toBe('fal-ai/wan/requests/req-1');

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://queue.fal.run/fal-ai/wan/v2.2-a14b/text-to-video'
        && $request->hasHeader('Authorization', 'Key fal-key')
        && $request['num_frames'] === 81
        && $request['aspect_ratio'] === '16:9'
        && $request['enable_safety_checker'] === true);
});

it('asks Veo for a length in its own words', function (): void {
    config(['services.fal.video_model' => 'fal-ai/veo3.1/fast']);
    Http::fake(['queue.fal.run/*' => Http::response(['request_id' => 'req-2'])]);

    $task = app(FalVideoGenerator::class)->start(new VideoPromptData('A koi pond', VideoShape::Portrait, 5, VideoResolution::Hd1080));

    expect($task)->toBe('fal-ai/veo3.1/requests/req-2');

    Http::assertSent(fn (Request $request): bool => $request['duration'] === '8s'
        && $request['resolution'] === '1080p'
        && $request['aspect_ratio'] === '9:16');
});

it('waits while the request is queued, then fetches its video', function (): void {
    Http::fake([
        'queue.fal.run/fal-ai/wan/requests/req-1/status' => Http::sequence()
            ->push(['status' => 'IN_PROGRESS'])
            ->push(['status' => 'COMPLETED']),
        'queue.fal.run/fal-ai/wan/requests/req-1' => Http::response(['video' => ['url' => 'https://v3.fal.media/files/v.mp4']]),
        'v3.fal.media/*' => Http::response('fal-bytes', 200, ['Content-Type' => 'video/mp4']),
    ]);

    $generator = app(FalVideoGenerator::class);

    expect($generator->check('fal-ai/wan/requests/req-1'))->toBeNull();

    $video = $generator->check('fal-ai/wan/requests/req-1');

    expect(File::get($video->path))->toBe('fal-bytes');
    File::delete($video->path);
});

it('treats a blocked prompt as a refusal, and other failures as failures', function (string $type, bool $refused): void {
    Http::fake(['queue.fal.run/*' => Http::response(['status' => 'COMPLETED', 'error' => 'Stopped.', 'error_type' => $type])]);

    expect(fn () => app(FalVideoGenerator::class)->check('fal-ai/wan/requests/req-3'))
        ->toThrow(fn (VideoGenerationFailedException $e) => expect($e->refused)->toBe($refused));
})->with([
    'blocked' => ['content_policy_violation', true],
    'runner' => ['runner_server_error', false],
]);
