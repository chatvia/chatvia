/**
 * Auto-scroll for the hosted chat transcript. Follows the newest message while
 * a reply streams in, stops following as soon as the visitor scrolls up (wheel,
 * touch or keyboard), and starts again when they reach the bottom or press the
 * "scroll to bottom" button.
 */
import { useScroll } from '@vueuse/core';
import { computed, onMounted, onUnmounted, ref, watch, type Ref } from 'vue';

/** Within this many pixels of the end counts as "at the bottom". */
const BOTTOM_THRESHOLD_PX = 20;

/** Streaming scroll updates are throttled to about one per frame. */
const STREAM_SCROLL_THROTTLE_MS = 16;

const SCROLL_KEYS = ['ArrowUp', 'ArrowDown', 'PageUp', 'PageDown', 'Home', 'End', ' '];

export function useChatScroll(container: Ref<HTMLElement | null>) {
    const isAutoScrollEnabled = ref(true);
    const userHasScrolledUp = ref(false);
    const { arrivedState } = useScroll(container);

    let animationFrame: number | null = null;
    let throttleTimeout: ReturnType<typeof setTimeout> | null = null;

    const showScrollToBottom = computed(() => {
        if (!container.value) return false;
        const { clientHeight, scrollHeight } = container.value;

        return !arrivedState.bottom && scrollHeight > clientHeight;
    });

    const isFollowing = (): boolean => isAutoScrollEnabled.value && !userHasScrolledUp.value;

    function distanceFromBottom(element: HTMLElement): number {
        return element.scrollHeight - element.scrollTop - element.clientHeight;
    }

    /** Follow the newest message again. */
    function follow() {
        userHasScrolledUp.value = false;
        isAutoScrollEnabled.value = true;
    }

    /** Stop following, e.g. while an edited turn re-streams above the fold. */
    function hold() {
        userHasScrolledUp.value = true;
        isAutoScrollEnabled.value = false;
    }

    function scrollToBottom(smooth = false) {
        if (!container.value) return;
        if (animationFrame) cancelAnimationFrame(animationFrame);

        animationFrame = requestAnimationFrame(() => {
            // Instant by default: smooth scrolling while text streams in causes scroll-linked jank.
            container.value?.scrollTo({ top: container.value.scrollHeight, behavior: smooth ? 'smooth' : 'instant' });
            follow();
            animationFrame = null;
        });
    }

    /** Keeps the newest streamed text in view, unless the visitor scrolled away. */
    function scrollWhileStreaming() {
        if (!isFollowing() || !container.value || throttleTimeout) return;

        throttleTimeout = setTimeout(() => {
            if (container.value && isFollowing()) {
                container.value.scrollTo({ top: container.value.scrollHeight, behavior: 'instant' });
            }
            throttleTimeout = null;
        }, STREAM_SCROLL_THROTTLE_MS);
    }

    // Programmatic scrollTo() and a visitor's scroll fire the same event, so
    // this only resumes following at the bottom. Leaving the bottom is detected
    // from the input itself in onUserScrollIntent.
    function onScroll() {
        if (container.value && distanceFromBottom(container.value) < BOTTOM_THRESHOLD_PX) {
            follow();
        }
    }

    function onUserScrollIntent(event: Event) {
        if (!container.value) return;
        if (event instanceof KeyboardEvent && !SCROLL_KEYS.includes(event.key)) return;
        if (distanceFromBottom(container.value) < BOTTOM_THRESHOLD_PX) return;

        hold();
    }

    watch(
        () => arrivedState.bottom,
        (isAtBottom) => {
            if (isAtBottom) follow();
        },
    );

    onMounted(() => {
        if (!container.value) return;

        container.value.addEventListener('scroll', onScroll, { passive: true });
        container.value.addEventListener('wheel', onUserScrollIntent, { passive: true });
        container.value.addEventListener('touchmove', onUserScrollIntent, { passive: true });
        window.addEventListener('keydown', onUserScrollIntent);
    });

    onUnmounted(() => {
        if (animationFrame) cancelAnimationFrame(animationFrame);
        if (throttleTimeout) clearTimeout(throttleTimeout);

        container.value?.removeEventListener('scroll', onScroll);
        container.value?.removeEventListener('wheel', onUserScrollIntent);
        container.value?.removeEventListener('touchmove', onUserScrollIntent);
        window.removeEventListener('keydown', onUserScrollIntent);
    });

    return {
        isAutoScrollEnabled,
        showScrollToBottom,
        follow,
        hold,
        scrollToBottom,
        scrollWhileStreaming,
    };
}

export type ChatScroll = ReturnType<typeof useChatScroll>;
