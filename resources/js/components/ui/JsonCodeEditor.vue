<script setup lang="ts">
import hljs from 'highlight.js/lib/core';
import json from 'highlight.js/lib/languages/json';
import { computed, ref, watch } from 'vue';

hljs.registerLanguage('json', json);

const props = withDefaults(
    defineProps<{
        modelValue: string;
        rows?: number;
        spellcheck?: boolean;
        ariaLabel?: string;
    }>(),
    { rows: 10, spellcheck: false, ariaLabel: 'JSON editor' },
);

const emit = defineEmits<{ 'update:modelValue': [value: string] }>();

const textareaEl = ref<HTMLTextAreaElement | null>(null);
const preEl = ref<HTMLPreElement | null>(null);

const highlighted = computed(() => {
    const value = props.modelValue ?? '';
    try {
        return hljs.highlight(value, { language: 'json', ignoreIllegals: true }).value;
    } catch {
        return escapeHtml(value);
    }
});

function escapeHtml(s: string): string {
    return s
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;');
}

function onInput(event: Event): void {
    emit('update:modelValue', (event.target as HTMLTextAreaElement).value);
}

function onScroll(): void {
    if (textareaEl.value && preEl.value) {
        preEl.value.scrollTop = textareaEl.value.scrollTop;
        preEl.value.scrollLeft = textareaEl.value.scrollLeft;
    }
}

function onKeydown(event: KeyboardEvent): void {
    // Tab inserts two spaces instead of moving focus — standard code-editor UX.
    if (event.key !== 'Tab') return;

    const ta = textareaEl.value;
    if (!ta) return;

    event.preventDefault();
    const start = ta.selectionStart;
    const end = ta.selectionEnd;
    const indent = '  ';
    const next = ta.value.slice(0, start) + indent + ta.value.slice(end);
    emit('update:modelValue', next);

    // Restore caret after Vue rerenders.
    requestAnimationFrame(() => {
        if (textareaEl.value) {
            textareaEl.value.selectionStart = textareaEl.value.selectionEnd = start + indent.length;
        }
    });
}

watch(
    () => props.modelValue,
    () => {
        // After external updates (e.g. "Insert template" button) ensure scroll
        // positions stay aligned.
        requestAnimationFrame(onScroll);
    },
);
</script>

<template>
    <div class="json-code-editor relative font-mono text-xs">
        <pre
            ref="preEl"
            aria-hidden="true"
            class="hljs pointer-events-none m-0 overflow-auto rounded-md border bg-background p-3"
            :style="{ minHeight: `${rows * 1.5}rem`, maxHeight: `${rows * 1.5}rem` }"
        ><code class="language-json" v-html="highlighted + '\n'"></code></pre>
        <textarea
            ref="textareaEl"
            :value="modelValue"
            :spellcheck="spellcheck"
            :aria-label="ariaLabel"
            wrap="off"
            autocomplete="off"
            autocorrect="off"
            autocapitalize="off"
            class="absolute inset-0 m-0 resize-none overflow-auto rounded-md border border-transparent bg-transparent p-3 outline-none focus-visible:border-ring focus-visible:ring-2 focus-visible:ring-ring/30"
            @input="onInput"
            @scroll="onScroll"
            @keydown="onKeydown"
        />
    </div>
</template>

<style scoped>
.json-code-editor pre,
.json-code-editor textarea {
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, 'Liberation Mono', 'Courier New', monospace;
    font-size: 0.75rem;
    line-height: 1.5;
    tab-size: 2;
    white-space: pre;
    word-spacing: normal;
    word-break: normal;
    word-wrap: normal;
}

/* Hide the text in the textarea so only the highlighted pre is visible.
   The caret is still rendered because of the explicit caret-color. */
.json-code-editor textarea {
    color: transparent;
    caret-color: var(--foreground, hsl(0 0% 10%));
}

/* Highlight.js token colors — VS Code "dark+" inspired, matching the
   theme used in the chat assistant bubble. */
:deep(.hljs) {
    color: hsl(var(--foreground));
    background: transparent;
}

:deep(.hljs-attr) {
    color: #9cdcfe;
}

:deep(.hljs-string) {
    color: #ce9178;
}

:deep(.hljs-number),
:deep(.hljs-literal) {
    color: #b5cea8;
}

:deep(.hljs-punctuation) {
    color: hsl(var(--muted-foreground));
}

:deep(.hljs-keyword) {
    color: #569cd6;
}

:deep(.hljs-comment) {
    color: #6a9955;
    font-style: italic;
}

/* Make the light theme readable too. */
:global(html:not(.dark)) .json-code-editor :deep(.hljs-attr) {
    color: #0451a5;
}
:global(html:not(.dark)) .json-code-editor :deep(.hljs-string) {
    color: #a31515;
}
:global(html:not(.dark)) .json-code-editor :deep(.hljs-number),
:global(html:not(.dark)) .json-code-editor :deep(.hljs-literal) {
    color: #098658;
}
:global(html:not(.dark)) .json-code-editor :deep(.hljs-keyword) {
    color: #0000ff;
}
:global(html:not(.dark)) .json-code-editor :deep(.hljs-comment) {
    color: #008000;
}
</style>
