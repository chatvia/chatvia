<?php

declare(strict_types=1);

use App\Identity\Workspace\Models\GenerationLimit;
use Domain\Conversation\Actions\Media\CheckGeneratedVideoAction;
use Domain\Conversation\Enums\ConversationSource;
use Domain\Conversation\Enums\GeneratedMediaFailure;
use Domain\Conversation\Enums\GeneratedMediaStatus;
use Domain\Conversation\Events\GeneratedMediaUpdated;
use Domain\Conversation\Jobs\CheckGeneratedVideoJob;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\GeneratedMedia;
use Domain\Llm\Exceptions\VideoGenerationFailedException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\Support\Llm\FakeVideoGenerator;

/**
 * Checking on a started video: asked again later while it is made (or the
 * service cannot be reached for a moment), saved and ready once made, and
 * failed when refused, failed or out of time, which gives its video slot
 * back. The chat is told whenever the video settles.
 */
beforeEach(function (): void {
    Storage::fake(GeneratedMedia::DISK);
    Event::fake([GeneratedMediaUpdated::class]);
    Queue::fake();
    $this->conversation = Conversation::factory()->create(['source' => ConversationSource::HostedChat]);
});

function cgvMedia(array $state = []): GeneratedMedia
{
    return GeneratedMedia::factory()->forConversation(test()->conversation)->video()->create($state);
}

it('asks again later while the video is being made', function (): void {
    FakeVideoGenerator::install([null]);
    $media = cgvMedia();

    app(CheckGeneratedVideoAction::class)->execute($media);

    expect($media->refresh()->status)->toBe(GeneratedMediaStatus::Pending);

    Queue::assertPushedOn('generation', CheckGeneratedVideoJob::class, fn (CheckGeneratedVideoJob $job): bool => $job->media->is($media));
    Event::assertNotDispatched(GeneratedMediaUpdated::class);
});

it('asks again later when the service cannot be reached for a moment', function (): void {
    FakeVideoGenerator::install([new VideoGenerationFailedException('Down.', previous: new ConnectionException('timed out'))]);
    $media = cgvMedia();

    app(CheckGeneratedVideoAction::class)->execute($media);

    expect($media->refresh()->status)->toBe(GeneratedMediaStatus::Pending);
    Queue::assertPushed(CheckGeneratedVideoJob::class);
});

it('saves a made video, forgets the service\'s copy and tells the chat', function (): void {
    $videos = FakeVideoGenerator::install(['mp4-bytes']);
    $media = cgvMedia();

    app(CheckGeneratedVideoAction::class)->execute($media);

    $media->refresh();
    expect($media->status)->toBe(GeneratedMediaStatus::Ready)
        ->and($media->mime_type)->toBe('video/mp4')
        ->and($media->filepath)->toEndWith("/chat/{$this->conversation->external_id}/videos/{$media->external_id}.mp4")
        ->and(Storage::disk(GeneratedMedia::DISK)->get((string) $media->filepath))->toBe('mp4-bytes')
        ->and($media->size_bytes)->toBe(9)
        ->and($videos->forgotten)->toBe(['fake-task-1']);

    Queue::assertNotPushed(CheckGeneratedVideoJob::class);
    Event::assertDispatched(GeneratedMediaUpdated::class, fn (GeneratedMediaUpdated $event): bool => $event->media->id === $media->external_id
        && $event->media->status === GeneratedMediaStatus::Ready
        && $event->media->url !== null);
});

it('marks a refused or failed video and gives back its video slot', function (Throwable $error, GeneratedMediaFailure $failure): void {
    FakeVideoGenerator::install([$error]);
    $limit = GenerationLimit::factory()->create(['workspace_id' => $this->conversation->workspace_id, 'generations_used' => 4, 'videos_used' => 2, 'monthly_video_limit' => 5]);
    $media = cgvMedia();

    app(CheckGeneratedVideoAction::class)->execute($media);

    expect($media->refresh()->status)->toBe(GeneratedMediaStatus::Failed)
        ->and($media->failure)->toBe($failure)
        ->and($limit->refresh()->videos_used)->toBe(1)
        ->and($limit->generations_used)->toBe(4);

    Event::assertDispatched(GeneratedMediaUpdated::class);
})->with([
    'refused' => [VideoGenerationFailedException::refused('Fake'), GeneratedMediaFailure::Refused],
    'failed' => [new VideoGenerationFailedException('Broken.'), GeneratedMediaFailure::Failed],
]);

it('gives up on a video not made in time', function (): void {
    config(['chatvia.ai.video_max_wait_minutes' => 15]);
    FakeVideoGenerator::install([null]);
    $media = cgvMedia(['created_at' => now()->subMinutes(16)]);

    app(CheckGeneratedVideoAction::class)->execute($media);

    expect($media->refresh()->status)->toBe(GeneratedMediaStatus::Failed)
        ->and($media->failure)->toBe(GeneratedMediaFailure::TimedOut);

    Queue::assertNotPushed(CheckGeneratedVideoJob::class);
});

it('leaves a settled video, or one not started, alone', function (array $state): void {
    $videos = FakeVideoGenerator::install(['mp4-bytes']);
    $media = cgvMedia($state);

    app(CheckGeneratedVideoAction::class)->execute($media);

    expect($videos->forgotten)->toBe([]);
    Queue::assertNotPushed(CheckGeneratedVideoJob::class);
    Event::assertNotDispatched(GeneratedMediaUpdated::class);
})->with([
    'failed' => [['status' => GeneratedMediaStatus::Failed, 'failure' => GeneratedMediaFailure::TimedOut]],
    'not started' => [['task_id' => null]],
]);
