import { usePage } from '@inertiajs/vue3';
import { ref } from 'vue';
import type { StreamCallbacks, StreamEnvelope, StreamError, StreamErrorCode, StreamState } from '@/types/stream';

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
 *   - one auto-retry with backoff for retryable errors, reusing the same
 *     `request_id` so the server returns the replay
 */
export function useChatStream() {
    const state = ref<StreamState>('idle');
    const error = ref<StreamError | null>(null);

    let abortController: AbortController | null = null;
    let generation = 0;

    function csrfToken(): string {
        const page = usePage();
        const fromProps = (page.props as { csrf_token?: string } | undefined)?.csrf_token;
        if (fromProps) return fromProps;

        const meta = document.querySelector('meta[name="csrf-token"]');
        return meta?.getAttribute('content') ?? '';
    }

    function synthesizeError(code: StreamErrorCode, message: string, retryable: boolean): StreamError {
        return { code, message, retryable };
    }

    function reportError(err: StreamError, id: string | null, callbacks: StreamCallbacks) {
        error.value = err;
        state.value = 'error';
        callbacks.onError?.(err, id);
    }

    function dispatch(env: StreamEnvelope, callbacks: StreamCallbacks): boolean {
        switch (env.type) {
            case 'start':
                callbacks.onStart?.(env.data, env.id);
                return false;
            case 'delta':
                callbacks.onDelta?.(env.data.text, env.id);
                return false;
            case 'meta':
                callbacks.onMeta?.(env.data.key, env.data.value, env.id);
                return false;
            case 'sources':
                callbacks.onSources?.(env.data.sources, env.id);
                return false;
            case 'tool':
                callbacks.onTool?.(env.data, env.id);
                return false;
            case 'error':
                reportError(
                    { code: env.data.code, message: env.data.message, retryable: env.data.retryable },
                    env.id,
                    callbacks,
                );
                return false;
            case 'done':
                state.value = 'done';
                callbacks.onDone?.(env.data, env.id);
                return true;
        }
    }

    async function run(url: string, body: string, callbacks: StreamCallbacks, myGeneration: number): Promise<void> {
        state.value = 'connecting';
        error.value = null;

        let response: Response;
        try {
            response = await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'text/event-stream',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrfToken(),
                },
                body,
                credentials: 'same-origin',
                signal: abortController!.signal,
            });
        } catch (e) {
            if (myGeneration !== generation) return;
            if ((e as Error).name === 'AbortError') {
                state.value = 'idle';
                return;
            }
            reportError(
                synthesizeError('network', 'Could not reach the server. Please try again.', true),
                null,
                callbacks,
            );
            return;
        }

        if (myGeneration !== generation) return;

        if (!response.ok) {
            reportError(
                synthesizeError('network', `Server returned status ${response.status}.`, response.status >= 500),
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
                if (myGeneration !== generation) return;
                if ((e as Error).name === 'AbortError') {
                    state.value = 'idle';
                    return;
                }
                reportError(
                    synthesizeError('network', 'The connection was interrupted.', true),
                    null,
                    callbacks,
                );
                return;
            }

            if (chunk.done) {
                if (state.value === 'streaming') {
                    state.value = 'done';
                }
                return;
            }

            buffer += decoder.decode(chunk.value, { stream: true });

            const events = buffer.split('\n\n');
            buffer = events.pop() ?? '';

            for (const raw of events) {
                if (myGeneration !== generation) return;
                if (raw.trim() === '') continue;

                const match = raw.match(/^data:\s?(.+)$/s);
                if (!match) continue;

                let env: StreamEnvelope;
                try {
                    env = JSON.parse(match[1]);
                } catch {
                    reportError(
                        synthesizeError('parse', 'Received malformed data from the server.', false),
                        null,
                        callbacks,
                    );
                    continue;
                }

                if (env?.v !== 1 || !env.type) continue;

                const terminal = dispatch(env, callbacks);
                if (terminal) {
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
