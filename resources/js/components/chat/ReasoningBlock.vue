<script setup lang="ts">
/**
 * The model's thinking above its answer: grey, smaller text in a block the
 * reader can fold away or open again at any time. It starts open so the
 * thinking can be followed as it streams. Models write their thinking in
 * Markdown (bold step titles, lists, code, maths), so it goes through the
 * answer's own renderer and sanitizer, kept small and grey. Live only; a
 * reload drops it.
 */
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { ChevronDown } from 'lucide-vue-next';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import AssistantMessageBody from './AssistantMessageBody.vue';

defineProps<{
    text: string;
    /** The model is still thinking: no answer text yet. */
    thinking: boolean;
    isRTL?: boolean;
}>();

const { t } = useI18n();
const open = ref(true);
</script>

<template>
    <Collapsible v-model:open="open" class="mb-2">
        <CollapsibleTrigger
            class="group text-muted-foreground hover:text-foreground flex items-center gap-1 text-xs font-medium"
            :aria-label="open ? t('reasoning.hide') : t('reasoning.show')"
        >
            <span :class="thinking ? 'reasoning-shimmer motion-reduce:animate-none' : ''">
                {{ thinking ? t('reasoning.thinking') : t('reasoning.done') }}
            </span>
            <ChevronDown class="h-3.5 w-3.5 transition-transform group-data-[state=open]:rotate-180 motion-reduce:transition-none" />
        </CollapsibleTrigger>
        <CollapsibleContent>
            <div dir="auto" class="reasoning-body border-border mt-1.5 max-h-80 overflow-y-auto border-s-2 ps-3">
                <AssistantMessageBody :content="text" :is-streaming="thinking" :isRTL="isRTL ?? false" />
            </div>
        </CollapsibleContent>
    </Collapsible>
</template>

<style scoped>
/* The thinking reads as secondary: smaller, grey, tighter than the answer.
   These win over the bubble's own `.prose` rules, which are less specific. */
.reasoning-body :deep(.prose) {
    font-size: 0.875rem;
    line-height: 1.5rem;
    color: var(--muted-foreground);
    --tw-prose-body: var(--muted-foreground);
    --tw-prose-bold: var(--muted-foreground);
    --tw-prose-headings: var(--muted-foreground);
    --tw-prose-bullets: var(--muted-foreground);
    --tw-prose-counters: var(--muted-foreground);
    --tw-prose-code: var(--muted-foreground);
}

.reasoning-body :deep(.prose :is(h1, h2, h3, h4, h5, h6)) {
    font-size: 0.875rem;
    margin-top: 0.75rem;
    margin-bottom: 0.25rem;
}

.reasoning-body :deep(.prose :is(strong, b)) {
    color: inherit;
}

/* The same moving sheen as the "Calling …" tool label. Theme variables hold
   full colour values, so they are used directly, never inside hsl(). */
.reasoning-shimmer {
    background-image: linear-gradient(90deg, var(--muted-foreground) 0%, var(--primary) 50%, var(--muted-foreground) 100%);
    background-size: 200% 100%;
    background-clip: text;
    -webkit-background-clip: text;
    color: transparent;
    -webkit-text-fill-color: transparent;
    animation: reasoning-shimmer 2.4s linear infinite;
}

@media (prefers-reduced-motion: reduce) {
    .reasoning-shimmer {
        animation: none;
        color: var(--muted-foreground);
        -webkit-text-fill-color: var(--muted-foreground);
    }
}

@keyframes reasoning-shimmer {
    0% {
        background-position: 200% 0;
    }
    100% {
        background-position: -200% 0;
    }
}
</style>
