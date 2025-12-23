<script setup lang="ts">
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
    AlertDialogTrigger,
} from '@/components/ui/alert-dialog';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import type { AIModel, Message } from '@/types/chat';
import { usePage } from '@inertiajs/vue3';
import axios from 'axios';
import hljs from 'highlight.js';
import { Check, Copy, Download, FileText, MessageSquare, RefreshCw, ThumbsDown, ThumbsUp, Trash2 } from 'lucide-vue-next';
import { Marked } from 'marked';
import markedAlert from 'marked-alert';
import markedCodePreview from 'marked-code-preview';
import { markedHighlight } from 'marked-highlight';
import markedKatex from 'marked-katex-extension';
import { computed, nextTick, onMounted, ref, watch } from 'vue';
import SourcesDialog from './SourcesDialog.vue';
import WaitingIndicator from './WaitingIndicator.vue';

const props = defineProps<{
    message: Message;
    userMessageId?: string | number;
    isThinking?: boolean;
    isStreaming?: boolean;
    hideFeedback?: boolean;
    models?: AIModel[];
    defaultModel?: AIModel | null;
    sources?: any[];
    conversationId?: string;
    avatar?: string | null;
    isRTL?: boolean;
}>();

const emit = defineEmits<{
    messageDeleted: [id: string | number];
    feedbackProvided: [id: string | number, type: 'thumbs-up' | 'thumbs-down'];
    regenerate: [userMessageId: string | number, assistantMessageId: string | number, model: AIModel];
}>();

// Computed for checking if we have multiple models
const hasMultipleModels = computed(() => {
    return props.models && props.models.length > 1;
});

const isRegenerating = ref(false);

// Regenerate with a specific model
async function regenerateWithModel(model: AIModel) {
    if (props.message.id === undefined || props.userMessageId === undefined || !props.conversationId) return;

    isRegenerating.value = true;
    emit('regenerate', props.userMessageId, props.message.id, model);
}

// Regenerate with default/current model
function regenerate() {
    const model = props.models?.[0] || props.defaultModel;
    if (model) {
        regenerateWithModel(model);
    }
}

const page = usePage();
const modelUsed = computed(() => {
    const models = props.models;
    const defaultModel = props.defaultModel;

    // Handle case where models might not be loaded yet
    if (!models || !Array.isArray(models)) {
        return defaultModel || null;
    }

    return models.find((m) => m.id === props.message.model) || defaultModel;
});

const messageContentRef = ref<HTMLElement>();
const isCopied = ref(false);
const feedback = ref<'thumbs-up' | 'thumbs-down' | null>(null);

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

function formatMessage(content: string): string {
    const result = marked.parse(content);
    return typeof result === 'string' ? result : '';
}

async function copyToClipboard() {
    try {
        await navigator.clipboard.writeText(props.message.content);
        isCopied.value = true;
        setTimeout(() => {
            isCopied.value = false;
        }, 2000);
    } catch (err) {
        console.error('Failed to copy text:', err);
    }
}

async function provideFeedback(type: 'thumbs-up' | 'thumbs-down') {
    if (feedback.value === type) {
        feedback.value = null;
    } else {
        feedback.value = type;
    }

    if (props.message.id !== undefined && props.conversationId) {
        try {
            const feedbackValue = type === 'thumbs-up';

            await axios.patch(route('chat.messages.feedback'), {
                conversation_id: props.conversationId,
                message_id: props.message.id,
                feedback: feedbackValue,
            });

            console.log('Feedback sent successfully:', props.message.id, type);
            emit('feedbackProvided', props.message.id, type);
        } catch (error) {
            console.error('Failed to send feedback:', error);
            // Reset feedback state on error
            feedback.value = null;
        }
    }
}

async function deleteMessage() {
    if (props.message.id !== undefined && props.conversationId) {
        try {
            await axios.delete(route('chat.messages.delete'), {
                data: {
                    conversation_id: props.conversationId,
                    message_id: props.message.id,
                },
            });

            console.log('ChatMessage deleted successfully:', props.message.id);
            emit('messageDeleted', props.message.id);
        } catch (error) {
            console.error('Failed to delete message:', error);
        }
    }
}

async function exportAs(format: 'pdf' | 'markdown' | 'text') {
    try {
        const url = route('conversations.export', {
            conversationId: props.conversationId,
            messageId: props.message.id,
            type: format,
        });
        window.open(url, '_blank');
    } catch (error) {
        console.error('Failed to export PDF:', error);
    }
}

