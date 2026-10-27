<?php

use Database\Factories\AgentFileFactory;
use Domain\Agent\Models\Agent;
use Domain\Knowledge\Ingestion\Models\Chunk;
use Domain\Knowledge\Retrieval\Data\RetrievedChunkData;
use Domain\Knowledge\Retrieval\Data\SourceLabelData;
use Domain\Knowledge\Retrieval\Enums\SourceKind;
use Domain\Knowledge\Retrieval\Services\SourceLabeler;
use Domain\Knowledge\Source\Text\Models\SourceText;
use Domain\Knowledge\Source\Web\Models\SourceWebsite;
use Illuminate\Database\Eloquent\Model;

/**
 * Each retrieved chunk is named after its source: a file with its PDF page,
 * the website page it came from (title and link), the start of a typed text,
 * a video's title and link. Stored files are never linked.
 */
/** A stored chunk of $source, as the search returns it; a website chunk names the pages that produced it. */
function slbChunk(Model $source, int $chunkIndex = 1, string $text = 'Some text.', Model ...$pages): RetrievedChunkData
{
    $chunk = Chunk::factory()->forSource($source)->referencedBy(...$pages)->create(['chunk_index' => $chunkIndex, 'chunk' => $text]);

    return new RetrievedChunkData(
        id: $chunk->id,
        external_id: $chunk->external_id,
        chunk: $text,
        chunk_index: $chunkIndex,
        chunkable_type: $source->getMorphClass(),
        chunkable_id: (int) $source->getKey(),
        metadata: null,
        search_type: 'vector',
        score: 0.03,
    );
}

it('names a file, with the page of a PDF, and never links it', function (): void {
    $pdf = AgentFileFactory::new()->create(['filename' => 'manual.pdf', 'mime_type' => 'application/pdf']);
    $notes = AgentFileFactory::new()->create(['filename' => 'notes.md', 'mime_type' => 'text/markdown']);
    $talk = AgentFileFactory::new()->create(['filename' => 'talk.mp3', 'mime_type' => 'audio/mpeg']);

    $chunks = [slbChunk($pdf, 3), slbChunk($notes, 2), slbChunk($talk, 1)];
    $labels = app(SourceLabeler::class)->forChunks($chunks);

    expect($labels[$chunks[0]->id])->toEqual(new SourceLabelData('manual.pdf', SourceKind::File, null, 3))
        ->and($labels[$chunks[1]->id])->toEqual(new SourceLabelData('notes.md', SourceKind::File))
        ->and($labels[$chunks[2]->id])->toEqual(new SourceLabelData('talk.mp3', SourceKind::Media));
});

it('names a website chunk after the page it came from, else the site', function (): void {
    $agent = Agent::factory()->create();
    $website = SourceWebsite::factory()->ingested()->create(['workspace_id' => $agent->workspace_id, 'url' => 'https://shop.test']);
    $titled = $website->urls()->create(['url' => 'https://shop.test/returns', 'title' => 'Returns and refunds']);
    $untitled = $website->urls()->create(['url' => 'https://shop.test/shipping']);

    $returns = slbChunk($website, 1, 'Returns are free.', $titled);
    $shipping = slbChunk($website, 1, 'We ship in two days.', $untitled);
    $orphan = slbChunk($website, 2, 'No page links this chunk.');

    $labels = app(SourceLabeler::class)->forChunks([$returns, $shipping, $orphan]);

    expect($labels[$returns->id])->toEqual(new SourceLabelData('Returns and refunds', SourceKind::Website, 'https://shop.test/returns'))
        ->and($labels[$shipping->id])->toEqual(new SourceLabelData('https://shop.test/shipping', SourceKind::Website, 'https://shop.test/shipping'))
        ->and($labels[$orphan->id])->toEqual(new SourceLabelData('https://shop.test', SourceKind::Website, 'https://shop.test'));
});

it('names a typed text by its start', function (): void {
    $agent = Agent::factory()->create();
    $text = SourceText::factory()->ingested()->create([
        'workspace_id' => $agent->workspace_id,
        'excerpt' => "Our shop opens at nine.\nIt closes at six and stays closed on Sundays and on every public holiday of the year.",
    ]);

    $textChunk = slbChunk($text);
    $labels = app(SourceLabeler::class)->forChunks([$textChunk]);

    expect($labels[$textChunk->id])->toEqual(new SourceLabelData('Our shop opens at nine. It closes at six and stays closed on Sundays and on...', SourceKind::Text));
});

it('names a chunk whose source is gone as unknown', function (): void {
    $chunk = new RetrievedChunkData(id: 99, external_id: null, chunk: 'x', chunk_index: 1, chunkable_type: 'source_file', chunkable_id: 999_999, metadata: null, search_type: 'vector', score: 0.01);

    expect(app(SourceLabeler::class)->forChunks([$chunk])[99])->toEqual(new SourceLabelData('Unknown source', SourceKind::Text));
});
