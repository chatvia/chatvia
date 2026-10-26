<?php

declare(strict_types=1);

use App\Identity\Workspace\Models\GenerationLimit;
use Domain\Conversation\Actions\Media\FailGeneratedMediaAction;
use Domain\Conversation\Enums\GeneratedMediaFailure;
use Domain\Conversation\Enums\GeneratedMediaStatus;
use Domain\Conversation\Events\GeneratedMediaUpdated;
use Domain\Conversation\Models\GeneratedMedia;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;

/**
 * A pending item that could not be made turns failed with its reason, its
 * monthly slot comes back and the chat is told; a settled item is left as
 * it is.
 */
beforeEach(function (): void {
    Storage::fake(GeneratedMedia::DISK);
    Event::fake([GeneratedMediaUpdated::class]);
});

it('fails a pending item, gives back its slot and tells the chat', function (): void {
    $media = GeneratedMedia::factory()->create();
    $limit = GenerationLimit::factory()->create(['workspace_id' => $media->workspace_id, 'generations_used' => 5]);

    app(FailGeneratedMediaAction::class)->execute($media, GeneratedMediaFailure::TimedOut);

    expect($media->refresh()->status)->toBe(GeneratedMediaStatus::Failed)
        ->and($media->failure)->toBe(GeneratedMediaFailure::TimedOut)
        ->and($limit->refresh()->generations_used)->toBe(4);

    Event::assertDispatched(GeneratedMediaUpdated::class, fn (GeneratedMediaUpdated $event): bool => $event->media->id === $media->external_id
        && $event->media->status === GeneratedMediaStatus::Failed);
});

it('never undoes a made file', function (): void {
    $media = GeneratedMedia::factory()->ready()->create();
    $limit = GenerationLimit::factory()->create(['workspace_id' => $media->workspace_id, 'generations_used' => 5]);

    app(FailGeneratedMediaAction::class)->execute($media, GeneratedMediaFailure::TimedOut);

    expect($media->refresh()->status)->toBe(GeneratedMediaStatus::Ready)
        ->and($limit->refresh()->generations_used)->toBe(5);
    Event::assertNotDispatched(GeneratedMediaUpdated::class);
});
