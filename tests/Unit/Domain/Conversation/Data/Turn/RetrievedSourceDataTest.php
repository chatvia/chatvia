<?php

use Domain\Conversation\Data\Turn\RetrievedSourceData;
use Domain\Conversation\Data\Turn\SourceRefData;
use Domain\Knowledge\Retrieval\Enums\SourceKind;

/**
 * The clients list one entry per place an answer drew on, not one per chunk:
 * the chunks of one text all carry the text's opening words as their label,
 * so five chunks of it read as five identical sources.
 */
function rsdSource(int $id, string $type, int $sourceId, string $label, SourceKind $kind, ?string $url = null, ?int $page = null): RetrievedSourceData
{
    return new RetrievedSourceData(
        id: $id,
        external_id: "chunk-{$id}",
        content: "Passage {$id}",
        relevance: null,
        label: $label,
        kind: $kind,
        url: $url,
        page: $page,
        sourceable_type: $type,
        sourceable_id: $sourceId,
    );
}

it('lists the chunks of one source once, with how many passages were used and which blocks they were', function (): void {
    $sources = collect([
        rsdSource(1, 'source_text', 4, 'أعلن المتحدث العسكري', SourceKind::Text),
        rsdSource(2, 'source_text', 4, 'أعلن المتحدث العسكري', SourceKind::Text),
        rsdSource(3, 'source_file', 9, 'guide.docx', SourceKind::File),
        rsdSource(4, 'source_text', 4, 'أعلن المتحدث العسكري', SourceKind::Text),
    ]);

    expect(collect(RetrievedSourceData::forClients($sources))->toArray())->toBe([
        ['id' => 'chunk-1', 'label' => 'أعلن المتحدث العسكري', 'kind' => 'text', 'url' => null, 'page' => null, 'passages' => 3, 'blocks' => [1, 2, 4], 'excerpt' => 'Passage 1'],
        ['id' => 'chunk-3', 'label' => 'guide.docx', 'kind' => 'file', 'url' => null, 'page' => null, 'passages' => 1, 'blocks' => [3], 'excerpt' => 'Passage 3'],
    ]);
});

it('keeps each PDF page, each website page and two sources with the same name apart', function (): void {
    $sources = collect([
        rsdSource(1, 'source_file', 9, 'manual.pdf', SourceKind::File, page: 3),
        rsdSource(2, 'source_file', 9, 'manual.pdf', SourceKind::File, page: 5),
        rsdSource(3, 'source_file', 9, 'manual.pdf', SourceKind::File, page: 3),
        rsdSource(4, 'source_website', 2, 'Returns', SourceKind::Website, 'https://shop.test/returns'),
        rsdSource(5, 'source_website', 2, 'Shipping', SourceKind::Website, 'https://shop.test/shipping'),
        rsdSource(6, 'source_text', 4, 'Opening hours', SourceKind::Text),
        rsdSource(7, 'source_text', 5, 'Opening hours', SourceKind::Text),
    ]);

    expect(array_map(static fn (SourceRefData $entry): array => [$entry->id, $entry->passages], RetrievedSourceData::forClients($sources)))->toBe([
        ['chunk-1', 2],
        ['chunk-2', 1],
        ['chunk-4', 1],
        ['chunk-5', 1],
        ['chunk-6', 1],
        ['chunk-7', 1],
    ]);
});
