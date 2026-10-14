<?php

use Domain\Conversation\Data\Turn\RetrievedSourceData;
use Domain\Knowledge\Retrieval\Enums\SourceKind;

/**
 * The clients list one entry per place an answer drew on, not one per chunk:
 * the chunks of one text all carry the text's opening words as their label,
 * so five chunks of it read as five identical sources.
 */
function retrievedSource(int $id, string $type, int $sourceId, string $label, SourceKind $kind, ?string $url = null, ?int $page = null): RetrievedSourceData
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

it('lists the chunks of one source once, with how many passages were used', function (): void {
    $sources = collect([
        retrievedSource(1, 'source_text', 4, 'أعلن المتحدث العسكري', SourceKind::Text),
        retrievedSource(2, 'source_text', 4, 'أعلن المتحدث العسكري', SourceKind::Text),
        retrievedSource(3, 'source_file', 9, 'guide.docx', SourceKind::File),
        retrievedSource(4, 'source_text', 4, 'أعلن المتحدث العسكري', SourceKind::Text),
    ]);

    expect(RetrievedSourceData::forClients($sources))->toBe([
        ['id' => 'chunk-1', 'label' => 'أعلن المتحدث العسكري', 'kind' => 'text', 'url' => null, 'page' => null, 'passages' => 3],
        ['id' => 'chunk-3', 'label' => 'guide.docx', 'kind' => 'file', 'url' => null, 'page' => null, 'passages' => 1],
    ]);
});

it('keeps each PDF page and each website page apart', function (): void {
    $sources = collect([
        retrievedSource(1, 'source_file', 9, 'manual.pdf', SourceKind::File, page: 3),
        retrievedSource(2, 'source_file', 9, 'manual.pdf', SourceKind::File, page: 5),
        retrievedSource(3, 'source_file', 9, 'manual.pdf', SourceKind::File, page: 3),
        retrievedSource(4, 'source_website', 2, 'Returns', SourceKind::Website, 'https://shop.test/returns'),
        retrievedSource(5, 'source_website', 2, 'Shipping', SourceKind::Website, 'https://shop.test/shipping'),
    ]);

    expect(array_map(static fn (array $entry): array => [$entry['id'], $entry['passages']], RetrievedSourceData::forClients($sources)))->toBe([
        ['chunk-1', 2],
        ['chunk-2', 1],
        ['chunk-4', 1],
        ['chunk-5', 1],
    ]);
});

it('tells two sources with the same name apart', function (): void {
    $sources = [
        retrievedSource(1, 'source_text', 4, 'Opening hours', SourceKind::Text),
        retrievedSource(2, 'source_text', 5, 'Opening hours', SourceKind::Text),
    ];

    expect(RetrievedSourceData::forClients($sources))->toHaveCount(2);
});

it('has nothing to list without sources', function (): void {
    expect(RetrievedSourceData::forClients([]))->toBe([]);
});
