<script setup lang="ts">
/**
 * The steps an agent took before its answer, as a chain of dots joined by a
 * line: each line it wrote before calling a tool ("Let me look up your event
 * types."), and each tool it called, with the tool's logo. A running call
 * shimmers. Foldable, like the reasoning block. Saved with the reply, so a
 * reload shows it again.
 */
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import type { MessageStep } from '@/types/chat';
import { ChevronDown, Plug, Webhook } from 'lucide-vue-next';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import AssistantMessageBody from './AssistantMessageBody.vue';

defineProps<{
    steps: MessageStep[];
    isRTL?: boolean;
}>();

const { t } = useI18n();
const open = ref(true);

function toolLabel(step: Extract<MessageStep, { kind: 'tool' }>): string {
    if (step.done) return t('steps.used', { label: step.call.label });

    return step.call.activity_label || t('messages.calling', { label: step.call.label });
}
</script>

<template>
    <Collapsible v-model:open="open" class="mb-3">
        <CollapsibleTrigger
            class="group text-muted-foreground hover:text-foreground flex items-center gap-1 text-xs font-medium"
            :aria-label="open ? t('steps.hide') : t('steps.show')"
        >
            {{ t('steps.count', { count: steps.length }, { plural: steps.length }) }}
            <ChevronDown class="h-3.5 w-3.5 transition-transform group-data-[state=open]:rotate-180 motion-reduce:transition-none" />
        </CollapsibleTrigger>
        <CollapsibleContent>
            <ol class="mt-2">
                <li v-for="(step, index) in steps" :key="index" class="relative flex gap-3 pb-2 last:pb-0">
                    <!-- The line runs from this marker's centre to the next one's. -->
                    <span v-if="index < steps.length - 1" class="step-rail absolute start-[7.5px] top-3 -bottom-3 w-px" aria-hidden="true" />
                    <span class="relative flex h-6 w-4 shrink-0 items-center justify-center" aria-hidden="true">
                        <span v-if="step.kind === 'text'" class="step-dot h-2 w-2 rounded-full" />
                        <span v-else class="step-icon flex h-4 w-4 items-center justify-center overflow-hidden rounded-full">
                            <img v-if="step.call.icon_url" :src="step.call.icon_url" alt="" class="h-full w-full object-contain p-px" />
                            <Webhook v-else-if="step.call.kind === 'function'" class="text-muted-foreground h-3 w-3" />
                            <Plug v-else class="text-muted-foreground h-3 w-3" />
                        </span>
                    </span>
                    <div v-if="step.kind === 'text'" dir="auto" class="step-body min-w-0 flex-1">
                        <AssistantMessageBody :content="step.text" :is-streaming="false" :isRTL="isRTL ?? false" />
                    </div>
                    <span
                        v-else
                        class="min-w-0 flex-1 truncate text-sm leading-6"
                        :class="step.done ? 'text-muted-foreground' : 'step-shimmer italic motion-reduce:animate-none'"
                    >
                        {{ toolLabel(step) }}
                    </span>
                </li>
            </ol>
        </CollapsibleContent>
    </Collapsible>
</template>

<style scoped>
/* Theme variables hold full colour values, so they are used directly, never
   inside hsl(). */
.step-rail {
    background-color: var(--border);
}

/* Solid, so the line does not show through the dot. */
.step-dot {
    background-color: color-mix(in srgb, var(--muted-foreground) 70%, var(--background));
}

.step-icon {
    background-color: var(--background);
    box-shadow: 0 0 0 1px var(--border);
}

/* What the agent said reads as secondary: smaller and grey, like its thinking. */
.step-body :deep(.prose) {
    font-size: 0.875rem;
    line-height: 1.5rem;
    color: var(--muted-foreground);
    --tw-prose-body: var(--muted-foreground);
    --tw-prose-bold: var(--muted-foreground);
}

.step-body :deep(.prose :is(strong, b)) {
    color: inherit;
}

.step-shimmer {
    background-image: linear-gradient(90deg, var(--muted-foreground) 0%, var(--primary) 50%, var(--muted-foreground) 100%);
    background-size: 200% 100%;
    background-clip: text;
    -webkit-background-clip: text;
    color: transparent;
    -webkit-text-fill-color: transparent;
    animation: step-shimmer 2.4s linear infinite;
}

@media (prefers-reduced-motion: reduce) {
    .step-shimmer {
        animation: none;
        color: var(--muted-foreground);
        -webkit-text-fill-color: var(--muted-foreground);
    }
}

@keyframes step-shimmer {
    0% {
        background-position: 200% 0;
    }
    100% {
        background-position: -200% 0;
    }
}
</style>
