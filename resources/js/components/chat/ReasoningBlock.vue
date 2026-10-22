<script setup lang="ts">
/**
 * The model's thinking above its answer: grey, smaller text in a block the
 * reader can fold away or open again at any time. It starts open so the
 * thinking can be followed as it streams. Plain text on purpose: thinking is
 * a rough draft, not a formatted answer. Live only; a reload drops it.
 */
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { ChevronDown } from 'lucide-vue-next';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';

defineProps<{
    text: string;
    /** The model is still thinking: no answer text yet. */
    thinking: boolean;
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
            <p
                dir="auto"
                class="text-muted-foreground border-border mt-1.5 max-h-80 overflow-y-auto border-s-2 ps-3 text-sm leading-relaxed whitespace-pre-wrap"
            >
                {{ text }}
            </p>
        </CollapsibleContent>
    </Collapsible>
</template>

<style scoped>
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
