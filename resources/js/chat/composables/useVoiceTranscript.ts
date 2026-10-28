/**
 * Mirrors a voice call into the hosted chat transcript: each turn of the
 * call (the visitor's words, the agent's spoken reply) is added to the
 * thread as it finishes, in the order it was spoken. The server saves the
 * same turns itself; once the call is over, the thread is reloaded from it,
 * so the messages get their real ids (feedback, delete, sources work).
 */
import type { useChatMessages } from '@/chat/composables/useChatMessages';
import type { VoiceTurnEvent } from '@/chat/composables/useRealtimeVoice';

/**
 * How long after the call the thread is reloaded: the server saves a reply
 * once the visitor's words are written down, a moment after the call ends.
 */
const SYNC_DELAY_MS = 2500;

/** Shown until the visitor's words are written down. */
const PENDING_TEXT = '…';

export function useVoiceTranscript(transcript: ReturnType<typeof useChatMessages>, onChange?: () => void) {
    const { messages } = transcript;
    /** The provider's id of each spoken turn → the index of its message in the thread. */
    const userTurns = new Map<string, number>();

    function onTurn(event: VoiceTurnEvent): void {
        if (event.kind === 'user-started') {
            userTurns.set(event.id, messages.value.length);
            messages.value.push({ role: 'user', content: PENDING_TEXT, id: 0 });
        } else if (event.kind === 'user') {
            const index = userTurns.get(event.id);

            if (index !== undefined && messages.value[index]) {
                messages.value[index].content = event.text || PENDING_TEXT;
            } else if (event.text) {
                messages.value.push({ role: 'user', content: event.text, id: 0 });
            }
        } else {
            messages.value.push({ role: 'assistant', content: event.text, id: 0 });
        }

        onChange?.();
    }

    /** The server's copy of the thread, once its last turn is saved. */
    async function syncAfterCall(): Promise<void> {
        userTurns.clear();
        await new Promise((resolve) => window.setTimeout(resolve, SYNC_DELAY_MS));

        try {
            await transcript.load();
            void transcript.loadSources();
            onChange?.();
        } catch {
            // The live copy stays; a page reload shows the saved one.
        }
    }

    return { onTurn, syncAfterCall };
}