function setupCodeBlockCopyButtons() {
    if (!messageContentRef.value) return;

    const codeBlocks = messageContentRef.value.querySelectorAll('pre');
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
    () => props.message.content,
    () => {
        if (props.message.content) {
            nextTick(() => setupCodeBlockCopyButtons());
        }
    },
    { immediate: true },
);

// Initialize feedback from message data
watch(
    () => props.message,
    (newMessage) => {
        if (newMessage.feedback !== undefined) {
            feedback.value = newMessage.feedback ? 'thumbs-up' : 'thumbs-down';
        }
    },
    { immediate: true },
);

onMounted(() => {
    if (props.message.content) {
        setupCodeBlockCopyButtons();
    }
});
</script>

<template>
    <div class="flex w-full items-start">
        <div class="max-w-full min-w-0 flex-1">
            <div class="prose prose-sm dark:prose-invert w-full max-w-none" :data-rtl="isRTL">
                <div
                    v-if="message.content"
                    id="chat-ai-responses"
                    ref="messageContentRef"
                    class="streaming-content"
                    :class="{ 'is-streaming': isStreaming }"
                    v-html="formatMessage(message.content)"
                ></div>
                <div v-else class="flex items-center gap-2 py-1">
                    <WaitingIndicator />
                </div>
            </div>

            <div v-if="message.content && !hideFeedback" class="mt-2 flex flex-wrap items-center justify-between gap-2">
                <div class="flex items-center gap-0.5">
                    <Button
                        size="icon"
                        variant="ghost"
                        class="text-muted-foreground hover:text-foreground hover:bg-muted/80 h-7 w-7"
                        @click="copyToClipboard"
                    >
                        <Check v-if="isCopied" class="h-3.5 w-3.5 text-green-500" />
                        <Copy v-else class="h-3.5 w-3.5" />
                    </Button>

                    <Button
                        size="icon"
                        variant="ghost"
                        class="text-muted-foreground hover:text-foreground hover:bg-muted/80 h-7 w-7"
                        :class="{ '!bg-green-500/10 !text-green-600': feedback === 'thumbs-up' }"
                        @click="provideFeedback('thumbs-up')"
                    >
                        <ThumbsUp class="h-3.5 w-3.5" />
                    </Button>

                    <Button
                        size="icon"
                        variant="ghost"
                        class="text-muted-foreground hover:text-foreground hover:bg-muted/80 h-7 w-7"
                        :class="{ '!bg-red-500/10 !text-red-600': feedback === 'thumbs-down' }"
                        @click="provideFeedback('thumbs-down')"
                    >
                        <ThumbsDown class="h-3.5 w-3.5" />
                    </Button>

                    <AlertDialog>
                        <AlertDialogTrigger asChild>
                            <Button size="icon" variant="ghost" class="text-muted-foreground h-7 w-7 hover:bg-red-500/10 hover:text-red-600">
                                <Trash2 class="h-3.5 w-3.5" />
                            </Button>
                        </AlertDialogTrigger>
                        <AlertDialogContent class="rounded-2xl">
                            <AlertDialogHeader>
                                <AlertDialogTitle>Delete this message?</AlertDialogTitle>
                                <AlertDialogDescription> This will also delete your original message. </AlertDialogDescription>
                            </AlertDialogHeader>
                            <AlertDialogFooter>
                                <AlertDialogCancel class="rounded-lg">Cancel</AlertDialogCancel>
                                <AlertDialogAction @click="deleteMessage" class="rounded-lg bg-red-600 hover:bg-red-700"> Delete </AlertDialogAction>
                            </AlertDialogFooter>
                        </AlertDialogContent>
                    </AlertDialog>

                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <Button size="icon" variant="ghost" class="text-muted-foreground hover:text-foreground hover:bg-muted/80 h-7 w-7">
                                <Download class="h-3.5 w-3.5" />
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="start" class="min-w-[140px]">
                            <DropdownMenuItem @click="exportAs('pdf')" class="text-sm">
                                <FileText class="mr-2 h-3.5 w-3.5 text-red-500" />
                                PDF
                            </DropdownMenuItem>
                            <DropdownMenuItem @click="exportAs('markdown')" class="text-sm">
                                <MessageSquare class="mr-2 h-3.5 w-3.5 text-blue-500" />
                                Markdown
                            </DropdownMenuItem>
                            <DropdownMenuItem @click="exportAs('text')" class="text-sm">
                                <FileText class="mr-2 h-3.5 w-3.5 text-gray-500" />
                                Text
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>

                    <!-- Regenerate button -->
                    <DropdownMenu v-if="hasMultipleModels && userMessageId !== undefined">
                        <DropdownMenuTrigger asChild>
                            <Button
                                size="icon"
                                variant="ghost"
                                class="text-muted-foreground hover:text-foreground hover:bg-muted/80 h-7 w-7"
                                :disabled="isRegenerating"
                            >
                                <RefreshCw class="h-3.5 w-3.5" :class="{ 'animate-spin': isRegenerating }" />
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="start" class="min-w-[180px]">
                            <DropdownMenuLabel class="text-muted-foreground text-xs">Regenerate with</DropdownMenuLabel>
                            <DropdownMenuSeparator />
                            <DropdownMenuItem v-for="model in models" :key="model.id" @click="regenerateWithModel(model)" class="text-sm">
                                <img v-if="model.icon" :src="`/images/llms/${model.icon}`" :alt="model.name" class="mr-2 h-4 w-4" />
                                {{ model.name }}
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                    <Button
                        v-else-if="userMessageId !== undefined"
                        size="icon"
                        variant="ghost"
                        class="text-muted-foreground hover:text-foreground hover:bg-muted/80 h-7 w-7"
                        :disabled="isRegenerating"
                        @click="regenerate"
                        title="Regenerate response"
                    >
                        <RefreshCw class="h-3.5 w-3.5" :class="{ 'animate-spin': isRegenerating }" />
                    </Button>
                </div>

                <div class="flex items-center gap-2">
                    <SourcesDialog v-if="sources && sources.length > 0" :sources="sources" />

                    <div v-if="modelUsed" class="text-muted-foreground flex items-center gap-1.5">
                        <img :src="`/images/llms/${modelUsed.icon}`" :alt="modelUsed.name" class="h-3 w-3 opacity-60" />
                        <span class="text-[11px]">{{ modelUsed.name }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* Prose customizations */
:deep(.prose) {
    font-size: 1rem;
    line-height: 1.75rem;
    color: hsl(var(--foreground));
}

:deep(.prose p) {
    margin-top: 0.5rem;
    margin-bottom: 0.5rem;
}

:deep(.prose p:first-child) {
    margin-top: 0;
}

:deep(.prose p:last-child) {
    margin-bottom: 0;
}

:deep(.prose pre) {
    background-color: hsl(var(--muted));
    border: 1px solid hsl(var(--border));
    margin: 0.75rem 0;
    position: relative;
    border-radius: 0.5rem;
}

:deep(.prose code) {
    font-size: 0.8rem;
}

:deep(.prose :not(pre) > code) {
    background-color: hsl(var(--muted));
    padding: 0.125rem 0.25rem;
    border-radius: 0.25rem;
    font-size: 0.8rem;
}

/* Code copy button */
:deep(.code-copy-button) {
    position: absolute;
    top: 0.5rem;
    right: 0.5rem;
    padding: 0.25rem;
    background-color: hsl(var(--background));
    border: 1px solid hsl(var(--border));
    border-radius: 0.25rem;
    color: hsl(var(--muted-foreground));
    cursor: pointer;
    opacity: 0;
    transition: all 0.2s ease;
    display: flex;
    align-items: center;
    justify-content: center;
}

:deep(pre:hover .code-copy-button) {
    opacity: 1;
}

:deep(.code-copy-button:hover) {
    background-color: hsl(var(--accent));
    color: hsl(var(--accent-foreground));
}

:deep(.code-copy-button.copied) {
    background-color: hsl(var(--success));
    color: white;
    border-color: hsl(var(--success));
}

/* Alert styling */
:deep(.markdown-alert) {
    padding: 0.75rem 1rem;
    margin: 0.75rem 0;
    border-left-width: 4px;
    border-left-style: solid;
    border-radius: 0.375rem;
}

/* RTL alert styling */
:deep([dir='rtl'] .markdown-alert) {
    border-left-width: 0;
    border-right-width: 4px;
    border-right-style: solid;
    padding-right: 1rem;
    padding-left: 1rem;
}

:deep(.markdown-alert-note) {
    border-left-color: hsl(var(--primary));
    background-color: hsl(var(--primary) / 0.1);
}

:deep(.markdown-alert-tip) {
    border-left-color: hsl(var(--success));
    background-color: hsl(var(--success) / 0.1);
}

:deep(.markdown-alert-important) {
    border-left-color: hsl(var(--warning));
    background-color: hsl(var(--warning) / 0.1);
}

:deep(.markdown-alert-warning) {
    border-left-color: hsl(var(--warning));
    background-color: hsl(var(--warning) / 0.1);
}

:deep(.markdown-alert-caution) {
    border-left-color: hsl(var(--destructive));
    background-color: hsl(var(--destructive) / 0.1);
}

:deep(.markdown-alert-title) {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-weight: 600;
    margin-bottom: 0.25rem;
}

/* Tables */
:deep(.prose table) {
    border-collapse: collapse;
    margin: 1rem 0;
    width: 100%;
}

:deep(.prose thead) {
    border-bottom: 2px solid hsl(var(--border));
}

:deep(.prose th) {
    font-weight: 600;
    padding: 0.75rem;
    text-align: left;
}

:deep(.prose td) {
    padding: 0.75rem;
    border-bottom: 1px solid hsl(var(--border));
}

:deep(.prose tbody tr:last-child td) {
    border-bottom: none;
}

/* Lists */
:deep(.prose ul) {
    list-style-type: disc;
    padding-left: 1.5rem;
}

:deep(.prose ol) {
    list-style-type: decimal;
    padding-left: 1.5rem;
}

:deep(.prose li) {
    margin: 0.25rem 0;
}

/* Blockquotes */
:deep(.prose blockquote) {
    border-left: 3px solid hsl(var(--muted-foreground) / 0.3);
    padding-left: 1rem;
    margin: 1rem 0;
    font-style: italic;
    color: hsl(var(--muted-foreground));
}

/* Links */
:deep(.prose a) {
    color: hsl(var(--primary));
    text-decoration: underline;
    text-underline-offset: 2px;
}

:deep(.prose a:hover) {
    text-decoration: none;
}

/* Headings */
:deep(.prose h1),
:deep(.prose h2),
:deep(.prose h3),
:deep(.prose h4),
:deep(.prose h5),
:deep(.prose h6) {
    font-weight: 600;
    margin-top: 1.5rem;
    margin-bottom: 0.5rem;
}

:deep(.prose h1) {
    font-size: 1.5rem;
}
:deep(.prose h2) {
    font-size: 1.25rem;
}
:deep(.prose h3) {
    font-size: 1.125rem;
}
:deep(.prose h4) {
    font-size: 1rem;
}

/* Images */
:deep(.prose img) {
    border-radius: 0.5rem;
    margin: 1rem 0;
    max-width: 100%;
    height: auto;
}

/* Horizontal rule */
:deep(.prose hr) {
    border: none;
    border-top: 1px solid hsl(var(--border));
    margin: 1.5rem 0;
}

/* RTL Support for specific content elements only */
:deep(.prose[data-rtl='true'] p),
:deep(.prose[data-rtl='true'] h1),
:deep(.prose[data-rtl='true'] h2),
:deep(.prose[data-rtl='true'] h3),
:deep(.prose[data-rtl='true'] h4),
:deep(.prose[data-rtl='true'] h5),
:deep(.prose[data-rtl='true'] h6) {
    text-align: right;
    direction: rtl;
}

/* Exclude math content from RTL styling */
:deep(.prose[data-rtl='true'] .katex-display),
:deep(.prose[data-rtl='true'] .katex) {
    direction: ltr !important;
    text-align: left !important;
}

/* Also target any paragraph that contains math */
:deep(.prose[data-rtl='true'] p .katex-display),
:deep(.prose[data-rtl='true'] p .katex) {
    direction: ltr !important;
    text-align: left !important;
}

:deep(.prose[data-rtl='true'] ul),
:deep(.prose[data-rtl='true'] ol) {
    padding-right: 1.5rem;
    padding-left: 0;
    direction: rtl;
}

:deep(.prose[data-rtl='true'] blockquote) {
    border-right: 3px solid hsl(var(--muted-foreground) / 0.3);
    border-left: none;
    padding-right: 1rem;
    padding-left: 0;
    direction: rtl;
}

:deep(.prose[data-rtl='true'] th),
:deep(.prose[data-rtl='true'] td) {
    text-align: right;
    direction: rtl;
}

:deep(.prose[data-rtl='true'] img) {
    direction: rtl;
}

/* Keep code blocks in LTR regardless of language */
:deep(.prose[data-rtl='true'] pre),
:deep(.prose[data-rtl='true'] code) {
    direction: ltr;
    text-align: left;
}

/* Streaming animation - blinking cursor */
.streaming-content.is-streaming::after {
    content: '▋';
    display: inline;
    margin-left: 2px;
    animation: blink 1s step-end infinite;
    color: hsl(var(--foreground));
    opacity: 0.7;
}

@keyframes blink {
    0%,
    100% {
        opacity: 0.7;
    }
    50% {
        opacity: 0;
    }
}
</style>
