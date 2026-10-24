<?php

use Domain\Knowledge\Ingestion\Models\Chunk;
use Domain\Knowledge\Source\Enums\SourceStatus;
use Domain\Knowledge\Source\Text\Models\SourceText;

/**
 * A retrain clears every chunk of the text, the summary chunk (index 0)
 * included, so the summary is written again, and puts the text back to
 * pending.
 */
it('deletes the summary chunk when a text is retrained', function (): void {
    $text = SourceText::factory()->ingested()->create();
    Chunk::factory()->forSource($text)->summary()->create();
    Chunk::factory()->forSource($text)->create();

    $text->retrain();

    expect(Chunk::query()->ofSource($text)->exists())->toBeFalse()
        ->and($text->fresh()->status)->toBe(SourceStatus::Pending);
});
