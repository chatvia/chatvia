<script setup lang="ts">
/**
 * Shared body renderer for assistant messages. Mounted by both the live
 * AssistantChatBubble and the chat-log MessageCard so chart artifacts and
 * the markdown pipeline live in one place.
 *
 * Owns: the marked instance + plugin config, streaming-aware debounced
 * render, code-block copy buttons, and the artifact-kind → component map.
 *
 * Does NOT own: feedback/copy buttons on the message itself, sources,
 * tool-call indicators, regenerate/edit affordances, avatars, bubble
 * styling. Those stay in the wrappers.
 */
import type { ToolArtifact } from '@/types/stream';
import DOMPurify from 'dompurify';
import hljs from 'highlight.js';
import { Marked } from 'marked';
import markedAlert from 'marked-alert';
import markedCodePreview from 'marked-code-preview';
import { markedHighlight } from 'marked-highlight';
import markedKatex from 'marked-katex-extension';
import { type Component, computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import EChartBlock from './EChartBlock.vue';

const props = withDefaults(
    defineProps<{
        content: string;
        artifacts?: ToolArtifact[];
        isStreaming?: boolean;
        isRTL?: boolean;
    }>(),
    {
        artifacts: () => [],
        isStreaming: false,
        isRTL: false,
    },
);

/**
 * Kind → renderer map. Adding a new artifact type means adding one entry
 * here, not touching AssistantChatBubble or MessageCard. `toProps` lets
 * each renderer accept its own prop shape (EChartBlock wants `option`,
 * a future table block might want `rows`/`columns`, etc.).
 */
type ArtifactRenderer = {
    component: Component;
    toProps: (data: unknown) => Record<string, unknown>;
};

const ARTIFACT_RENDERERS: Record<string, ArtifactRenderer> = {
    echarts_option: {
        component: EChartBlock,
        toProps: (data) => ({ option: data }),
    },
};

const renderableArtifacts = computed(() =>
    (props.artifacts ?? []).filter((a): a is ToolArtifact => a.kind in ARTIFACT_RENDERERS && a.data !== null && typeof a.data === 'object'),
);

const marked = new Marked(
    markedHighlight({
        emptyLangClass: 'hljs',
        langPrefix: 'hljs language-',
        highlight(code, lang) {
            const language = hljs.getLanguage(lang) ? lang : 'plaintext';
            return hljs.highlight(code, { language }).value;
        },
    }),
);

marked
    .use({ gfm: true })
    .use(
        markedKatex({
            throwOnError: false,
            errorColor: '#cc0000',
            strict: false,
        }),
    )
    .use(markedCodePreview())
    .use(markedAlert());

/**
 * When the response carries a rendered artifact (e.g. an ECharts chart),
 * the model often emits a markdown image placeholder like
 * `![Bar chart comparing 1000 USD to 6401 DKK]` — it knows a visual is
 * being shown but has no URL to point at, so the `![alt]` lands without a
 * trailing `(url)` and marked renders it as raw text. Strip those
 * orphan placeholders so users don't see leftover bracketed captions
 * above the actual chart.
 *
 * Only runs when there's at least one artifact — without that signal we
 * can't be sure the `![...]` is a placeholder vs. the user's own copy.
 */
const ORPHAN_IMAGE_PLACEHOLDER = /!\[[^\]]*\](?!\()/g;

function stripOrphanImagePlaceholders(content: string): string {
    return content.replace(ORPHAN_IMAGE_PLACEHOLDER, '').replace(/\n{3,}/g, '\n\n');
}

function formatMessage(content: string): string {
    const cleaned = renderableArtifacts.value.length > 0 ? stripOrphanImagePlaceholders(content) : content;
    const result = marked.parse(cleaned);
    const html = typeof result === 'string' ? result : '';

    // Assistant/visitor content (and prompt-injected model output) is rendered
    // via v-html in both the live widget AND the privileged admin chat-log /
    // handoff UIs. Sanitize so injected <img onerror=…>, <script>, javascript:
    // hrefs, etc. can never execute. marked v15 does NOT sanitize.
    return DOMPurify.sanitize(html, { ADD_ATTR: ['target'] });
}

// While streaming, debounce reparsing on a trailing edge — markdown +
// highlight.js is expensive on long responses. 60 ms is under the user
// perception threshold but caps CPU. When the stream ends or the
// component renders a stored message, flush immediately.
const renderedHtml = ref('');
let renderDebounce: ReturnType<typeof setTimeout> | null = null;

function flushRender() {
    if (renderDebounce) {
        clearTimeout(renderDebounce);
        renderDebounce = null;
    }
    renderedHtml.value = formatMessage(props.content ?? '');
}

watch(
    () => props.content,
    () => {
        if (!props.isStreaming || renderedHtml.value === '') {
            flushRender();
            return;
        }
        if (renderDebounce) return;
        renderDebounce = setTimeout(() => {
            renderDebounce = null;
            renderedHtml.value = formatMessage(props.content ?? '');
        }, 60);
    },
    { immediate: true },
);

watch(
    () => props.isStreaming,
    (streaming) => {
        if (!streaming) flushRender();
    },
);

// Artifacts can arrive AFTER some text has already streamed in (the tool
// result lands a few tokens late). Re-flush when an artifact appears so
// any orphan `![...]` placeholders the model wrote earlier disappear.
watch(
    () => renderableArtifacts.value.length,
    (count, prev) => {
        if (count !== prev) flushRender();
    },
);

onUnmounted(() => {
    if (renderDebounce) clearTimeout(renderDebounce);
});

const bodyRef = ref<HTMLElement>();

function setupCodeBlockCopyButtons() {
    if (!bodyRef.value) return;

    const codeBlocks = bodyRef.value.querySelectorAll('pre');
    codeBlocks.forEach((pre) => {
        if (pre.querySelector('.code-copy-button')) return;

        const copyButton = document.createElement('button');
        copyButton.className = 'code-copy-button';
        copyButton.setAttribute('aria-label', 'Copy code to clipboard');

        const copyIcon = document.createElement('span');
        copyIcon.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" /></svg>`;
        copyButton.appendChild(copyIcon);

        copyButton.addEventListener('click', async (e) => {
            e.preventDefault();
            e.stopPropagation();

            const code = pre.querySelector('code');
            if (code) {
                try {
                    await navigator.clipboard.writeText(code.textContent || '');
                    copyButton.classList.add('copied');
                    copyIcon.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>`;

                    setTimeout(() => {
                        copyButton.classList.remove('copied');
                        copyIcon.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" /></svg>`;
                    }, 2000);
                } catch (err) {
                    console.error('Failed to copy code:', err);
                }
            }
        });

        pre.style.position = 'relative';
        pre.appendChild(copyButton);
    });
}

watch(
    () => props.content,
    () => {
        if (props.content) {
            nextTick(() => setupCodeBlockCopyButtons());
        }
    },
    { immediate: true },
);

onMounted(() => {
    if (props.content) {
        nextTick(() => setupCodeBlockCopyButtons());
    }
});
</script>

<template>
    <div class="assistant-message-body prose prose-sm dark:prose-invert w-full max-w-none" :data-rtl="isRTL">
        <!-- Artifacts (charts today; tables/maps/etc. tomorrow) render
             above the markdown so the visualization comes first and the
             model's caption / commentary follows. -->
        <component
            :is="ARTIFACT_RENDERERS[artifact.kind].component"
            v-for="(artifact, i) in renderableArtifacts"
            :key="`artifact-${i}`"
            v-bind="ARTIFACT_RENDERERS[artifact.kind].toProps(artifact.data)"
        />
        <div
            v-if="content"
            id="chat-ai-responses"
            ref="bodyRef"
            class="streaming-content"
            :class="{ 'is-streaming': isStreaming }"
            v-html="renderedHtml"
        />
    </div>
</template>
