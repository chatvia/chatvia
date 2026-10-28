import { i18n } from '@/shared/i18n';
import type { StreamCallbacks, StreamDoneData, StreamEnvelope, StreamError, StreamErrorCode, StreamState } from '@/shared/types/stream';
import { csrfHeaders } from '@/shared/utils/csrf';
import { ref } from 'vue';

/**
 * Typed SSE consumer for the envelope v1 wire format.
 *
 * Differences from the legacy useSSE composable:
 *   - parses a single typed envelope (no parallel regex paths for
 *     `data:` / `sources:` / `language:`)
 *   - exposes `state` and `error` reactive refs so the UI can render
 *     toasts and inline retry instead of silently logging
 *   - tracks a per-call generation token: a stale stream from a previous
 *     `start()` cannot leak chunks into a new one after rapid double-submit
 *   - HTTP and JSON-parse failures synthesize an `error` envelope and run
 *     `onError`, instead of writing to the console
 *
 * Display pacing
 * --------------
 * Modern LLMs often finish a multi-paragraph response in under two seconds.
 * Forwarding every `delta` straight to the UI yields the "text just appears
 * in a block" feel users complain about. To match the smooth typewriter
 * cadence of ChatGPT / Claude.ai we buffer incoming text and drain it to
 * `onDelta` at a steady rate (default ~80 chars/sec) via rAF.
 *
 * Slow streams pay no cost — the queue stays empty so paced output keeps
 * up with arrivals. When the server's `done` envelope arrives we accelerate
 * the drain so any backlog flushes before `onDone` fires, keeping the
 * streaming indicator visible until the last character lands.
 *
 * Rates are deliberately calm: fast local models dump a whole response as
 * backlog, so the post-`done` drain dominates the perceived speed. Keep the
 * acceleration modest or the reveal still feels like an instant block.
 */
const PACE_CHARS_PER_SECOND = 50;
const FINISH_ACCELERATION = 2.5;

export interface UseChatStreamOptions {
    /**
     * Override the steady-state display rate. Set to `Infinity` to disable
     * pacing entirely and forward deltas the moment they arrive (the old
     * behaviour, useful for tests).
     */
    charsPerSecond?: number;
}

