<script setup lang="ts">
/**
 * A highlighted HTML install snippet with its copy button, as shown on the
 * Deploy page. With a `label` the button sits next to it; without one it sits
 * alone on the right. `wrap` lets long single-line code (the iframe) wrap.
 */
import DeployCopyButton from '@/dashboard/agents/components/deploy/DeployCopyButton.vue';
import { highlightCode } from '@/dashboard/agents/components/deploy/highlightCode';
import { computed } from 'vue';

const props = defineProps<{
    code: string;
    copied: boolean;
    label?: string;
    wrap?: boolean;
}>();

const emit = defineEmits<{
    copy: [];
}>();

const highlighted = computed(() => highlightCode(props.code, 'xml'));
</script>

<template>
    <div class="bg-muted space-y-3 rounded-lg p-4">
        <div v-if="label" class="flex items-center justify-between gap-2">
            <p class="text-muted-foreground text-sm">{{ label }}</p>
            <DeployCopyButton :copied="copied" class="shrink-0" @click="emit('copy')" />
        </div>
        <div v-else class="flex items-center justify-end">
            <DeployCopyButton :copied="copied" @click="emit('copy')" />
        </div>
        <div class="bg-background rounded border p-3" dir="ltr">
            <pre
                class="hljs overflow-x-auto bg-transparent p-0 text-xs"
                :class="{ 'whitespace-pre-wrap': wrap }"
            ><code class="language-xml" v-html="highlighted" /></pre>
        </div>
    </div>
</template>
