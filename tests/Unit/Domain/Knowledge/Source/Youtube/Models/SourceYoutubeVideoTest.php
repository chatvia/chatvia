<?php

use Domain\Knowledge\Ingestion\Models\Chunk;
use Domain\Knowledge\Source\Enums\SourceStatus;
use Domain\Knowledge\Source\Youtube\Models\SourceYoutubeVideo;

/**
 * A retrain clears every chunk of the video, the summary chunk (index 0)
 * included, and puts it back to pending without its old failure reason.
 */
it('retrains by deleting every chunk, the summary included, and waiting again', function (): void {
    $video = SourceYoutubeVideo::factory()->ingested()->create(['failure_reason' => 'stale error']);
    Chunk::factory()->forSource($video)->summary()->create();
    Chunk::factory()->forSource($video)->create();
    $otherVideo = Chunk::factory()->forSource(SourceYoutubeVideo::factory()->ingested()->create(['workspace_id' => $video->workspace_id]))->create();

    $video->retrain();

    $video->refresh();
    expect(Chunk::query()->ofSource($video)->exists())->toBeFalse()
        ->and(Chunk::query()->find($otherVideo->id))->not->toBeNull()
        ->and($video->status)->toBe(SourceStatus::Pending)
        ->and($video->failure_reason)->toBeNull();
});
