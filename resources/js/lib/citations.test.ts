import type { MessageSource } from '@/types/chat';
import { describe, expect, it } from 'vitest';
import { citedSources, withCitationChips } from './citations';

// Markers point to reference blocks; the client numbers the sources in the
// order they are first cited and drops markers for blocks it has no source for.
const sources: MessageSource[] = [
    { id: 'a', label: 'Policy.pdf', kind: 'file', url: null, page: 3, blocks: [1, 3], excerpt: 'Refunds take five days.' },
    { id: 'b', label: 'Shipping', kind: 'website', url: 'https://shop.test/shipping', page: null, blocks: [2], excerpt: 'Free over 50.' },
    { id: 'c', label: 'FAQ', kind: 'text', url: null, page: null, blocks: [4] },
];

describe('citedSources', () => {
    it('numbers the sources in the order they are first cited, then lists the rest', () => {
        const cited = citedSources('Shipping is free [2]. Refunds take five days [3][1].', sources);

        expect(cited.ordered.map((entry) => [entry.source.id, entry.number])).toEqual([
            ['b', 1],
            ['a', 2],
            ['c', null],
        ]);
        expect(cited.numberOfBlock.get(1)).toBe(2);
    });
});

describe('withCitationChips', () => {
    it('turns markers into chips, merges blocks of one source and drops unknown ones', () => {
        const content = 'Refunds take five days [1, 3]. Shipping is free [2][9].';
        const html = withCitationChips(content, citedSources(content, sources), false);

        expect(html).toBe(
            'Refunds take five days<sup class="cite" data-cite="1" tabindex="0" role="button">1</sup>. Shipping is free<sup class="cite" data-cite="2" tabindex="0" role="button">2</sup>.',
        );
    });

    it('leaves code and links alone and hides a marker still being written', () => {
        const content = 'Use `arr[1]` and [the guide](https://x.test) [2';

        expect(withCitationChips(content, citedSources(content, sources), true)).toBe('Use `arr[1]` and [the guide](https://x.test)');
    });
});
