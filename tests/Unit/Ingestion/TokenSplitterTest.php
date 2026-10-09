<?php

use Domain\Knowledge\Ingestion\Data\Ocr\PageData;
use Domain\Knowledge\Ingestion\Services\TokenSplitter;

/**
 * TokenSplitter is deterministic, side-effect-free chunking that every ingested
 * document flows through, yet it had no test (its directory was an empty
 * placeholder). A silent regression here corrupts every embedding. The real
 * tokenizer (TikToken / cl100k_base) is used — counts are deterministic.
 */
it('returns a single chunk when the whole text fits within the chunk size', function (): void {
    $splitter = new TokenSplitter(chunkSize: 1000, chunkOverlap: 100, normalize: false);

    $chunks = $splitter->split('A short sentence that easily fits.');

    expect($chunks)->toHaveCount(1)
        ->and($chunks[0])->toBeInstanceOf(PageData::class)
        ->and($chunks[0]->content)->toBe('A short sentence that easily fits.')
        ->and($chunks[0]->pageNumber)->toBe(1)
        ->and($chunks[0]->tokens)->toBeGreaterThan(0);
});

it('splits multi-paragraph text over the limit into multiple in-bound chunks', function (): void {
    $splitter = new TokenSplitter(chunkSize: 20, chunkOverlap: 0, normalize: false);

    $paragraphs = [];
    for ($i = 0; $i < 8; $i++) {
        $paragraphs[] = "This is paragraph number {$i} with a handful of words.";
    }
    $text = implode("\n\n", $paragraphs);

    $chunks = $splitter->split($text);

    expect(count($chunks))->toBeGreaterThan(1);

    // Every paragraph is well under chunkSize, so the splitter must never emit an
    // oversized chunk, and page numbers run sequentially from 1.
    foreach ($chunks as $index => $chunk) {
        expect($chunk)->toBeInstanceOf(PageData::class)
            ->and($chunk->tokens)->toBeLessThanOrEqual(20)
            ->and($chunk->content)->not->toBe('')
            ->and($chunk->pageNumber)->toBe($index + 1);
    }

    // Nothing is dropped: the first and last paragraphs both survive somewhere.
    $joined = implode("\n", array_map(fn (PageData $c): string => $c->content, $chunks));
    expect($joined)->toContain('paragraph number 0')
        ->and($joined)->toContain('paragraph number 7');
});

it('prepends overlap context from the previous chunk when overlap is enabled', function (): void {
    $paragraphs = [];
    for ($i = 0; $i < 8; $i++) {
        $paragraphs[] = "This is paragraph number {$i} with a handful of words.";
    }
    $text = implode("\n\n", $paragraphs);

    $noOverlap = new TokenSplitter(chunkSize: 20, chunkOverlap: 0, normalize: false)->split($text);
    $withOverlap = new TokenSplitter(chunkSize: 20, chunkOverlap: 10, normalize: false)->split($text);

    // Overlap is applied after chunking, so the base chunk count is unchanged…
    expect(count($withOverlap))->toBe(count($noOverlap))
        ->and(count($withOverlap))->toBeGreaterThan(1);

    // …but each follow-on chunk carries context prepended from the prior chunk,
    // so it is strictly longer than its no-overlap counterpart.
    expect(strlen($withOverlap[1]->content))->toBeGreaterThan(strlen($noOverlap[1]->content));
});
