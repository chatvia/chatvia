<?php

declare(strict_types=1);

use Domain\Conversation\Enums\GeneratedMediaFailure;
use Domain\Conversation\Enums\GeneratedMediaStatus;
use Domain\Conversation\Events\GeneratedMediaUpdated;
use Domain\Conversation\Jobs\GenerateMediaJob;
use Domain\Conversation\Models\GeneratedMedia;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Tests\Support\Llm\FakeImageGenerator;

/**
 * The job makes the file on the `generation` queue; a job that dies (out of
 * time) leaves the item timed out instead of pending for good.
 */
beforeEach(function (): void {
    Storage::fake(GeneratedMedia::DISK);
    Event::fake([GeneratedMediaUpdated::class]);
});

it('makes the file', function (): void {
    FakeImageGenerator::install();
    $media = GeneratedMedia::factory()->create();

    dispatch_sync(new GenerateMediaJob($media));

    expect($media->refresh()->status)->toBe(GeneratedMediaStatus::Ready);
});

it('marks the item timed out when the job dies', function (): void {
    $media = GeneratedMedia::factory()->create();

    (new GenerateMediaJob($media))->failed(new RuntimeException('Timed out'));

    expect($media->refresh()->status)->toBe(GeneratedMediaStatus::Failed)
        ->and($media->failure)->toBe(GeneratedMediaFailure::TimedOut);
});
