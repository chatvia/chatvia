<?php

use Database\Factories\AgentFileFactory;
use Domain\Agent\Models\Agent;
use Domain\Knowledge\Embedding\Contracts\EmbeddingsProviderInterface;
use Domain\Knowledge\Ingestion\Models\Chunk;
use Domain\Knowledge\Ingestion\Models\ChunkReference;
use Domain\Knowledge\Retrieval\Data\RetrievedChunkData;
use Domain\Knowledge\Retrieval\Data\SourceLabelData;
use Domain\Knowledge\Retrieval\Enums\SourceKind;
use Domain\Knowledge\Retrieval\Services\Bm25Tokenizer;
use Domain\Knowledge\Retrieval\Services\SourceLabeler;
use Domain\Knowledge\Source\Enums\SourceStatus;
use Domain\Knowledge\Source\Text\Models\SourceText;
use Domain\Knowledge\Source\Web\Models\SourceWebsite;
use Domain\Knowledge\Source\Youtube\Models\SourceYoutubeVideo;
use Illuminate\Database\Eloquent\Model;

/**
 * Each retrieved chunk is named after its source: a file with its PDF page,
 * the website page it came from (title and link), the start of a typed text,
 * a video's title and link. Stored files are never linked.
 */
function labelerChunk(Model $source, int $chunkIndex = 1, string $text = 'Some text.'): RetrievedChunkData
{
    $chunk = Chunk::query()->create([
        'workspace_id' => $source->getAttribute('workspace_id'),
        'chunkable_type' => $source->getMorphClass(),
        'chunkable_id' => $source->getKey(),
        'chunk_index' => $chunkIndex,
        'chunk' => $text,
        'tokens' => 3,
        'embedding' => app(EmbeddingsProviderInterface::class)->embed($text, true),
        'embedding_bm25' => Bm25Tokenizer::expression($text),
        'checksum' => hash('sha256', $text.$chunkIndex.$source->getKey()),
        'cost_in_micro_cents' => 0,
    ]);

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

    $chunks = [labelerChunk($pdf, 3), labelerChunk($notes, 2), labelerChunk($talk, 1)];
    $labels = app(SourceLabeler::class)->forChunks($chunks);

    expect($labels[$chunks[0]->id])->toEqual(new SourceLabelData('manual.pdf', SourceKind::File, null, 3))
        ->and($labels[$chunks[1]->id])->toEqual(new SourceLabelData('notes.md', SourceKind::File))
        ->and($labels[$chunks[2]->id])->toEqual(new SourceLabelData('talk.mp3', SourceKind::Media));
});

it('names a website chunk after the page it came from, else the site', function (): void {
    $agent = Agent::factory()->create();
    $website = SourceWebsite::query()->create(['workspace_id' => $agent->workspace_id, 'url' => 'https://shop.test', 'status' => 'ingested']);
    $titled = $website->urls()->create(['url' => 'https://shop.test/returns', 'title' => 'Returns and refunds']);
    $untitled = $website->urls()->create(['url' => 'https://shop.test/shipping']);

    $returns = labelerChunk($website, 1, 'Returns are free.');
    $shipping = labelerChunk($website, 1, 'We ship in two days.');
    $orphan = labelerChunk($website, 2, 'No page links this chunk.');

    ChunkReference::query()->insert([
        ['chunk_id' => $returns->id, 'referenceable_type' => $titled->getMorphClass(), 'referenceable_id' => $titled->id],
        ['chunk_id' => $shipping->id, 'referenceable_type' => $untitled->getMorphClass(), 'referenceable_id' => $untitled->id],
    ]);

    $labels = app(SourceLabeler::class)->forChunks([$returns, $shipping, $orphan]);

    expect($labels[$returns->id])->toEqual(new SourceLabelData('Returns and refunds', SourceKind::Website, 'https://shop.test/returns'))
        ->and($labels[$shipping->id])->toEqual(new SourceLabelData('https://shop.test/shipping', SourceKind::Website, 'https://shop.test/shipping'))
        ->and($labels[$orphan->id])->toEqual(new SourceLabelData('https://shop.test', SourceKind::Website, 'https://shop.test'));
});

it('names a typed text by its start and a video by its title', function (): void {
    $agent = Agent::factory()->create();
    $text = SourceText::query()->create([
        'workspace_id' => $agent->workspace_id,
        'filepath' => 'workspaces/x/texts/a.txt',
        'excerpt' => "Our shop opens at nine.\nIt closes at six and stays closed on Sundays and on every public holiday of the year.",
        'length' => 1,
        'checksum' => md5(uniqid()),
        'status' => SourceStatus::Completed,
    ]);
    $video = SourceYoutubeVideo::query()->create(['workspace_id' => $agent->workspace_id, 'url' => 'https://www.youtube.com/watch?v=abc', 'title' => 'Pitching a tent', 'length' => 1, 'status' => 'pending']);

    $textChunk = labelerChunk($text);
    $videoChunk = labelerChunk($video);
    $labels = app(SourceLabeler::class)->forChunks([$textChunk, $videoChunk]);

    expect($labels[$textChunk->id])->toEqual(new SourceLabelData('Our shop opens at nine. It closes at six and stays closed on Sundays and on...', SourceKind::Text))
        ->and($labels[$videoChunk->id])->toEqual(new SourceLabelData('Pitching a tent', SourceKind::Video, 'https://www.youtube.com/watch?v=abc'));
});

it('names a chunk whose source is gone as unknown', function (): void {
    $chunk = new RetrievedChunkData(id: 99, external_id: null, chunk: 'x', chunk_index: 1, chunkable_type: 'source_file', chunkable_id: 999_999, metadata: null, search_type: 'vector', score: 0.01);

    expect(app(SourceLabeler::class)->forChunks([$chunk])[99])->toEqual(new SourceLabelData('Unknown source', SourceKind::Text));
});
