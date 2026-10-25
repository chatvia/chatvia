/**
 * Reads hosted-chat replies aloud: one shared player, so starting a reply
 * stops the one playing. Each reply's MP3 is fetched once per page (the
 * server saves it too) and kept as an object URL for replays. The player is
 * made inside the click and first plays a silent clip, so Safari still lets
 * it play once the file arrives a few seconds later.
 */
import { fetchMessageSpeech } from '@/api/chat';
import { computed, onBeforeUnmount, ref } from 'vue';

export type ReadAloudStatus = 'idle' | 'loading' | 'playing';

/** An empty WAV file: playing it inside the click unlocks the player. */
const SILENCE = 'data:audio/wav;base64,UklGRiQAAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQAAAAA=';

const activeId = ref<string | null>(null);
const state = ref<ReadAloudStatus>('idle');
const urls = new Map<string, string>();
let player: HTMLAudioElement | null = null;
/** Bumped by every start and stop, so a late answer of an old request is dropped. */
let generation = 0;

function stop(): void {
    generation++;
    player?.pause();
    player = null;
    activeId.value = null;
    state.value = 'idle';
}

/** Plays a reply; rejects when it could not be read, so the caller can say so. */
async function play(conversationId: string, messageId: string): Promise<void> {
    stop();
    const ticket = generation;
    const audio = new Audio(SILENCE);
    audio.play().catch(() => {});
    player = audio;
    activeId.value = messageId;
    state.value = 'loading';

    try {
        let url = urls.get(messageId);

        if (!url) {
            url = URL.createObjectURL(await fetchMessageSpeech(conversationId, messageId));
            urls.set(messageId, url);
        }

        if (ticket !== generation) return;

        audio.addEventListener('ended', () => {
            if (player === audio) stop();
        });
        audio.src = url;
        await audio.play();

        if (ticket === generation) state.value = 'playing';
    } catch (error) {
        if (ticket !== generation) return;
        stop();
        throw error;
    }
}

/** The read-aloud state of one reply, and the shared play / stop. */
export function useReadAloud(messageId: () => string | null) {
    const status = computed<ReadAloudStatus>(() => (activeId.value !== null && activeId.value === messageId() ? state.value : 'idle'));

    // A reply that leaves the page (conversation switched, message deleted) stops talking.
    onBeforeUnmount(() => {
        if (activeId.value !== null && activeId.value === messageId()) stop();
    });

    return { status, play, stop };
}
