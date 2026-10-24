<?php

use Domain\Knowledge\Ingestion\Enums\ContentKind;
use Domain\Knowledge\Ingestion\Enums\SplitStrategy;

/**
 * Each kind of content has its own strategy: the default suits its
 * structure, and the admin may pick another that suits it.
 */
beforeEach(function (): void {
    config([
        'chatvia.ai.chunking' => [
            'unit' => 'tokens',
            'size' => null,
            'overlap' => 0,
            'strategies' => ['markdown' => null, 'text' => null, 'transcript' => null],
        ],
    ]);
});

it('splits Markdown by headings, text by paragraphs, and transcripts by whole sentences, by default', function (): void {
    expect(ContentKind::Markdown->strategy())->toBe(SplitStrategy::Markdown)
        ->and(ContentKind::Text->strategy())->toBe(SplitStrategy::Recursive)
        ->and(ContentKind::Transcript->strategy())->toBe(SplitStrategy::Sentence);
});

it('offers the heading strategy for Markdown only, and sentences and fixed sizes for the rest, the default first', function (): void {
    expect(ContentKind::Markdown->strategies())->toBe([SplitStrategy::Markdown, SplitStrategy::Recursive])
        ->and(ContentKind::Text->strategies())->toBe([SplitStrategy::Recursive, SplitStrategy::Sentence, SplitStrategy::Fixed])
        ->and(ContentKind::Transcript->strategies())->toBe([SplitStrategy::Sentence, SplitStrategy::Recursive, SplitStrategy::Fixed]);
});

it('uses the strategy set for a kind, and the default when it does not suit the kind', function (): void {
    config(['chatvia.ai.chunking.strategies' => ['markdown' => 'fixed', 'text' => 'sentence', 'transcript' => 'not-a-strategy']]);

    expect(ContentKind::Markdown->strategy())->toBe(SplitStrategy::Markdown)
        ->and(ContentKind::Text->strategy())->toBe(SplitStrategy::Sentence)
        ->and(ContentKind::Transcript->strategy())->toBe(SplitStrategy::Sentence);
});
