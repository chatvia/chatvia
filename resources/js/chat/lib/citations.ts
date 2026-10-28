/**
 * Inline citations: an answer's `[n]` markers point to the numbered
 * reference blocks the model was given, and each source the client got lists
 * the blocks it was (`blocks`). Here the markers become small numbered chips,
 * numbered in the order the sources are first cited ([1], [2], … rather than
 * the block numbers), and the sources are put in that same order for the
 * list under the answer. A marker for a block no source has is dropped.
 * Markers inside code are left as they are, and a half-written marker at the
 * end of a streaming answer is hidden until it is complete.
 */
import type { MessageSource } from '@/shared/types/chat';

/** `[1]` or `[1, 3]`, with the space before it, but not a markdown link `[1](…)`. */
const MARKER = / ?\[(\d{1,3}(?:\s*,\s*\d{1,3})*)\](?!\()/g;

/** Fenced code blocks and inline code spans, kept apart from the citation pass. */
const CODE = /(```[\s\S]*?```|`[^`\n]*`)/;

/** A marker still being written at the end of a streaming answer: `[`, `[1`, `[1,`. */
const PARTIAL_MARKER = / ?\[\d{0,3}(?:\s*,\s*\d{0,3})*$/;

export interface CitedSources {
    /** The display number of each block number the answer cites. */
    numberOfBlock: Map<number, number>;
    /** The sources, the cited ones first in citation order, each with its display number (null when not cited). */
    ordered: Array<{ source: MessageSource; number: number | null }>;
}

function outsideCode(text: string, change: (part: string) => string): string {
    return text
        .split(CODE)
        .map((part, index) => (index % 2 === 1 ? part : change(part)))
        .join('');
}

function blockNumbers(match: string): number[] {
    return match.split(',').map((value) => Number.parseInt(value.trim(), 10));
}

/** Numbers the sources an answer cites in the order they are first cited. */
export function citedSources(content: string, sources: MessageSource[]): CitedSources {
    const sourceOfBlock = new Map<number, MessageSource>();
    sources.forEach((source) => (source.blocks ?? []).forEach((block) => sourceOfBlock.set(block, source)));

    const numberOfSource = new Map<MessageSource, number>();
    const numberOfBlock = new Map<number, number>();

    outsideCode(content, (part) => {
        for (const match of part.matchAll(MARKER)) {
            for (const block of blockNumbers(match[1])) {
                const source = sourceOfBlock.get(block);
                if (!source) continue;
                if (!numberOfSource.has(source)) numberOfSource.set(source, numberOfSource.size + 1);
                numberOfBlock.set(block, numberOfSource.get(source)!);
            }
        }
        return part;
    });

    const cited = [...numberOfSource.entries()].map(([source, number]) => ({ source, number }));
    const uncited = sources.filter((source) => !numberOfSource.has(source)).map((source) => ({ source, number: null }));

    return { numberOfBlock, ordered: [...cited, ...uncited] };
}

/**
 * The answer's markdown with each marker as a chip: `<sup>` with the display
 * number, which the message body opens as a source card. Several blocks of
 * one source make one chip. Run before the markdown is parsed; DOMPurify
 * keeps the `data-cite` attribute.
 */
export function withCitationChips(content: string, cited: CitedSources, isStreaming: boolean): string {
    const text = isStreaming ? content.replace(PARTIAL_MARKER, '') : content;

    return outsideCode(text, (part) =>
        part.replace(MARKER, (_marker, match: string) =>
            [
                ...new Set(
                    blockNumbers(match)
                        .map((block) => cited.numberOfBlock.get(block))
                        .filter((n): n is number => n !== undefined),
                ),
            ]
                .map((n) => `<sup class="cite" data-cite="${n}" tabindex="0" role="button">${n}</sup>`)
                .join(''),
        ),
    );
}
