<?php

use Domain\Knowledge\Ingestion\Data\Chunk;
use Domain\Knowledge\Ingestion\Services\Splitters\AzureMarkdownSplitter;

/**
 * AzureMarkdownSplitter contract:
 *  - Tracks page numbers from `<!-- PageNumber="N" -->` markers
 *  - Drops content before the first page marker is seen
 *  - Splits on H1–H3; H4+ are inlined into the current chunk
 *  - Skips page-related comments (PageBreak / PageFooter / PageHeader)
 *  - Filters chunks with content <= 200 chars (MIN_CHUNK_SIZE); small ones
 *    get merged with neighbours first, then filtered if still too small
 *  - Reindexes chunk_id sequentially in the final output
 *
 * MIN_CHUNK_SIZE = 200, MAJOR_HEADING_MAX_LEVEL = 3.
 */
function longParagraph(string $marker = ''): string
{
    // Reliably above MIN_CHUNK_SIZE (200) so the chunk survives post-process.
    return $marker.' '.str_repeat('Lorem ipsum dolor sit amet, consectetur adipiscing elit. ', 6);
}

it('splits on H1/H2/H3 and tags each chunk with its current page', function (): void {
    $intro = longParagraph('introA');
    $methods = longParagraph('methodsA');

    $markdown = <<<MD
<!-- PageNumber="1" -->
# Introduction
{$intro}

<!-- PageNumber="2" -->
## Methods
{$methods}
MD;

    $chunks = (new AzureMarkdownSplitter)->split($markdown);

    expect($chunks)->toHaveCount(2);

    expect($chunks[0])->toBeInstanceOf(Chunk::class)
        ->and($chunks[0]->chunk_id)->toBe(1)
        ->and($chunks[0]->level)->toBe(1)
        ->and($chunks[0]->heading)->toBe('Introduction')
        ->and($chunks[0]->original_content)->toContain('# Introduction')
        ->and($chunks[0]->original_content)->toContain('introA')
        ->and($chunks[0]->metadata['pages'])->toBe(['1']);

    expect($chunks[1])->toBeInstanceOf(Chunk::class)
        ->and($chunks[1]->chunk_id)->toBe(2)
        ->and($chunks[1]->level)->toBe(2)
        ->and($chunks[1]->heading)->toBe('Methods')
        ->and($chunks[1]->original_content)->toContain('## Methods')
        ->and($chunks[1]->original_content)->toContain('methodsA')
        ->and($chunks[1]->metadata['pages'])->toBe(['2']);
});

it('drops all content that appears before the first PageNumber marker', function (): void {
    $kept = longParagraph('kept-after-marker');

    $markdown = <<<MD
# Untracked
Untracked content that should never appear in any chunk because it precedes the first page marker.

<!-- PageNumber="3" -->
# Tracked
{$kept}
MD;

    $chunks = (new AzureMarkdownSplitter)->split($markdown);

    expect($chunks)->toHaveCount(1)
        ->and($chunks[0]->heading)->toBe('Tracked')
        ->and($chunks[0]->original_content)->toContain('# Tracked')
        ->and($chunks[0]->original_content)->not->toContain('# Untracked')
        ->and($chunks[0]->original_content)->not->toContain('Untracked content')
        ->and($chunks[0]->metadata['pages'])->toBe(['3']);
});

it('inlines H4+ headers into the current major-header chunk instead of opening a new one', function (): void {
    $topBody = longParagraph('topBody');
    $subBody = longParagraph('subBody');

    $markdown = <<<MD
<!-- PageNumber="1" -->
# Top Heading
{$topBody}

#### Sub-detail Heading
{$subBody}
MD;

    $chunks = (new AzureMarkdownSplitter)->split($markdown);

    expect($chunks)->toHaveCount(1)
        ->and($chunks[0]->heading)->toBe('Top Heading')
        ->and($chunks[0]->original_content)->toContain('# Top Heading')
        ->and($chunks[0]->original_content)->toContain('#### Sub-detail Heading')
        ->and($chunks[0]->original_content)->toContain('topBody')
        ->and($chunks[0]->original_content)->toContain('subBody');
});

it('skips PageBreak / PageFooter / PageHeader comments without affecting chunking', function (): void {
    $body = longParagraph('bodyA');

    $markdown = <<<MD
<!-- PageNumber="1" -->
# Heading
{$body}
<!-- PageBreak -->
<!-- PageFooter="footer text" -->
<!-- PageHeader="header text" -->
MD;

    $chunks = (new AzureMarkdownSplitter)->split($markdown);

    expect($chunks)->toHaveCount(1)
        ->and($chunks[0]->original_content)->not->toContain('PageBreak')
        ->and($chunks[0]->original_content)->not->toContain('PageFooter')
        ->and($chunks[0]->original_content)->not->toContain('PageHeader')
        ->and($chunks[0]->original_content)->not->toContain('footer text');
});

it('drops chunks that never reach MIN_CHUNK_SIZE and reindexes the survivors', function (): void {
    // Two fat sections survive; a tiny one in the middle (well below 200 chars
    // even with its H2 header line) is dropped at the hasValidContent gate,
    // and the final chunk_ids are 1, 2 — not 1, 3.
    $sectionA = longParagraph('sectionA');
    $sectionB = longParagraph('sectionB');

    $markdown = <<<MD
<!-- PageNumber="1" -->
# Section A
{$sectionA}

## Tiny
short.

# Section B
{$sectionB}
MD;

    $chunks = (new AzureMarkdownSplitter)->split($markdown);

    expect($chunks)->toHaveCount(2)
        ->and($chunks[0]->chunk_id)->toBe(1)
        ->and($chunks[0]->heading)->toBe('Section A')
        ->and($chunks[1]->chunk_id)->toBe(2)
        ->and($chunks[1]->heading)->toBe('Section B');

    foreach ($chunks as $chunk) {
        expect($chunk->original_content)->not->toContain('## Tiny');
    }
});
