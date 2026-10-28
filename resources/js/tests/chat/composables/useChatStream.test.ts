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
    const encoder = new TextEncoder();

    return new Response(
        new ReadableStream({
            start(controller) {
                chunks.forEach((chunk) => controller.enqueue(encoder.encode(chunk)));
                controller.close();
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
});
