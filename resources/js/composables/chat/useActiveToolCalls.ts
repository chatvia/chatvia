/**
 * Tool calls that are running during the current streamed turn, keyed by
 * `tool_call_id`. Fed by the stream's `tool` start/end events (synchronous and
 * in order with the text) and read by the branded "Connecting to …" indicator.
 */
import type { ActiveToolCall } from '@/types/chat';
import type { StreamToolData } from '@/types/stream';
import { computed, ref } from 'vue';

export function useActiveToolCalls() {
    const calls = ref(new Map<string, ActiveToolCall>());

    const hasActiveCalls = computed(() => calls.value.size > 0);

    // Oldest first, so the indicator stacks icons in arrival order and labels the newest.
    const activeCalls = computed(() => Array.from(calls.value.values()).sort((a, b) => a.started_at - b.started_at));

    function start(data: StreamToolData): ActiveToolCall {
        const call: ActiveToolCall = {
            tool_call_id: data.tool_call_id,
            name: data.name ?? '',
            kind: data.kind ?? 'function',
            label: data.label ?? data.name ?? '',
            activity_label: data.activity_label ?? null,
            icon_url: data.icon_url ?? null,
            brand_color: data.brand_color ?? null,
            started_at: Date.now(),
        };
        calls.value.set(call.tool_call_id, call);

        return call;
    }

    function end(toolCallId: string) {
        calls.value.delete(toolCallId);
    }

    function clear() {
        calls.value.clear();
    }

    return { hasActiveCalls, activeCalls, start, end, clear };
}
