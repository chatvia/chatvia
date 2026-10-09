<script setup lang="ts">
/**
 * Read-only message body for the chat log and the live-chat (handoff) panel:
 * the rendered markdown with its chart/form artifacts, or a waiting indicator
 * while the message is still empty. Prompts are rendered by the callers.
 */
import AssistantMessageBody from '@/components/chat/AssistantMessageBody.vue';
import WaitingIndicator from '@/components/chat/WaitingIndicator.vue';
import type { ToolArtifact } from '@/types/stream';

// Callers pass a ChatLogMessageData row; only these fields are read.
interface MessageLike {
    id: string | number;
    role: string;
    content: string;
    artifacts?: ToolArtifact[] | null;
}

defineProps<{
    message: MessageLike;
}>();
</script>

<template>
    <div :class="message.role === 'user' ? 'flex-row-reverse justify-end' : ''" class="mx-auto flex max-w-3xl items-start gap-4 sm:gap-6">
        <div :class="message.role === 'user' ? 'flex justify-end' : ''" class="prose dark:prose-invert max-w-none flex-1">
            <div
                :class="[
                    message.role === 'user'
                        ? 'group relative inline-block max-w-xl rounded-2xl bg-gray-800 px-4 py-2 text-base font-normal text-white shadow-sm'
                        : 'mx-auto my-4 block px-2 text-lg leading-relaxed text-gray-800 dark:text-white',
                ]"
            >
                <div>
                    <AssistantMessageBody
                        v-if="message.content"
                        :content="message.content"
                        :artifacts="message.artifacts ?? undefined"
                        :message-id="message.id ? String(message.id) : undefined"
                    />
                    <WaitingIndicator v-else />
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
:deep(.prose *) {
    transition:
        background-color 0.2s ease,
        color 0.2s ease,
        border-color 0.2s ease;
    overflow-wrap: break-word;
    word-wrap: break-word;
    word-break: break-word;
    hyphens: auto;
}

/* Images */
:deep(img) {
    max-width: 100%;
    height: auto;
    border-radius: 0.375rem;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    margin: 0.5rem 0;
}

/* Code blocks - improved for mobile */
:deep(pre) {
    background-color: rgba(0, 0, 0, 0.03);
    border: 1px solid rgba(0, 0, 0, 0.05);
    box-shadow: inset 0 1px 3px rgba(0, 0, 0, 0.05);
    margin: 1rem 0;
    padding: 1rem;
    border-radius: 0.375rem;
    overflow-x: auto;
    max-width: 100%;
    white-space: pre-wrap;
}

.dark :deep(pre) {
    background-color: rgba(255, 255, 255, 0.03);
    border-color: rgba(255, 255, 255, 0.05);
}

/* Code block content */
:deep(pre code) {
    display: block;
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, 'Liberation Mono', 'Courier New', monospace;
    font-size: 0.875em;
    line-height: 1.7;
}

/* Inline code */
:deep(:not(pre) > code) {
    padding: 0.2em 0.4em;
    background-color: rgba(0, 0, 0, 0.03);
    border-radius: 3px;
    font-size: 0.9em;
    border: 1px solid rgba(0, 0, 0, 0.05);
    white-space: normal;
}

.dark :deep(:not(pre) > code) {
    background-color: rgba(255, 255, 255, 0.03);
    border-color: rgba(255, 255, 255, 0.05);
}

/* Blockquotes */
:deep(blockquote) {
    border-inline-start: 3px solid #e5e7eb;
    padding-inline-start: 1rem;
    font-style: italic;
    color: rgba(75, 85, 99);
    margin: 1rem 0;
}

.dark :deep(blockquote) {
    border-inline-start-color: #4b5563;
    color: rgba(156, 163, 175);
}

/* Tables */
:deep(table) {
    border-collapse: separate;
    border-spacing: 0;
    border-radius: 0.375rem;
    overflow-x: auto;
    display: block;
    max-width: 100%;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    margin: 1rem 0;
}

:deep(th) {
    background-color: rgba(0, 0, 0, 0.03);
    font-weight: 600;
    text-align: start;
    padding: 0.75rem 1rem;
}

:deep(td) {
    padding: 0.75rem 1rem;
    border-top: 1px solid rgba(0, 0, 0, 0.05);
}

.dark :deep(th) {
    background-color: rgba(255, 255, 255, 0.03);
}

.dark :deep(td) {
    border-top-color: rgba(255, 255, 255, 0.05);
}

/* Links */
:deep(a) {
    transition:
        color 0.2s ease,
        text-decoration 0.2s ease;
}

:deep(a:hover) {
    text-decoration-thickness: 2px;
}

