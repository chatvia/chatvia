<?php

declare(strict_types=1);

use App\Identity\Workspace\Models\GenerationLimit;
use Domain\Conversation\Actions\Media\GenerateMediaAction;
use Domain\Conversation\Enums\ConversationSource;
use Domain\Conversation\Enums\GeneratedMediaFailure;
use Domain\Conversation\Enums\GeneratedMediaStatus;
use Domain\Conversation\Events\GeneratedMediaUpdated;
use Domain\Conversation\Jobs\CheckGeneratedVideoJob;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\GeneratedMedia;
use Domain\Llm\Enums\ImageShape;
use Domain\Llm\Enums\VideoResolution;
use Domain\Llm\Enums\VideoShape;
use Domain\Llm\Exceptions\ImageGenerationFailedException;
use Domain\Llm\Exceptions\VideoGenerationFailedException;
use Domain\Llm\Services\AudioGeneration;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\Support\Llm\FakeImageGenerator;
use Tests\Support\Llm\FakeSpeaker;
use Tests\Support\Llm\FakeVideoGenerator;

/**
 * Making one pending file: saved under the agent's folder and ready, or
 * failed (refused, failed or timed out) with its monthly slot given back.
 * Either way the chat is told at once.
 */
beforeEach(function (): void {
    Storage::fake(GeneratedMedia::DISK);
    Event::fake([GeneratedMediaUpdated::class]);
    $this->conversation = Conversation::factory()->create(['source' => ConversationSource::HostedChat]);
});

function gmaMedia(array $state = []): GeneratedMedia
{
    return GeneratedMedia::factory()->forConversation(test()->conversation)->create($state);
}

it('draws a picture, saves it and tells the chat', function (): void {
    $images = FakeImageGenerator::install();
    $media = gmaMedia(['prompt' => 'A red fox', 'metadata' => ['shape' => 'portrait']]);

    app(GenerateMediaAction::class)->execute($media);

    $media->refresh();
    expect($media->status)->toBe(GeneratedMediaStatus::Ready)
        ->and($media->mime_type)->toBe('image/png')
        ->and($media->filepath)->toEndWith("/chat/{$this->conversation->external_id}/images/{$media->external_id}.png")
        ->and(Storage::disk(GeneratedMedia::DISK)->get((string) $media->filepath))->toBe('[portrait:A red fox]')
        ->and($media->size_bytes)->toBe(strlen('[portrait:A red fox]'))
        ->and($images->drawn[0]->shape)->toBe(ImageShape::Portrait);

    Event::assertDispatched(GeneratedMediaUpdated::class, fn (GeneratedMediaUpdated $event): bool => $event->conversationId === $this->conversation->external_id
        && $event->media->id === $media->external_id
        && $event->media->status === GeneratedMediaStatus::Ready
        && $event->media->url !== null);
});

it('reads an audio file in the stored voice', function (): void {
    app()->instance(AudioGeneration::class, new AudioGeneration($speaker = new FakeSpeaker));
    $media = GeneratedMedia::factory()->forConversation($this->conversation)->audio()->create(['metadata' => ['voice' => 'tenor'], 'prompt' => 'Hello there.']);

    app(GenerateMediaAction::class)->execute($media);

    expect($media->refresh()->status)->toBe(GeneratedMediaStatus::Ready)
        ->and($media->mime_type)->toBe('audio/mpeg')
        ->and($media->filepath)->toEndWith('.mp3')
        ->and($speaker->spoken)->toBe([['text' => 'Hello there.', 'voice' => 'tenor']]);
});

it('marks a failed file with its reason and gives back its slot', function (Throwable $error, GeneratedMediaFailure $failure): void {
    FakeImageGenerator::install($error);
    $limit = GenerationLimit::factory()->create(['workspace_id' => $this->conversation->workspace_id, 'generations_used' => 3]);
    $media = gmaMedia();

    app(GenerateMediaAction::class)->execute($media);

    expect($media->refresh()->status)->toBe(GeneratedMediaStatus::Failed)
        ->and($media->failure)->toBe($failure)
        ->and($media->filepath)->toBeNull()
        ->and($limit->refresh()->generations_used)->toBe(2);

    Event::assertDispatched(GeneratedMediaUpdated::class, fn (GeneratedMediaUpdated $event): bool => $event->media->failure === $failure);
})->with([
    'refused' => [ImageGenerationFailedException::refused('OpenAI'), GeneratedMediaFailure::Refused],
    'failed' => [new ImageGenerationFailedException('OpenAI sent no image.'), GeneratedMediaFailure::Failed],
    'timed out' => [new ImageGenerationFailedException('OpenAI could not be reached', previous: new ConnectionException('cURL error 28: timed out')), GeneratedMediaFailure::TimedOut],
]);

it('leaves an item that is no longer pending alone', function (): void {
    $images = FakeImageGenerator::install();
    $media = gmaMedia(['status' => GeneratedMediaStatus::Failed, 'failure' => GeneratedMediaFailure::Failed]);

    app(GenerateMediaAction::class)->execute($media);

    expect($images->drawn)->toBe([])
        ->and($media->refresh()->status)->toBe(GeneratedMediaStatus::Failed);
    Event::assertNotDispatched(GeneratedMediaUpdated::class);
});

it('starts a video with its saved length and resolution and checks on it later, without waiting for it', function (): void {
    Queue::fake();
    // The admin changed the settings after the video was asked for.
    config(['chatvia.ai.video_duration' => 4, 'chatvia.ai.video_resolution' => '720p']);
    $videos = FakeVideoGenerator::install();
    $media = GeneratedMedia::factory()->forConversation($this->conversation)->video()->create([
        'task_id' => null,
        'metadata' => ['shape' => 'portrait', 'seconds' => 6, 'resolution' => '1080p'],
        'prompt' => 'Rain on a window',
    ]);

    app(GenerateMediaAction::class)->execute($media);
    app(GenerateMediaAction::class)->execute($media->refresh());

    expect($media->status)->toBe(GeneratedMediaStatus::Pending)
        ->and($media->task_id)->toBe('fake-task-1')
        ->and($videos->started)->toHaveCount(1)
        ->and($videos->started[0]->prompt)->toBe('Rain on a window')
        ->and($videos->started[0]->shape)->toBe(VideoShape::Portrait)
        ->and($videos->started[0]->seconds)->toBe(6)
        ->and($videos->started[0]->resolution)->toBe(VideoResolution::Hd1080);

    Queue::assertPushed(CheckGeneratedVideoJob::class, 1);
    Event::assertNotDispatched(GeneratedMediaUpdated::class);
});

it('marks a video the service refuses to start', function (): void {
    FakeVideoGenerator::install(startFailure: VideoGenerationFailedException::refused('Fake'));
    $media = GeneratedMedia::factory()->forConversation($this->conversation)->video()->create(['task_id' => null]);

    app(GenerateMediaAction::class)->execute($media);

    expect($media->refresh()->status)->toBe(GeneratedMediaStatus::Failed)
        ->and($media->failure)->toBe(GeneratedMediaFailure::Refused);
});
