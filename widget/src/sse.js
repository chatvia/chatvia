import { authHeaders } from './auth.js';
import { getApiUrl } from './endpoints.js';
import { debug } from './utils.js';

export function createSSEClient(agent_id, session_id, conversation_id, callbacks = {}) {
  const sseCallbacks = {
    onOpen: () => {},
    onClose: () => {},
    onMessage: () => {},
    ...callbacks
  };

  let abortController = null;
  let isStreaming = false;

  function connect() {
    debug('SSE client ready', { agent_id, session_id, conversation_id });
    sseCallbacks.onOpen();
  }

  function send(message) {
    // Never log full message content in production — gated via debug().
    debug('SSE send', { conversation_id, type: message?.type });

    // Abort any existing connection
    if (abortController) {
      abortController.abort();
    }

    abortController = new AbortController();
    // Captured so the retry loop below stops the moment a newer send()
    // (or close()) aborts this turn — otherwise a backed-off retry could fire
    // after the connection was intentionally torn down.
    const myController = abortController;
    isStreaming = true;
    let isDone = false;

    const sendUrl = getApiUrl() + '/stream';

    // Transient, worker-/gateway-level failures: the POST was rejected before a
    // worker ran it (nothing persisted server-side), so re-sending the same turn
    // is safe and idempotent. These usually clear within ~1s as a worker frees,
    // so retry a couple of times with backoff before surfacing an error — that
    // turns the common "no reply" blip into a self-healing hiccup.
    const TRANSIENT_STATUS = new Set([429, 502, 503, 504]);
    const MAX_RETRIES = 2;

    function attempt(retriesLeft) {
      let assistantMessageId = null;

      return fetch(sendUrl, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          Accept: 'text/event-stream',
          'X-AGENT-ID': agent_id,
          'X-SESSION-ID': session_id,
          'X-CONVERSATION-ID': conversation_id,
          ...authHeaders(agent_id)
        },
        body: JSON.stringify({
          ...message,
          agent_id: agent_id
        }),
        signal: myController.signal
      }).then(response => {
        if (!response.ok) {
          if (
            TRANSIENT_STATUS.has(response.status) &&
            retriesLeft > 0 &&
            !myController.signal.aborted
          ) {
            const backoff = (MAX_RETRIES - retriesLeft + 1) * 700; // 700ms, then 1400ms
            debug('SSE transient failure — retrying', {
              status: response.status,
              retriesLeft,
              backoff
            });
            return new Promise(resolve => setTimeout(resolve, backoff)).then(() => {
              if (myController.signal.aborted) {
                throw Object.assign(new Error('aborted'), { name: 'AbortError' });
              }
              return attempt(retriesLeft - 1);
            });
          }
          throw new Error(`HTTP error! Status: ${response.status}`);
        }

        debug('SSE stream open');
        sseCallbacks.onOpen();

        const reader = response.body.getReader();
        const decoder = new TextDecoder();
        let buffer = '';

        function processChunk({ done, value }) {
          if (done || isDone) {
            if (!isDone) {
              isDone = true;
              isStreaming = false;
              sseCallbacks.onClose();
            }
            return Promise.resolve();
          }

          // Decode the chunk and add to buffer
          buffer += decoder.decode(value, { stream: true });

          // Process complete SSE messages
          const lines = buffer.split('\n\n');
          buffer = lines.pop() || ''; // Keep the last incomplete chunk

          for (const line of lines) {
            if (line.trim() === '') continue;

            // Extract data from SSE format
            const dataMatch = line.match(/^data: (.+)$/m);

            if (dataMatch) {
              let event;
              try {
                event = JSON.parse(dataMatch[1]);
              } catch (e) {
                console.error('❌ Error parsing SSE data:', e, dataMatch[1]);
                continue;
              }

              const type = event.type;
              const d = event.data || {};

              // start: capture assistant id for feedback/copy; emit nothing
              // (avoids a premature hideLoading flash before any text arrives).
              if (type === 'start') {
                assistantMessageId = d.assistant_message_id || event.id || assistantMessageId;
                continue;
              }

              // terminal: clean `done` envelope OR legacy [DONE]
              if (type === 'done' || event.content === '[DONE]') {
                isDone = true;
                isStreaming = false;
                sseCallbacks.onClose();
                return Promise.resolve();
              }

              // backend-signalled error: surface, then let the following `done` close
              if (type === 'error') {
                sseCallbacks.onMessage({ type: 'error', code: d.code, message: d.message });
                continue;
              }

              // streamed text chunk
              if (type === 'delta') {
                if (d.text) sseCallbacks.onMessage({ content: d.text, id: assistantMessageId });
                continue;
              }

              // function + MCP tool lifecycle
              if (type === 'tool') {
                sseCallbacks.onMessage({ type: 'tool', id: assistantMessageId, ...d });
                continue;
              }

              // handoff now rides inside meta{key:'handoff', value:{status,…}}
              if (type === 'meta' && d.key === 'handoff' && d.value) {
                sseCallbacks.onMessage({ ...d.value, status: d.value.status });
                isDone = true;
                isStreaming = false;
                sseCallbacks.onClose();
                return Promise.resolve();
              }

              // legacy flat handoff: { status: 'human' | 'returned' | 'ai' }
              const legacyStatus = d.status || event.status;
              if (legacyStatus && ['human', 'returned', 'ai'].includes(legacyStatus)) {
                sseCallbacks.onMessage({ ...(event.data || event), status: legacyStatus });
                isDone = true;
                isStreaming = false;
                sseCallbacks.onClose();
                return Promise.resolve();
              }

              // backward-compat: flat { content } (old format + server.js mock)
              if (event.content) sseCallbacks.onMessage(event);
              // meta(non-handoff) / sources / unknown types: ignored
            }
          }

          // Continue reading if not done
          return reader.read().then(processChunk);
        }

        return reader.read().then(processChunk);
      });
    }

    try {
      // Single failure handler for the whole turn — runs only after retries are
      // exhausted (or on a non-transient/aborted failure).
      attempt(MAX_RETRIES).catch(error => {
        isStreaming = false;
        if (error.name === 'AbortError') {
          // Superseded by a new send or torn down — not a failed turn.
          console.log('Fetch aborted');
          sseCallbacks.onClose({ aborted: true });
        } else {
          console.error('❌ SSE error:', error);
          sseCallbacks.onMessage({
            type: 'error',
            message: 'Connection lost. Please try again.'
          });
          sseCallbacks.onClose();
        }
      });

      return Promise.resolve(true); // Return immediately since we're streaming
    } catch (error) {
      console.error('❌ Error in send function:', error);
      isStreaming = false;
      return Promise.resolve(false);
    }
  }

  function close() {
    if (abortController) {
      debug('Aborting SSE connection');
      abortController.abort();
      abortController = null;
    }
    isStreaming = false;
    // Teardown (init, conversation switch, new chat) — not a failed turn, so flag it
    // aborted to keep the empty-close guard from surfacing a spurious error.
    sseCallbacks.onClose({ aborted: true });
  }

  return {
    connect,
    send,
    close,
    isConnected: () => isStreaming
  };
}
