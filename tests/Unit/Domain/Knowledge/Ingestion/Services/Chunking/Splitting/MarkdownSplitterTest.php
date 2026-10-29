<?php

use Domain\Knowledge\Ingestion\Contracts\ChunkMeasure;
use Domain\Knowledge\Ingestion\Services\Chunking\Splitting\CharacterMeasure;
use Domain\Knowledge\Ingestion\Services\Chunking\Splitting\MarkdownSplitter;
use Domain\Knowledge\Ingestion\Services\Chunking\Splitting\RecursiveSplitter;
use Illuminate\Support\Str;

/**
 * The Markdown splitter follows the structure of the page: one section per
 * heading, long sections cut between blocks with the headings above every
 * chunk repeated at its start, and a comment inside a code block never taken
 * for a heading.
 */
function mdsSplit(string $markdown, int $size, int $overlap = 0, ?ChunkMeasure $measure = null, int $minimum = 0): array
{
    return new MarkdownSplitter(new RecursiveSplitter($measure ?? new CharacterMeasure, $size, $overlap, $minimum))->split($markdown);
}

it('repeats the headings above a chunk from the middle of a long section', function (): void {
    $markdown = <<<'MD'
        # Pricing

        ## Enterprise

        ### Limits

        Each workspace has ten agents. Each agent has many sources.

        Each source may hold a million words. Ask us for more.
        MD;

    $chunks = mdsSplit($markdown, 100);

    expect($chunks)->toBe([
        "# Pricing\n## Enterprise\n### Limits\n\nEach workspace has ten agents. Each agent has many sources.",
        "# Pricing\n## Enterprise\n### Limits\n\nEach source may hold a million words. Ask us for more.",
    ]);

    foreach ($chunks as $chunk) {
        expect(Str::length($chunk))->toBeLessThanOrEqual(100);
    }
});

it('does not take a comment inside a code block for a heading', function (): void {
    $markdown = <<<'MD'
        # Install

        ```bash
        # Download the files
        curl -O https://example.com/app.tar.gz
        ```

        Then start the app.
        MD;

    expect(mdsSplit($markdown, 200))->toBe([$markdown])
        ->and(mdsSplit($markdown, 90))->toBe([
            "# Install\n\n```bash\n# Download the files\ncurl -O https://example.com/app.tar.gz\n```",
            "# Install\n\nThen start the app.",
        ]);
});