/* Lists */
:deep(ul),
:deep(ol) {
    padding-inline-start: 1.5rem;
    margin: 0.75rem 0;
}

:deep(li) {
    margin: 0.375rem 0;
    padding-inline-start: 0.25rem;
}

/* Headings */
:deep(h1),
:deep(h2),
:deep(h3),
:deep(h4),
:deep(h5),
:deep(h6) {
    font-weight: 600;
    margin-top: 1.5rem;
    margin-bottom: 0.75rem;
    line-height: 1.3;
}

:deep(h1) {
    font-size: 1.5rem;
}

:deep(h2) {
    font-size: 1.3rem;
}

:deep(h3) {
    font-size: 1.15rem;
}

:deep(h4) {
    font-size: 1.05rem;
}

/* Mobile-specific adjustments */
@media (max-width: 640px) {
    :deep(pre) {
        font-size: 0.8em;
    }

    :deep(table) {
        font-size: 0.85em;
    }

    :deep(h1) {
        font-size: 1.3rem;
    }

    :deep(h2) {
        font-size: 1.15rem;
    }

    :deep(h3) {
        font-size: 1.05rem;
    }

    :deep(h4) {
        font-size: 1rem;
    }
}

/* Ensure message container doesn't overflow */
.prose {
    max-width: 100%;
    overflow-wrap: break-word;
}

/* Code block copy button */
:deep(.code-copy-button) {
    position: absolute;
    top: 0.5rem;
    inset-inline-end: 0.5rem;
    padding: 0.25rem;
    background-color: rgba(255, 255, 255, 0.1);
    border: none;
    border-radius: 0.25rem;
    color: #6b7280;
    cursor: pointer;
    opacity: 0;
    transition:
        opacity 0.2s ease,
        background-color 0.2s ease;
    display: flex;
    align-items: center;
    justify-content: center;
}

:deep(pre:hover .code-copy-button) {
    opacity: 1;
}

:deep(.code-copy-button:hover) {
    background-color: rgba(255, 255, 255, 0.2);
    color: #4b5563;
}

:deep(.code-copy-button.copied) {
    background-color: rgba(16, 185, 129, 0.2);
    color: #10b981;
}

.dark :deep(.code-copy-button) {
    background-color: rgba(0, 0, 0, 0.2);
    color: #9ca3af;
}

.dark :deep(.code-copy-button:hover) {
    background-color: rgba(0, 0, 0, 0.3);
    color: #d1d5db;
}

.dark :deep(.code-copy-button.copied) {
    background-color: rgba(16, 185, 129, 0.2);
    color: #10b981;
}

/* GFM Alert Styling */
:deep(.markdown-alert) {
    padding: 0.75rem 1rem;
    margin: 1rem 0;
    border-inline-start-width: 4px;
    border-inline-start-style: solid;
    border-radius: 0.375rem;
}

/* Note alert styling */
:deep(.markdown-alert-note) {
    border-inline-start-color: #3b82f6; /* blue-500 */
    background-color: rgba(59, 130, 246, 0.05);
}

:deep(.markdown-alert-note .markdown-alert-title) {
    color: #2563eb; /* blue-600 */
}

/* Tip alert styling */
:deep(.markdown-alert-tip) {
    border-inline-start-color: #10b981; /* emerald-500 */
    background-color: rgba(16, 185, 129, 0.05);
}

:deep(.markdown-alert-tip .markdown-alert-title) {
    color: #059669; /* emerald-600 */
}

/* Important alert styling */
:deep(.markdown-alert-important) {
    border-inline-start-color: #8b5cf6; /* violet-500 */
    background-color: rgba(139, 92, 246, 0.05);
}

:deep(.markdown-alert-important .markdown-alert-title) {
    color: #7c3aed; /* violet-600 */
}

/* Warning alert styling */
:deep(.markdown-alert-warning) {
    border-inline-start-color: #f59e0b; /* amber-500 */
    background-color: rgba(245, 158, 11, 0.05);
}

:deep(.markdown-alert-warning .markdown-alert-title) {
    color: #d97706; /* amber-600 */
}

/* Caution alert styling */
:deep(.markdown-alert-caution) {
    border-inline-start-color: #ef4444; /* red-500 */
    background-color: rgba(239, 68, 68, 0.05);
}

:deep(.markdown-alert-caution .markdown-alert-title) {
    color: #dc2626; /* red-600 */
}

:deep(.markdown-alert-title) {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-weight: 600;
    margin-bottom: 0.5rem;
}

:deep(.markdown-alert-title svg) {
    width: 1.25rem;
    height: 1.25rem;
    flex-shrink: 0;
}
</style>
