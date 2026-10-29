<?php

declare(strict_types=1);

use Domain\Knowledge\Retrieval\Services\RetrievalScoring;

/**
 * RetrievalScoring::plainText() gives the reranker a chunk as plain text: tags
 * go, their text and the line breaks that carry meaning stay.
 */
it('removes tag markup but keeps the text inside it', function (): void {
    expect(RetrievalScoring::plainText('Hello <b>bold</b> and <span class="x">Price: €10</span> end'))
        ->toBe('Hello bold and Price: €10 end')
        ->and(RetrievalScoring::plainText('<ref id="1">cited</ref> visible'))->toBe('cited visible');
});

it('keeps the line breaks of Markdown headings, lists and tables', function (): void {
    $markdown = "# Shipping\n\n| Option | Price |\n|---|---|\n| Standard | 4.95 |\n\n- one\n- two";

    expect(RetrievalScoring::plainText($markdown))->toBe($markdown);
});
