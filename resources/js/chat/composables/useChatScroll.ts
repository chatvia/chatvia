/**
 * Scrolling for the hosted chat transcript, as in ChatGPT: a sent prompt moves
 * to the top of the view and its reply streams in underneath. A spacer under
 * the transcript makes that room and shrinks as the reply grows; once the
 * reply reaches the bottom of the view, the view glides down with it. Scrolling
 * up stops that at once, and scrolling back to the end (or the "scroll to
 * bottom" button) starts it again.
 */
import { useScroll } from '@vueuse/core';
import { computed, nextTick, onMounted, onUnmounted, ref, type Ref } from 'vue';

/** Within this many pixels of the end counts as "at the bottom". */
const BOTTOM_THRESHOLD_PX = 20;

/** Share of the distance to the bottom covered each frame while following: an ease-out glide. */
const FOLLOW_EASING = 0.2;

/** Longest a smooth pin scroll may take before following takes over (browsers without `scrollend`). */
const PIN_SCROLL_MAX_MS = 700;

const SCROLL_UP_KEYS = ['ArrowUp', 'PageUp', 'Home'];

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
    /** Height of the room left under the pinned turn. */
    const spacerHeight = ref(0);

    // While the smooth scroll that pins a prompt runs, following would cut it short.
    let isPinning = false;
    let pinTimeout: ReturnType<typeof setTimeout> | null = null;
    let followFrame: number | null = null;
    let jumpFrame: number | null = null;
    let touchStartY: number | null = null;
    let lastScrollTop = 0;
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

    /** One frame of the glide towards the bottom; repeats until it catches up. */
    function followStep() {
        followFrame = null;
        const element = container.value;
        if (!element || !isFollowing.value || isPinning) return;

        const gap = distanceFromBottom(element);
        if (gap <= 1) return;

        element.scrollTop += prefersReducedMotion() ? gap : Math.max(1, Math.ceil(gap * FOLLOW_EASING));
        followFrame = requestAnimationFrame(followStep);
    }

    function stopFollowing() {
        if (followFrame) cancelAnimationFrame(followFrame);
        followFrame = null;
    }

    /** Follow the newest message again. */
    function follow() {
        isFollowing.value = true;
    }

    /** Stop following, e.g. after the visitor scrolled up. */
    function hold() {
        isFollowing.value = false;
        isPinning = false;
        stopFollowing();
    }

    function endPinning() {
        if (pinTimeout) clearTimeout(pinTimeout);
        pinTimeout = null;
        if (!isPinning) return;

        isPinning = false;
        scrollWhileStreaming();
    }

    /**
     * Moves the prompt at `index` to the top of the view and lets its reply
     * stream in below it. Used for sent, edited, regenerated and retried
     * turns. Only the last turn is followed as it grows: an edited turn
     * higher up stays where the visitor is reading.
     */
    async function pinToTop(index: number) {
        stopFollowing();
        anchorIndex.value = index;

        await nextTick();
        measureSpacer();
        await nextTick();

        const row = rowElement(index);
        if (!container.value || !row) return;

        isFollowing.value = rowElement(index + 2) === null;

        const offset = row.getBoundingClientRect().top - container.value.getBoundingClientRect().top;
        if (Math.abs(offset) < 1 || prefersReducedMotion()) {
            container.value.scrollTop += offset;
            return;
        }

        isPinning = true;
        container.value.scrollTo({ top: container.value.scrollTop + offset, behavior: 'smooth' });
        pinTimeout = setTimeout(endPinning, PIN_SCROLL_MAX_MS);
    }

    /** Forgets the pinned turn, e.g. once messages above it were deleted. */
    function unpin() {
        anchorIndex.value = null;
        spacerHeight.value = 0;
    }

    /** Jumps (or, for the button, scrolls smoothly) to the end and follows from there. */
    function scrollToBottom(smooth = false) {
        if (!container.value) return;
        if (jumpFrame) cancelAnimationFrame(jumpFrame);
        stopFollowing();

        jumpFrame = requestAnimationFrame(() => {
            const behavior = smooth && !prefersReducedMotion() ? 'smooth' : 'instant';
            container.value?.scrollTo({ top: container.value.scrollHeight, behavior });
            follow();
            jumpFrame = null;
        });
    }

    /** Keeps the newest streamed text in view while the visitor follows the stream. */
    function scrollWhileStreaming() {
        if (!isFollowing.value || isPinning || followFrame) return;
        followFrame = requestAnimationFrame(followStep);
    }

    // Programmatic scrolling fires this too, so it never stops following;
    // only the visitor's own input does (onUserScrollIntent). Only a move
    // down resumes it, so a small scroll up from the bottom is never undone.
    function onScroll() {
        if (!container.value) return;
        const movedDown = container.value.scrollTop > lastScrollTop;
        lastScrollTop = container.value.scrollTop;
        if (isPinning || !movedDown) return;
        if (distanceFromBottom(container.value) < BOTTOM_THRESHOLD_PX) follow();
    }

    /** Scrolling up stops following at once; scrolling down leaves it to onScroll. */
    function onUserScrollIntent(event: Event) {
        if (!container.value) return;

        let scrollsUp = false;
        if (event instanceof WheelEvent) {
            scrollsUp = event.deltaY < 0;
        } else if (typeof TouchEvent !== 'undefined' && event instanceof TouchEvent) {
            // Desktop Firefox has no TouchEvent, so a bare instanceof throws on every key press.
            const y = event.touches[0]?.clientY;
            // The finger moving down drags the content down: the view scrolls up.
            scrollsUp = y !== undefined && touchStartY !== null && y > touchStartY;
            if (y !== undefined) touchStartY = y;
        } else if (event instanceof KeyboardEvent) {
            if (event.target instanceof HTMLElement && event.target.closest('textarea, input, [contenteditable]')) return;
            scrollsUp = SCROLL_UP_KEYS.includes(event.key) || (event.key === ' ' && event.shiftKey);
        }

        if (scrollsUp) hold();
    }

    function onTouchStart(event: TouchEvent) {
        touchStartY = event.touches[0]?.clientY ?? null;
    }

    onMounted(() => {
        if (!container.value) return;

        container.value.addEventListener('scroll', onScroll, { passive: true });
        container.value.addEventListener('scrollend', endPinning, { passive: true });
        container.value.addEventListener('wheel', onUserScrollIntent, { passive: true });
        container.value.addEventListener('touchstart', onTouchStart, { passive: true });
        container.value.addEventListener('touchmove', onUserScrollIntent, { passive: true });
        window.addEventListener('keydown', onUserScrollIntent);

        // The reply growing (also when its markdown re-renders after the last
        // token), the composer growing or the window resizing all change how
        // much room the pinned turn needs and where the bottom is.
        resizeObserver = new ResizeObserver(() => {
            if (anchorIndex.value !== null) measureSpacer();
            scrollWhileStreaming();
        });
        resizeObserver.observe(container.value);
        if (content.value) resizeObserver.observe(content.value);
    });

    onUnmounted(() => {
        if (jumpFrame) cancelAnimationFrame(jumpFrame);
        if (pinTimeout) clearTimeout(pinTimeout);
        stopFollowing();
        resizeObserver?.disconnect();

        container.value?.removeEventListener('scroll', onScroll);
        container.value?.removeEventListener('scrollend', endPinning);
        container.value?.removeEventListener('wheel', onUserScrollIntent);
        container.value?.removeEventListener('touchstart', onTouchStart);
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
