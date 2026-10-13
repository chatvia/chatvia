<?php

declare(strict_types=1);

use Domain\Knowledge\Retrieval\Services\RetrievalScoring;

describe('plainText', function (): void {
    it('removes tag markup but keeps the text inside it', function (): void {
        expect(RetrievalScoring::plainText('Hello <b>bold</b> and <span class="x">Price: €10</span> end'))
            ->toBe('Hello bold and Price: €10 end')
            ->and(RetrievalScoring::plainText('<ref id="1">cited</ref> visible'))->toBe('cited visible');
    });

    it('drops self-closing tags and turns line break tags into new lines', function (): void {
        expect(RetrievalScoring::plainText('a<img src="x.png"/>b'))->toBe('ab')
            ->and(RetrievalScoring::plainText('first<br>second<br/>third'))->toBe("first\nsecond\nthird");
    });

    it('keeps the line breaks of Markdown headings, lists and tables', function (): void {
        $markdown = "# Shipping\n\n| Option | Price |\n|---|---|\n| Standard | 4.95 |\n\n- one\n- two";

        expect(RetrievalScoring::plainText($markdown))->toBe($markdown);
    });

    it('folds three or more line breaks into one blank line, and trims', function (): void {
        expect(RetrievalScoring::plainText("  line1  \n\n\n\n line2\n"))->toBe("line1\n\n line2");
    });

    it('leaves text that only looks like a tag', function (): void {
        expect(RetrievalScoring::plainText('a < b and c > d, see <https://example.com>'))
            ->toBe('a < b and c > d, see <https://example.com>');
    });
});
