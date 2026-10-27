/**
 * Swaps a generated image's, audio file's or video's placeholder for the file
 * once its background job is done, without a reload. While anything is still
 * being made the chat listens on the conversation's private media channel,
 * and also asks the server every few seconds in case no live update comes (no
 * WebSocket, or it dropped). Both stop once every file is settled. After a
 * few minutes only a video can still be on its way, so the asking slows down,
 * and it stops once a video has had all its time: a reload is the way back.
 */
import { fetchGeneratedMedia } from '@/api/chatMedia';
import type { GeneratedMedia } from '@/types/generatedMedia';
import { echo } from '@laravel/echo-vue';
import { onUnmounted, watch, type ComputedRef } from 'vue';

const POLL_EVERY_MS = 4000;
/** Pictures and audio files are made by then; after it, ask less often. */
const POLL_FAST_FOR_MS = 3 * 60 * 1000;
const POLL_SLOW_EVERY_MS = 15000;
/** A video may take up to 15 minutes (`chatvia.ai.video_max_wait_minutes`). */
const POLL_FOR_MS = 16 * 60 * 1000;

interface MediaTranscript {
    pendingMediaIds: ComputedRef<string[]>;
    updateMedia: (media: GeneratedMedia) => void;
}

export function useGeneratedMediaUpdates(conversationId: string, transcript: MediaTranscript) {
    const channelName = `chat-media.${conversationId}`;
    let listening = false;
    let timer: ReturnType<typeof setInterval> | null = null;
    let pollingSince = 0;
    let polledAt = 0;

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
        const now = Date.now();

        if (now - pollingSince > POLL_FOR_MS) {
            stopPolling();
            return;
        }

        if (now - pollingSince > POLL_FAST_FOR_MS && now - polledAt < POLL_SLOW_EVERY_MS) return;
        polledAt = now;

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
