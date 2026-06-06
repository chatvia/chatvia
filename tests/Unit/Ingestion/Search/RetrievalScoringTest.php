<?php

use Domain\Knowledge\Ingestion\Services\Search\RetrievalScoring;

describe('confidenceScore', function (): void {
    it('returns 0.99 for a single or empty result set', function (): void {
        expect(RetrievalScoring::confidenceScore(0, 1))->toBe(0.99)
            ->and(RetrievalScoring::confidenceScore(0, 0))->toBe(0.99);
    });

    it('maps the top result to 0.99 and the last to 0.50', function (): void {
        expect(RetrievalScoring::confidenceScore(0, 4))->toBe(0.99)
            ->and(RetrievalScoring::confidenceScore(3, 4))->toBe(0.5);
    });

    it('spreads ranks linearly between 0.99 and 0.50', function (): void {
        expect(RetrievalScoring::confidenceScore(0, 5))->toBe(0.99)
            ->and(RetrievalScoring::confidenceScore(1, 5))->toBe(0.8675)
            ->and(RetrievalScoring::confidenceScore(2, 5))->toBe(0.745)
            ->and(RetrievalScoring::confidenceScore(3, 5))->toBe(0.6225)
            ->and(RetrievalScoring::confidenceScore(4, 5))->toBe(0.5);
    });
});

describe('stripXmlTags', function (): void {
    it('removes a paired tag together with its content', function (): void {
        expect(RetrievalScoring::stripXmlTags('<ref id="1">cited</ref> visible'))->toBe('visible');
    });

    it('removes multiple paired tags and collapses the gaps', function (): void {
        expect(RetrievalScoring::stripXmlTags('Hello <b>bold</b> and <i>italic</i> end'))
            ->toBe('Hello and end');
    });

    it('drops self-closing tags', function (): void {
        expect(RetrievalScoring::stripXmlTags('a<img/>b'))->toBe('ab');
    });

    it('collapses whitespace and trims', function (): void {
        expect(RetrievalScoring::stripXmlTags("line1\n\n  line2"))->toBe('line1 line2');
    });

    it('leaves tag-free text intact', function (): void {
        expect(RetrievalScoring::stripXmlTags('plain text'))->toBe('plain text');
    });
});
