/**
 * Polls whether the agent's owner still has message capacity, so the hosted
 * chat can disable the composer once the plan limit is reached. A failed
 * check never blocks the visitor: it counts as "can send".
 */
import { fetchUsage } from '@/api/chat';
import { onMounted, onUnmounted, ref } from 'vue';

const CHECK_INTERVAL_MS = 60_000;

export function useSendCapacity() {
    const canSendMessages = ref(true);
    let timer: ReturnType<typeof setInterval> | null = null;

    async function check() {
        try {
            const usage = await fetchUsage();
            canSendMessages.value = usage.can_send_messages;
        } catch {
            canSendMessages.value = true;
        }
    }

    onMounted(() => {
        void check();
        timer = setInterval(check, CHECK_INTERVAL_MS);
    });

    onUnmounted(() => {
        if (timer) {
            clearInterval(timer);
            timer = null;
        }
    });

    return { canSendMessages };
}
