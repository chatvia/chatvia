/**
 * Swaps a generated image's or audio file's placeholder for the file once its
 * background job is done, without a reload. While anything is still being
 * made the chat listens on the conversation's private media channel, and also
 * asks the server every few seconds in case no live update comes (no
 * WebSocket, or it dropped). Both stop once every file is settled, and the
 * asking also stops after a few minutes, when a reload is the way back.
 */
import { fetchGeneratedMedia } from '@/api/chatMedia';
import type { GeneratedMedia } from '@/types/generatedMedia';
import { echo } from '@laravel/echo-vue';
import { onUnmounted, watch, type ComputedRef } from 'vue';

const POLL_EVERY_MS = 4000;
const POLL_FOR_MS = 3 * 60 * 1000;

interface MediaTranscript {
    pendingMediaIds: ComputedRef<string[]>;
    updateMedia: (media: GeneratedMedia) => void;
}

export function useGeneratedMediaUpdates(conversationId: string, transcript: MediaTranscript) {
    const channelName = `chat-media.${conversationId}`;
    let listening = false;
    let timer: ReturnType<typeof setInterval> | null = null;
    let pollingSince = 0;

    function listen() {
        if (listening) return;
        listening = true;

        try {
            echo()
                .private(channelName)
                .listen('.GeneratedMediaUpdated', (media: GeneratedMedia) => transcript.updateMedia(media));
        } catch {
            // No WebSocket on this page: polling covers it.
        }
    }

    function stopListening() {
        if (!listening) return;
        listening = false;

        try {
            echo().leave(channelName);
        } catch {
            // Nothing to leave.
        }
    }

    async function poll() {
        if (Date.now() - pollingSince > POLL_FOR_MS) {
            stopPolling();
            return;
        }

        try {
            const pending = new Set(transcript.pendingMediaIds.value);
            for (const media of await fetchGeneratedMedia(conversationId)) {
                if (pending.has(media.id) && media.status !== 'pending') transcript.updateMedia(media);
            }
        } catch {
            // Try again on the next tick.
        }
    }

    function startPolling() {
        if (timer) return;
        pollingSince = Date.now();
        timer = setInterval(() => void poll(), POLL_EVERY_MS);
    }

    function stopPolling() {
        if (!timer) return;
        clearInterval(timer);
        timer = null;
    }

    watch(
        () => transcript.pendingMediaIds.value.length > 0,
        (hasPending) => {
            if (hasPending) {
                listen();
                startPolling();
            } else {
                stopListening();
                stopPolling();
            }
        },
        { immediate: true },
    );

    onUnmounted(() => {
        stopListening();
        stopPolling();
    });
}
