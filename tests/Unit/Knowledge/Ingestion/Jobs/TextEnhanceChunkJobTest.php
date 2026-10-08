<?php

use Database\Factories\AgentFileFactory;
use Domain\Agent\Models\Agent;
use Domain\Knowledge\Ingestion\Data\OCR\PageData;
use Domain\Knowledge\Ingestion\Jobs\Text\TextEnhanceChunkJob;
use Domain\Knowledge\Ingestion\Models\Chunk;
use Domain\Knowledge\Source\Text\Models\SourceText;

/**
 * TextEnhanceChunkJob is unique per source page. SourceText and SourceFile ids
 * overlap, so the unique key must include the source type or one source's
 * enhancement job silently drops the other's.
 */
it('gives a SourceText and a SourceFile with the same id different unique keys', function (): void {
    $agent = Agent::factory()->create();
    $page = new PageData(pageNumber: 1, content: 'x', checksum: 'x');

    $file = AgentFileFactory::new()->make();
    $file->id = 42;

    $text = new SourceText;
    $text->id = 42;

    $fileJob = new TextEnhanceChunkJob($agent, $file, new Chunk, $page);
    $textJob = new TextEnhanceChunkJob($agent, $text, new Chunk, $page);

    expect($fileJob->uniqueId())->not->toBe($textJob->uniqueId())
        ->and($fileJob->uniqueId())->toContain('source_file:42')
        ->and($textJob->uniqueId())->toContain('source_text:42');
});

it('keeps the sourceText property so jobs queued before the change still unserialize', function (): void {
    expect(property_exists(TextEnhanceChunkJob::class, 'sourceText'))->toBeTrue();
});