export function useChatStream(options: UseChatStreamOptions = {}) {
    const charsPerSecond = options.charsPerSecond ?? PACE_CHARS_PER_SECOND;
    const state = ref<StreamState>('idle');
    const error = ref<StreamError | null>(null);

    let abortController: AbortController | null = null;
    let generation = 0;

    function synthesizeError(code: StreamErrorCode, message: string, retryable: boolean): StreamError {
        return { code, message, retryable };
    }

    function reportError(err: StreamError, id: string | null, callbacks: StreamCallbacks) {
        error.value = err;
        state.value = 'error';
        callbacks.onError?.(err, id);
    }

    /**
     * Per-stream pacer. Closed over by `dispatch` so each `run()` call
     * gets its own queue + rAF loop without leaking state between
     * sequential prompts.
     */
    function createPacer(callbacks: StreamCallbacks, isAlive: () => boolean) {
        // Pacing disabled: forward deltas synchronously and skip the rAF
        // machinery entirely.
        if (!isFinite(charsPerSecond) || charsPerSecond <= 0) {
            return {
                push(text: string, id: string) {
                    callbacks.onDelta?.(text, id);
                },
                async flush() {
                    /* no-op */
                },
                drainNow() {
                    /* no-op */
                },
                cancel() {
                    /* no-op */
                },
            };
        }

        let buffer = '';
        let bufferedId = '';
        let lastTickMs = 0;
        let rafHandle: number | null = null;
        let finishing = false;
        let drainResolver: (() => void) | null = null;

        function scheduleTick(): void {
            if (rafHandle !== null) return;
            lastTickMs = performance.now();
            rafHandle = requestAnimationFrame(tick);
        }

        function tick(now: number): void {
            rafHandle = null;
            if (!isAlive()) return;

            const dt = Math.max(0, now - lastTickMs) / 1000;
            lastTickMs = now;

            const rate = finishing ? charsPerSecond * FINISH_ACCELERATION : charsPerSecond;
            const budget = Math.max(1, Math.floor(rate * dt));

            if (buffer.length > 0) {
                const slice = buffer.slice(0, budget);
                buffer = buffer.slice(budget);
                callbacks.onDelta?.(slice, bufferedId);
            }

            if (buffer.length > 0) {
                rafHandle = requestAnimationFrame(tick);
                lastTickMs = now;
            } else if (finishing && drainResolver) {
                const resolve = drainResolver;
                drainResolver = null;
                resolve();
            }
        }

        return {
            push(text: string, id: string) {
                if (!text) return;
                buffer += text;
                bufferedId = id;
                scheduleTick();
            },
            /** Flush any buffered characters at accelerated rate, then resolve. */
            flush(): Promise<void> {
                if (buffer.length === 0) return Promise.resolve();
                finishing = true;
                return new Promise<void>((resolve) => {
                    drainResolver = resolve;
                    scheduleTick();
                });
            },
            /** Hand over every buffered character at once, skipping the typing pace. */
            drainNow() {
                if (rafHandle !== null) {
                    cancelAnimationFrame(rafHandle);
                    rafHandle = null;
                }
                if (buffer.length > 0 && isAlive()) callbacks.onDelta?.(buffer, bufferedId);
                buffer = '';
                if (drainResolver) {
                    const resolve = drainResolver;
                    drainResolver = null;
                    resolve();
                }
            },
            cancel() {
                if (rafHandle !== null) {
                    cancelAnimationFrame(rafHandle);
                    rafHandle = null;
                }
                buffer = '';
                if (drainResolver) {
                    const resolve = drainResolver;
                    drainResolver = null;
                    resolve();
                }
            },
        };
    }

    /**
     * Dispatch a parsed envelope. Returns the `done` envelope (so the run
     * loop can fire `onDone` AFTER the pacer drains), or `null` for
     * non-terminal envelopes.
     */
    function dispatch(
        env: StreamEnvelope,
        callbacks: StreamCallbacks,
        pacer: ReturnType<typeof createPacer>,
    ): { data: StreamDoneData; id: string } | null {
        switch (env.type) {
            case 'start':
                callbacks.onStart?.(env.data, env.id);
                return null;
            case 'delta':
                pacer.push(env.data.text, env.id);
                return null;
            case 'reasoning':
                // Shown as it arrives: thinking comes before the answer, so
                // it never waits on (or holds up) the answer's typing pace.
                callbacks.onReasoning?.(env.data.text, env.id);
                return null;
            case 'meta':
                callbacks.onMeta?.(env.data.key, env.data.value, env.id);
                return null;
            case 'sources':
                callbacks.onSources?.(env.data.sources, env.id);
                return null;
            case 'tool':
                // The text before a tool call belongs to the step it starts
                // (the chat moves it into the step chain), so it must all be
                // in place first, not still typing out behind the call.
                pacer.drainNow();
                callbacks.onTool?.(env.data, env.id);
                return null;
            case 'replace':
                // Output guardrail correction: drop any buffered (uncorrected)
                // tail and hand the consumer the full safe text to render.
                pacer.cancel();
                callbacks.onReplace?.(env.data.text, env.id);
                return null;
            case 'error':
                pacer.cancel();
                reportError({ code: env.data.code, message: env.data.message, retryable: env.data.retryable }, env.id, callbacks);
                return null;
            case 'done':
                // Return the done envelope so the run loop can fire
                // `onDone` AFTER the pacer drains. Otherwise consumers
                // flip `isStreaming=false` mid-flush and the trailing
                // tokens lose their streaming animation.
                return { data: env.data, id: env.id };
        }
    }

    async function run(url: string, body: string, callbacks: StreamCallbacks, myGeneration: number): Promise<void> {
        state.value = 'connecting';
        error.value = null;
        const pacer = createPacer(callbacks, () => myGeneration === generation);

        let response: Response;
        try {
            response = await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'text/event-stream',
                    'X-Requested-With': 'XMLHttpRequest',
                    ...csrfHeaders(),
                },
                body,
                credentials: 'same-origin',
                signal: abortController!.signal,
            });
        } catch (e) {
            if (myGeneration !== generation) {
                pacer.cancel();
                return;
            }
            if ((e as Error).name === 'AbortError') {
                pacer.cancel();
                state.value = 'idle';
                return;
            }
            reportError(synthesizeError('network', i18n.global.t('chat.stream_errors.unreachable'), true), null, callbacks);
            return;
        }

        if (myGeneration !== generation) {
            pacer.cancel();
            return;
        }

        if (!response.ok) {
            reportError(
                synthesizeError('network', i18n.global.t('chat.stream_errors.http_status', { status: response.status }), response.status >= 500),
                null,
                callbacks,
            );
            return;
        }

        const reader = response.body!.getReader();
        const decoder = new TextDecoder();
        let buffer = '';

        state.value = 'streaming';

        while (true) {
            if (myGeneration !== generation) {
                pacer.cancel();
                try {
                    await reader.cancel();
                } catch {
                    /* ignore */
                }
                return;
            }

            let chunk: ReadableStreamReadResult<Uint8Array>;
            try {
                chunk = await reader.read();
            } catch (e) {
                if (myGeneration !== generation) {
                    pacer.cancel();
                    return;
                }
                if ((e as Error).name === 'AbortError') {
                    pacer.cancel();
                    state.value = 'idle';
                    return;
                }
                reportError(synthesizeError('network', i18n.global.t('chat.stream_errors.interrupted'), true), null, callbacks);
                return;
            }

            if (chunk.done) {
                await pacer.flush();
                if (state.value === 'streaming') {
                    state.value = 'done';
                }
                return;
            }

            buffer += decoder.decode(chunk.value, { stream: true });

            const events = buffer.split('\n\n');
            buffer = events.pop() ?? '';

            for (const raw of events) {
                if (myGeneration !== generation) {
                    pacer.cancel();
                    return;
                }
                if (raw.trim() === '') continue;

                const match = raw.match(/^data:\s?(.+)$/s);
                if (!match) continue;

                let env: StreamEnvelope;
                try {
                    env = JSON.parse(match[1]);
                } catch {
                    reportError(synthesizeError('parse', i18n.global.t('chat.stream_errors.malformed'), false), null, callbacks);
                    continue;
                }

                if (env?.v !== 1 || !env.type) continue;

                const terminal = dispatch(env, callbacks, pacer);
                if (terminal) {
                    // Drain any buffered text at accelerated rate so the
                    // visible stream finishes naturally, THEN fire
                    // `onDone`. Consumers typically use `onDone` to flip
                    // `isStreaming=false`, which must happen after the
                    // last paced characters render.
                    await pacer.flush();
                    state.value = 'done';
                    callbacks.onDone?.(terminal.data, terminal.id);
                    try {
                        await reader.cancel();
                    } catch {
                        /* ignore */
                    }
                    return;
                }
            }
        }
    }

    function start(url: string, payload: string, callbacks: StreamCallbacks = {}): void {
        abortController?.abort();
        abortController = new AbortController();
        generation++;
        const myGeneration = generation;

        void run(url, payload, callbacks, myGeneration);
    }

    function stop(): void {
        generation++;
        abortController?.abort();
        abortController = null;
        if (state.value === 'streaming' || state.value === 'connecting') {
            state.value = 'idle';
        }
    }

    return {
        state,
        error,
        start,
        stop,
    };
}
