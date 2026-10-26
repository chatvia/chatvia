<script setup lang="ts">
/**
 * Shared body renderer for assistant messages. Mounted by both the live
 * AssistantChatBubble and the chat-log MessageCard so chart artifacts and
 * the markdown pipeline live in one place.
 *
 * Owns: the marked instance + plugin config, streaming-aware debounced
 * render, code-block copy buttons, the artifact-kind → component map, and,
 * when `cite` is on, the `[n]` citation chips and the source card they open,
 * and the gallery an image opens in when clicked.
 *
 * Does NOT own: feedback/copy buttons on the message itself, sources,
 * tool-call indicators, regenerate/edit affordances, avatars, bubble
 * styling. Those stay in the wrappers.
 */
import { citedSources, withCitationChips } from '@/lib/citations';
import { sanitizeUntrustedHtml } from '@/lib/sanitizeHtml';
import type { MessageSource } from '@/types/chat';
import type { ToolArtifact } from '@/types/stream';
import { useClipboard } from '@vueuse/core';
import hljs from 'highlight.js';
import { Marked } from 'marked';
import markedAlert from 'marked-alert';
import markedCodePreview from 'marked-code-preview';
import { markedHighlight } from 'marked-highlight';
import markedKatex from 'marked-katex-extension';
import { type Component, computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import CitationCard from './CitationCard.vue';
import EChartBlock from './EChartBlock.vue';
import GeneratedAudioBlock from './GeneratedAudioBlock.vue';
import GeneratedImageBlock from './GeneratedImageBlock.vue';
import ImageLightbox, { type LightboxImage } from './ImageLightbox.vue';
import InlineCalendarBlock from './InlineCalendarBlock.vue';
import InlineFormBlock from './InlineFormBlock.vue';

const props = withDefaults(
    defineProps<{
        content: string;
        artifacts?: ToolArtifact[];
        isStreaming?: boolean;
        isRTL?: boolean;
        // External id of the message that owns these artifacts — passed to the
        // inline-form renderer so a submit can stamp the read-only state back
        // onto this message for reload.
        messageId?: string;
        // The answer's sources and whether its `[n]` markers become citation chips.
        sources?: MessageSource[];
        cite?: boolean;
    }>(),
    {
        artifacts: () => [],
        sources: () => [],
        cite: false,
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
    form: {
        component: InlineFormBlock,
        toProps: (data) => ({ form: data }),
    },
    calendar: {
        component: InlineCalendarBlock,
        toProps: (data) => ({ calendar: data }),
    },
    image: {
        component: GeneratedImageBlock,
        toProps: (data) => ({ media: data }),
    },
    audio: {
        component: GeneratedAudioBlock,
        toProps: (data) => ({ media: data }),
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

const cited = computed(() => citedSources(props.content ?? '', props.sources ?? []));
const citesSources = computed(() => props.cite && (props.sources ?? []).some((source) => (source.blocks ?? []).length > 0));

function formatMessage(content: string): string {
    const withImages = renderableArtifacts.value.length > 0 ? stripOrphanImagePlaceholders(content) : content;
    const cleaned = citesSources.value ? withCitationChips(withImages, cited.value, props.isStreaming) : withImages;
    const result = marked.parse(cleaned);
    const html = typeof result === 'string' ? result : '';

    // Assistant/visitor content (and prompt-injected model output) is rendered
    // via v-html in both the live widget AND the privileged admin chat-log /
    // handoff UIs. Sanitize so injected <img onerror=…>, <script>, javascript:
    // hrefs and self-loading media can never run or leak the conversation.
    // marked v15 does NOT sanitize.
    return sanitizeUntrustedHtml(html);
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

// The sources usually arrive after the text: at the end of a stream, or a
// moment after a reloaded conversation shows its messages. Re-flush when
// they (or the agent's citing) change, so the markers become chips.
watch(
    () => [props.cite, (props.sources ?? []).map((source) => (source.blocks ?? []).join(',')).join('|')],
    () => flushRender(),
);

onUnmounted(() => {
    if (renderDebounce) clearTimeout(renderDebounce);
});

const bodyRef = ref<HTMLElement>();

// ---- Citation cards ---------------------------------------------------------
// A chip opens its source's card on hover, focus or tap; the card stays while
// the pointer moves onto it, and closes shortly after it leaves both.

const openCitation = ref<{ source: MessageSource; number: number; anchor: DOMRect } | null>(null);
let closeTimer: ReturnType<typeof setTimeout> | null = null;

function citeChip(event: Event): HTMLElement | null {
    const target = event.target as HTMLElement | null;
    return target?.closest?.('sup.cite') ?? null;
}

function showCitation(chip: HTMLElement) {
    const number = Number(chip.dataset.cite);
    const entry = cited.value.ordered.find((item) => item.number === number);
    if (!entry) return;

    keepCitation();
    openCitation.value = { source: entry.source, number, anchor: chip.getBoundingClientRect() };
}

function keepCitation() {
    if (closeTimer) {
        clearTimeout(closeTimer);
        closeTimer = null;
    }
}

function scheduleCloseCitation() {
    keepCitation();
    closeTimer = setTimeout(() => (openCitation.value = null), 200);
}

function closeCitation() {
    keepCitation();
    openCitation.value = null;
}

function onCiteHover(event: Event) {
    const chip = citeChip(event);
    if (chip) showCitation(chip);
}

function onCiteLeave(event: Event) {
    if (citeChip(event)) scheduleCloseCitation();
}

function onCiteClick(event: Event) {
    const chip = citeChip(event);
    if (!chip) return;
    event.preventDefault();
    showCitation(chip);
}

// ---- Image gallery ----------------------------------------------------------
// An image in the answer opens full size, with the answer's other images one
// arrow away. The list is read when it opens, so a re-render while streaming
// does not move the gallery.

const lightboxImages = ref<LightboxImage[]>([]);
const lightboxIndex = ref<number | null>(null);

function answerImages(): HTMLImageElement[] {
    return Array.from(bodyRef.value?.querySelectorAll('img') ?? []);
}

function openImage(event: Event): boolean {
    const image = (event.target as HTMLElement | null)?.closest?.('img');
    if (!image || !bodyRef.value?.contains(image)) return false;

    event.preventDefault();
    const images = answerImages();
    lightboxImages.value = images.map((img) => ({ src: img.currentSrc || img.src, alt: img.alt }));
    lightboxIndex.value = Math.max(images.indexOf(image), 0);

    return true;
}

/** Lets keyboard users open an image: each one becomes a focusable button. */
function markZoomableImages() {
    for (const image of answerImages()) {
        if (image.dataset.zoomable) continue;
        image.dataset.zoomable = 'true';
        image.tabIndex = 0;
        image.setAttribute('role', 'button');
        image.setAttribute('aria-label', image.alt ? t('chat.image_viewer.view_named', { name: image.alt }) : t('chat.image_viewer.view'));
    }
}

function onBodyClick(event: Event) {
    if (!openImage(event)) onCiteClick(event);
}

// A card left open would point at a chip that has moved away.
const closeOnScroll = () => closeCitation();
onMounted(() => window.addEventListener('scroll', closeOnScroll, true));
onUnmounted(() => {
    window.removeEventListener('scroll', closeOnScroll, true);
    keepCitation();
});

/**
 * Streaming fade, as in ChatGPT: each piece of text that arrives fades in on
 * its own. The body is re-rendered from markdown as it streams, which would
 * restart or cut short every fade, so the arrival time of each stretch of
 * text is remembered by its position, and after every render the stretches
 * still fading are wrapped again with a negative delay that resumes their
 * fade where it was.
 */
const STREAM_FADE_MS = 500;

interface FadingText {
    start: number;
    end: number;
    arrivedAt: number;
}

let fadingText: FadingText[] = [];
let renderedLength = 0;
let hasStreamed = false;

/** The body's text nodes in reading order, leaving out formulas (KaTeX lays out its own spans). */
function textNodes(root: HTMLElement): Text[] {
    const walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT, {
        acceptNode: (node) => (node.parentElement?.closest('.katex') ? NodeFilter.FILTER_REJECT : NodeFilter.FILTER_ACCEPT),
    });
    const nodes: Text[] = [];
    while (walker.nextNode()) nodes.push(walker.currentNode as Text);

    return nodes;
}

function wrapFading(text: Text, delayMs: number) {
    const span = document.createElement('span');
    span.className = 'stream-fade';
    span.style.animationDelay = `${-delayMs}ms`;
    text.replaceWith(span);
    span.appendChild(text);
}

function applyStreamFade() {
    const root = bodyRef.value;
    if (!root || !hasStreamed) return;

    const nodes = textNodes(root);
    const length = nodes.reduce((sum, node) => sum + node.data.length, 0);
    const now = performance.now();

    // Shorter than before: an output guardrail replaced the answer.
    if (length < renderedLength) fadingText = [];
    if (length > renderedLength) fadingText.push({ start: renderedLength, end: length, arrivedAt: now });
    renderedLength = length;

    fadingText = fadingText.filter((stretch) => now - stretch.arrivedAt < STREAM_FADE_MS);
    if (fadingText.length === 0) return;

    let offset = 0;
    for (const node of nodes) {
        const nodeStart = offset;
        const nodeEnd = offset + node.data.length;
        offset = nodeEnd;
        if (node.data.trim() === '') continue;

        // From the last stretch back, so the start of the node stays where it is.
        for (let i = fadingText.length - 1; i >= 0; i--) {
            const stretch = fadingText[i];
            const from = Math.max(stretch.start, nodeStart) - nodeStart;
            const to = Math.min(stretch.end, nodeEnd) - nodeStart;
            if (from >= to) continue;

            const fading = from > 0 ? node.splitText(from) : node;
            if (to - from < fading.data.length) fading.splitText(to - from);
            wrapFading(fading, now - stretch.arrivedAt);
        }
    }
}

watch(
    () => props.isStreaming,
    (streaming) => {
        if (streaming) hasStreamed = true;
    },
    { immediate: true },
);

const { t } = useI18n();

// Runs after v-html replaced the body, so it works on the fresh nodes.
watch(
    renderedHtml,
    () => {
        applyStreamFade();
        markZoomableImages();
    },
    { flush: 'post' },
);
const { copy } = useClipboard({ legacy: true });

const COPY_ICON = `<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" /></svg>`;
const COPIED_ICON = `<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>`;
const COPIED_FEEDBACK_MS = 2000;

/** Adds a hover "copy" button to every rendered code block (once per block). */
function setupCodeBlockCopyButtons() {
    if (!bodyRef.value) return;

    const codeBlocks = bodyRef.value.querySelectorAll('pre');
    codeBlocks.forEach((pre) => {
        if (pre.querySelector('.code-copy-button')) return;

        const copyButton = document.createElement('button');
        copyButton.className = 'code-copy-button';
        copyButton.setAttribute('aria-label', t('chat.copy_code'));

        const copyIcon = document.createElement('span');
        copyIcon.innerHTML = COPY_ICON;
        copyButton.appendChild(copyIcon);

        copyButton.addEventListener('click', async (e) => {
            e.preventDefault();
            e.stopPropagation();

            const code = pre.querySelector('code');
            if (!code) return;

            await copy(code.textContent || '');
            copyButton.classList.add('copied');
            copyIcon.innerHTML = COPIED_ICON;

            setTimeout(() => {
                copyButton.classList.remove('copied');
                copyIcon.innerHTML = COPY_ICON;
            }, COPIED_FEEDBACK_MS);
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
        nextTick(() => {
            setupCodeBlockCopyButtons();
            markZoomableImages();
        });
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
            :message-id="artifact.kind === 'form' || artifact.kind === 'calendar' ? messageId : undefined"
        />
        <div
            v-if="content"
            id="chat-ai-responses"
            ref="bodyRef"
            v-html="renderedHtml"
            @mouseover="onCiteHover"
            @mouseout="onCiteLeave"
            @focusin="onCiteHover"
            @focusout="onCiteLeave"
            @click="onBodyClick"
            @keydown.enter="onBodyClick"
            @keydown.space="openImage"
            @keydown.esc="closeCitation"
        />
        <CitationCard
            v-if="openCitation"
            :source="openCitation.source"
            :number="openCitation.number"
            :anchor="openCitation.anchor"
            @enter="keepCitation"
            @leave="scheduleCloseCitation"
        />
        <ImageLightbox v-model:index="lightboxIndex" :images="lightboxImages" :is-r-t-l="isRTL" />
    </div>
</template>

<style scoped>
/* Citation chips: a small number after the sentence it backs. */
:deep(sup.cite) {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 1.15rem;
    height: 1.15rem;
    margin-inline-start: 0.2rem;
    padding: 0 0.3rem;
    border-radius: 9999px;
    background-color: var(--muted);
    color: var(--muted-foreground);
    font-size: 0.68rem;
    font-weight: 600;
    line-height: 1;
    vertical-align: super;
    cursor: pointer;
    transition: background-color 120ms;
}

:deep(sup.cite:hover),
:deep(sup.cite:focus-visible) {
    background-color: var(--accent);
    color: var(--accent-foreground);
    outline: none;
}

/* Answer images open in the gallery. */
:deep(img[data-zoomable]) {
    cursor: zoom-in;
    border-radius: 0.5rem;
    transition: opacity 120ms;
}

:deep(img[data-zoomable]:hover) {
    opacity: 0.9;
}

:deep(img[data-zoomable]:focus-visible) {
    outline: 2px solid var(--ring);
    outline-offset: 2px;
}

/* Streaming reveal: each newly arrived stretch of text fades in (see
   applyStreamFade). The spans are added to v-html content, which carries no
   scope attribute, hence :deep. */
:deep(.stream-fade) {
    animation: stream-fade-in 500ms ease-out both;
}

@keyframes stream-fade-in {
    from {
        opacity: 0;
    }
    to {
        opacity: 1;
    }
}

@media (prefers-reduced-motion: reduce) {
    :deep(.stream-fade) {
        animation: none;
    }
}
</style>
