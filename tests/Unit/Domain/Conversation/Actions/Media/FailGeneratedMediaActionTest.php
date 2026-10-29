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
 * Failing a generated item never undoes a file that was already made: its
 * status and its monthly slot stay as they are.
 */
it('leaves a made file ready and its slot counted', function (): void {
    Storage::fake(GeneratedMedia::DISK);
    Event::fake([GeneratedMediaUpdated::class]);
    $media = GeneratedMedia::factory()->ready()->create();
    $limit = GenerationLimit::factory()->create(['workspace_id' => $media->workspace_id, 'generations_used' => 5]);

    app(FailGeneratedMediaAction::class)->execute($media, GeneratedMediaFailure::TimedOut);

    expect($media->refresh()->status)->toBe(GeneratedMediaStatus::Ready)
        ->and($limit->refresh()->generations_used)->toBe(5);
    Event::assertNotDispatched(GeneratedMediaUpdated::class);
});
