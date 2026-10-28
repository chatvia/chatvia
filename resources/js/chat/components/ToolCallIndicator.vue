<script setup lang="ts">
/**
 * "Connecting to <service>…" indicator shown while an assistant turn runs MCP or
 * webhook tools: the branded icons of the calls in flight, overlapped like a
 * stack, plus a shimmering label for the newest call.
 */
import type { ActiveToolCall } from '@/shared/types/chat';
import { Plug, Webhook } from 'lucide-vue-next';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps<{
    /** Oldest first; the last entry is the call that started most recently. */
    calls: ActiveToolCall[];
    labelClass?: string;
}>();

const { t } = useI18n();

// Cap the stack so the row stays tidy on narrow screens; a count badge covers the rest.
const STACK_LIMIT = 4;

const stackedCalls = computed(() => props.calls.slice(-STACK_LIMIT));
const overflowCount = computed(() => Math.max(0, props.calls.length - STACK_LIMIT));

const label = computed(() => {
    const current = props.calls[props.calls.length - 1];
    if (!current) return t('messages.calling_external');

    // Prefer the connector's own activity phrase ("Preparing your form…"), as the widget does.
    return current.activity_label || t('messages.calling', { label: current.label });
});
</script>

<template>
    <div class="flex items-center">
        <div
            v-for="(call, i) in stackedCalls"
            :key="call.tool_call_id"
            class="external-call-icon"
            :style="{ marginInlineStart: i === 0 ? '0' : '-6px', zIndex: stackedCalls.length - i }"
            :title="call.label"
        >
            <img v-if="call.icon_url" :src="call.icon_url" :alt="call.label" class="h-full w-full object-contain p-0.5" />
            <Webhook v-else-if="call.kind === 'function'" class="text-muted-foreground h-full w-full p-1" />
            <Plug v-else class="text-muted-foreground h-full w-full p-1" />
        </div>
        <span v-if="overflowCount > 0" class="text-muted-foreground ms-1 text-xs">+{{ overflowCount }}</span>
    </div>
    <span class="external-call-shimmer italic" :class="labelClass">{{ label }}</span>
</template>

<style scoped>
/* Theme variables hold full colour functions (e.g. `--primary: #404040`), so
   they are used directly; wrapping them in hsl() would be invalid. */
.external-call-shimmer {
    background-image: linear-gradient(90deg, var(--muted-foreground) 0%, var(--primary) 50%, var(--muted-foreground) 100%);
    background-size: 200% 100%;
    background-clip: text;
    -webkit-background-clip: text;
    color: transparent;
    -webkit-text-fill-color: transparent;
    animation: external-call-shimmer 2.4s linear infinite;
}

.external-call-icon {
    position: relative;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 1.25rem;
    height: 1.25rem;
    border-radius: 9999px;
    background-color: var(--background);
    box-shadow: 0 0 0 1px var(--border);
    overflow: hidden;
}

@keyframes external-call-shimmer {
    0% {
        background-position: 200% 0;
    }
    100% {
        background-position: -200% 0;
    }
}
</style>
