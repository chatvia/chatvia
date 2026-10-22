/**
 * Scrolling for the hosted chat transcript, as in ChatGPT: a sent prompt moves
 * to the top of the view and its reply streams in underneath without moving
 * the page. A spacer under the transcript makes that room and shrinks as the
 * reply grows. Following the stream starts only when the visitor asks for it
 * (the "scroll to bottom" button, or scrolling down to the end themselves),
 * and stops as soon as they scroll up.
 */
import { useScroll } from '@vueuse/core';
import { computed, nextTick, onMounted, onUnmounted, ref, type Ref } from 'vue';

/** Within this many pixels of the end counts as "at the bottom". */
const BOTTOM_THRESHOLD_PX = 20;

/** Streaming scroll updates are throttled to about one per frame. */
const STREAM_SCROLL_THROTTLE_MS = 16;

const SCROLL_KEYS = ['ArrowUp', 'ArrowDown', 'PageUp', 'PageDown', 'Home', 'End', ' '];

function prefersReducedMotion(): boolean {
    return window.matchMedia?.('(prefers-reduced-motion: reduce)').matches ?? false;
}

/**
 * @param container The scrolling element.
 * @param content The transcript inside it (everything above the spacer).
 */
export function useChatScroll(container: Ref<HTMLElement | null>, content: Ref<HTMLElement | null>) {
    const isFollowing = ref(true);
    const { arrivedState } = useScroll(container);

    /** Transcript index of the latest pinned prompt; the spacer is sized for its turn. */
    const anchorIndex = ref<number | null>(null);
    /** Whether that prompt is still held at the top (the visitor has not scrolled since). */
    const isPinned = ref(false);
    /** Height of the room left under the pinned turn. */
    const spacerHeight = ref(0);

    let animationFrame: number | null = null;
    let throttleTimeout: ReturnType<typeof setTimeout> | null = null;
    let resizeObserver: ResizeObserver | null = null;

    const showScrollToBottom = computed(() => {
        if (!container.value) return false;
        const { clientHeight, scrollHeight } = container.value;

        return !arrivedState.bottom && scrollHeight > clientHeight;
    });

    function distanceFromBottom(element: HTMLElement): number {
        return element.scrollHeight - element.scrollTop - element.clientHeight;
    }

    function rowElement(index: number): HTMLElement | null {
        return container.value?.querySelector<HTMLElement>(`#message-${index}`) ?? null;
    }

    /** Room under the pinned turn so its prompt can sit at the top of the view. */
    function measureSpacer() {
        if (anchorIndex.value === null || !container.value || !content.value) {
            spacerHeight.value = 0;
            return;
        }

        const row = rowElement(anchorIndex.value);
        if (!row) {
            spacerHeight.value = 0;
            return;
        }

        const turnHeight = content.value.getBoundingClientRect().bottom - row.getBoundingClientRect().top;
        spacerHeight.value = Math.max(0, Math.floor(container.value.clientHeight - turnHeight));
    }

    /** Follow the newest message again. */
    function follow() {
        isFollowing.value = true;
    }

    /** Stop following, e.g. after the visitor scrolled up. */
    function hold() {
        isFollowing.value = false;
    }

    /**
     * Moves the prompt at `index` to the top of the view and leaves its reply
     * to stream in below it. Used for sent, edited, regenerated and retried turns.
     */
    async function pinToTop(index: number) {
        hold();
        anchorIndex.value = index;
        isPinned.value = true;

        await nextTick();
        measureSpacer();
        await nextTick();

        const row = rowElement(index);
        if (!container.value || !row) return;

        const offset = row.getBoundingClientRect().top - container.value.getBoundingClientRect().top;
        container.value.scrollTo({ top: container.value.scrollTop + offset, behavior: prefersReducedMotion() ? 'instant' : 'smooth' });
    }

    /** Forgets the pinned turn, e.g. once messages above it were deleted. */
    function unpin() {
        anchorIndex.value = null;
        isPinned.value = false;
        spacerHeight.value = 0;
    }

    function scrollToBottom(smooth = false) {
        if (!container.value) return;
        if (animationFrame) cancelAnimationFrame(animationFrame);

        animationFrame = requestAnimationFrame(() => {
            // Instant by default: smooth scrolling while text streams in causes scroll-linked jank.
            const behavior = smooth && !prefersReducedMotion() ? 'smooth' : 'instant';
            container.value?.scrollTo({ top: container.value.scrollHeight, behavior });
            follow();
            animationFrame = null;
        });
    }

    /** Keeps the newest streamed text in view while the visitor follows the stream. */
    function scrollWhileStreaming() {
        if (!isFollowing.value || !container.value || throttleTimeout) return;

        throttleTimeout = setTimeout(() => {
            if (container.value && isFollowing.value) {
                container.value.scrollTo({ top: container.value.scrollHeight, behavior: 'instant' });
            }
            throttleTimeout = null;
        }, STREAM_SCROLL_THROTTLE_MS);
    }

    // Programmatic scrollTo() fires this too, so it never stops following;
    // only the visitor's own input does (onUserScrollIntent). Reaching the
    // end resumes following, unless a pinned prompt still fills the view.
    function onScroll() {
        if (!container.value || isPinned.value) return;
        if (distanceFromBottom(container.value) < BOTTOM_THRESHOLD_PX) follow();
    }

    // Scrolling by hand releases the pinned prompt: from then on the visitor
    // decides, and reaching the end follows the stream again.
    function onUserScrollIntent(event: Event) {
        if (!container.value) return;
        if (event instanceof KeyboardEvent && !SCROLL_KEYS.includes(event.key)) return;
        if (event instanceof KeyboardEvent && event.target instanceof HTMLElement && event.target.closest('textarea, input')) return;

        if (isPinned.value) {
            // The spacer keeps tracking the turn, so the page does not jump; only the hold ends.
            isPinned.value = false;
            hold();
            return;
        }

        if (distanceFromBottom(container.value) >= BOTTOM_THRESHOLD_PX) hold();
    }

    onMounted(() => {
        if (!container.value) return;

        container.value.addEventListener('scroll', onScroll, { passive: true });
        container.value.addEventListener('wheel', onUserScrollIntent, { passive: true });
        container.value.addEventListener('touchmove', onUserScrollIntent, { passive: true });
        window.addEventListener('keydown', onUserScrollIntent);

        // The reply growing, the composer growing or the window resizing all
        // change how much room the pinned turn needs.
        resizeObserver = new ResizeObserver(() => {
            if (anchorIndex.value !== null) measureSpacer();
        });
        resizeObserver.observe(container.value);
        if (content.value) resizeObserver.observe(content.value);
    });

    onUnmounted(() => {
        if (animationFrame) cancelAnimationFrame(animationFrame);
        if (throttleTimeout) clearTimeout(throttleTimeout);
        resizeObserver?.disconnect();

        container.value?.removeEventListener('scroll', onScroll);
        container.value?.removeEventListener('wheel', onUserScrollIntent);
        container.value?.removeEventListener('touchmove', onUserScrollIntent);
        window.removeEventListener('keydown', onUserScrollIntent);
    });

    return {
        isFollowing,
        showScrollToBottom,
        spacerHeight,
        follow,
        hold,
        pinToTop,
        unpin,
        scrollToBottom,
        scrollWhileStreaming,
    };
}

export type ChatScroll = ReturnType<typeof useChatScroll>;
