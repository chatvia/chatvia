<?php

declare(strict_types=1);

use Domain\Knowledge\Ingestion\Models\Chunk;
use Domain\Knowledge\Ingestion\Services\ChunkEmbeddingText;

/**
 * Ingestion and a re-index embed a chunk from the same text: a page with its
 * summary first and its image and table descriptions last, the summary chunk
 * of a source without its HTML.
 */
it('builds an enhanced page from its summary, its text and its visuals', function (): void {
    $chunk = new Chunk([
        'chunk_index' => 3,
        'chunk' => 'Page text.',
        'summary' => 'About prices.',
        'metadata' => [
            'images' => ['workspaces/a/images/1.png' => 'A price chart.'],
            'tables' => ['markdown' => ['| a |'], 'descriptions' => 'A price table.'],
        ],
    ]);

    expect(ChunkEmbeddingText::of($chunk))->toBe("About prices.\n\nPage text.\n\nA price chart.\n\nA price table.")
        ->and(ChunkEmbeddingText::of($chunk))->toBe(ChunkEmbeddingText::forPage('About prices.', 'Page text.', "A price chart.\n\nA price table."));
});

it('embeds a page that was not enhanced as its text', function (): void {
    $chunk = new Chunk(['chunk_index' => 1, 'chunk' => 'Plain page.', 'summary' => null, 'metadata' => []]);

    expect(ChunkEmbeddingText::of($chunk))->toBe('Plain page.');
});

it('embeds the summary chunk as plain text', function (): void {
    $chunk = new Chunk(['chunk_index' => 0, 'chunk' => '<p>Short <b>summary</b>.</p>', 'summary' => '', 'metadata' => ['language' => 'en']]);

    expect(ChunkEmbeddingText::of($chunk))->toBe('Short summary.');
});
