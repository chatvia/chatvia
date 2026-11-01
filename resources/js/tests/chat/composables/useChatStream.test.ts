import { useChatStream } from '@/chat/composables/useChatStream';
import type { StreamEnvelope } from '@/shared/types/stream';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

vi.mock('@/shared/i18n', () => ({ i18n: { global: { t: (key: string) => key } } }));
vi.mock('@/shared/utils/csrf', () => ({ csrfHeaders: () => ({}) }));

let seq = 0;

function envelope(type: StreamEnvelope['type'], data: unknown): string {
    return `data: ${JSON.stringify({ v: 1, seq: seq++, id: 'm1', type, data })}\n\n`;
}

/** A response whose body sends each chunk as its own read. */
function sse(...chunks: string[]): Response {
    return sseAfter(0, chunks, []);
}

/** A response that sends `first` at once and `rest` after `delayMs`, while the first text is still typing. */
function sseAfter(delayMs: number, first: string[], rest: string[]): Response {
    const encoder = new TextEncoder();

    return new Response(
        new ReadableStream({
            start(controller) {
                first.forEach((chunk) => controller.enqueue(encoder.encode(chunk)));
                setTimeout(() => {
                    rest.forEach((chunk) => controller.enqueue(encoder.encode(chunk)));
                    controller.close();
                }, delayMs);
            },
        }),
        { status: 200 },
    );
}

beforeEach(() => {
    // rAF in Node: a frame every 16ms on the fake clock.
    vi.useFakeTimers();
    vi.stubGlobal('requestAnimationFrame', (callback: (now: number) => void) => setTimeout(() => callback(performance.now()), 16));
    vi.stubGlobal('cancelAnimationFrame', (handle: ReturnType<typeof setTimeout>) => clearTimeout(handle));
});

afterEach(() => {
    vi.useRealTimers();
    vi.unstubAllGlobals();
});

describe('useChatStream', () => {
    it('hands over all the text before a tool call ahead of the tool event, though text is typed out slowly', async () => {
        vi.stubGlobal(
            'fetch',
            vi.fn(async () =>
                sse(
                    envelope('delta', { text: 'Let me look up your event types to find that intro call.' }),
                    envelope('tool', { phase: 'start', tool_call_id: 'call_1', label: 'Calendly' }),
                    envelope('tool', { phase: 'end', tool_call_id: 'call_1' }),
                    envelope('delta', { text: 'Found it.' }),
                    envelope('done', { reason: 'complete', response_time_ms: 1, finished_at: null }),
                ),
            ),
        );

        const log: string[] = [];
        let text = '';
        const finished = new Promise<void>((resolve) => {
            useChatStream().start('/stream', '{}', {
                onDelta: (delta) => {
                    text += delta;
                },
                onTool: (data) => {
                    log.push(`${data.phase}: ${text}`);
                    if (data.phase === 'start') text = '';
                },
                onDone: () => resolve(),
            });
        });

        await vi.runAllTimersAsync();
        await finished;

        expect(log).toEqual(['start: Let me look up your event types to find that intro call.', 'end: ']);
        expect(text).toBe('Found it.');
    });

    it('swaps the shown part of a corrected answer at once and keeps typing the rest', async () => {
        const corrected = 'Delivery takes five days[1]. Returns are free within thirty days of the order date[2].';
        vi.stubGlobal(
            'fetch',
            vi.fn(async () =>
                sseAfter(
                    200,
                    [envelope('delta', { text: 'Delivery takes five days [1]. Returns are free within thirty days of the order date [2].' })],
                    [envelope('replace', { text: corrected }), envelope('done', { reason: 'complete', response_time_ms: 1, finished_at: null })],
                ),
            ),
        );

        let text = '';
        let replaced: string | null = null;
        let deltasAfterReplace = 0;
        const finished = new Promise<void>((resolve) => {
            useChatStream().start('/stream', '{}', {
                onDelta: (delta) => {
                    text += delta;
                    if (replaced !== null) deltasAfterReplace++;
                },
                onReplace: (full) => {
                    replaced = full;
                    text = full;
                },
                onDone: () => resolve(),
            });
        });

        await vi.runAllTimersAsync();
        await finished;

        // The correction arrived mid-answer: the part on screen was swapped for
        // the matching stretch of the corrected text, nothing more.
        expect(replaced).not.toBeNull();
        expect(replaced!.length).toBeGreaterThan(0);
        expect(replaced!.length).toBeLessThan(corrected.length);
        expect(corrected.startsWith(replaced!)).toBe(true);
        // The rest was typed out, not dumped in one block.
        expect(deltasAfterReplace).toBeGreaterThan(1);
        expect(text).toBe(corrected);
    });
});
