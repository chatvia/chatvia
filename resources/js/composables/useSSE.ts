import {ref} from 'vue';

export function useSSE() {
    const isStreaming = ref(false);
    let abortController: AbortController | null = null;

    const startStream = (url: string, payload: string, callbacks: {
        onMessage?: (content: string) => void,
        onDone?: () => void,
        onError?: (error: any) => void,
        onSources?: (sources: any[]) => void
    }) => {
        return () => {
            // Abort any existing connection
            if (abortController) {
                abortController.abort();
            }

            abortController = new AbortController();
            isStreaming.value = true;
            let isDone = false;

            // Create POST request with payload
            fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'text/event-stream',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: payload,
                credentials: 'same-origin',
                signal: abortController.signal,
            })
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! Status: ${response.status}`);
                    }

                    const reader = response.body!.getReader();
                    const decoder = new TextDecoder();
                    let buffer = '';

                    function processChunk({done, value}: ReadableStreamReadResult<Uint8Array>): Promise<void> {
                        if (done || isDone) {
                            if (!isDone) {
                                isDone = true;
                                isStreaming.value = false;
                                if (callbacks.onDone) callbacks.onDone();
                            }
                            return Promise.resolve();
                        }

                        // Decode the chunk and add to buffer
                        buffer += decoder.decode(value, {stream: true});

                        // Process complete SSE messages
                        const lines = buffer.split('\n\n');
                        buffer = lines.pop() || ''; // Keep the last incomplete chunk

                        for (const line of lines) {
                            if (line.trim() === '') continue;

                            // Extract data from SSE format
                            const dataMatch = line.match(/^data: (.+)$/m);
                            const sourcesMatch = line.match(/^sources: (.+)$/m);
                            
                            if (dataMatch) {
                                try {
                                    const data = JSON.parse(dataMatch[1]);
                                    console.log('Received SSE message:', data);
                                    if (data.content === '[DONE]') {
                                        isDone = true;
                                        isStreaming.value = false;
                                        if (callbacks.onDone) callbacks.onDone();
                                        return Promise.resolve(); // Stop processing after [DONE]
                                    } else if (data.content) {
                                        if (callbacks.onMessage) callbacks.onMessage(data.content);
                                    }
                                } catch (e) {
                                    console.error('Error parsing SSE data:', e);
                                }
                            } else if (sourcesMatch) {
                                try {
                                    const sources = JSON.parse(sourcesMatch[1]);
                                    console.log('Received SSE sources:', sources);
                                    if (callbacks.onSources) callbacks.onSources(sources);
                                } catch (e) {
                                    console.error('Error parsing SSE sources:', e);
                                }
                            }
                        }

                        // Continue reading if not done
                        return reader.read().then(processChunk);
                    }

                    return reader.read().then(processChunk);
                })
                .catch(error => {
                    if (error.name === 'AbortError') {
                        console.log('Fetch aborted');
                    } else {
                        console.error('SSE error:', error);
                        if (callbacks.onError) callbacks.onError(error);
                    }
                    isStreaming.value = false;
                });
        };
    };

    const stopStream = () => {
        if (abortController) {
            abortController.abort();
            abortController = null;
        }
        isStreaming.value = false;
    };

    return {
        isStreaming,
        startStream,
        stopStream
    };
}
