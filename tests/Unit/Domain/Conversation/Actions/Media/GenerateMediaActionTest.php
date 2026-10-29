<?php

declare(strict_types=1);

use App\Identity\Workspace\Models\GenerationLimit;
use Domain\Conversation\Actions\Media\GenerateMediaAction;
use Domain\Conversation\Enums\ConversationSource;
use Domain\Conversation\Enums\GeneratedMediaFailure;
use Domain\Conversation\Enums\GeneratedMediaStatus;
use Domain\Conversation\Events\GeneratedMediaUpdated;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\GeneratedMedia;
use Domain\Llm\Enums\ImageShape;
use Domain\Llm\Exceptions\ImageGenerationFailedException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Tests\Support\Llm\FakeImageGenerator;

/**
 * Making one pending picture: saved under the conversation's folder and
 * ready, or failed (refused, failed or timed out) with its monthly slot
 * given back. Either way the chat is told at once.
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
