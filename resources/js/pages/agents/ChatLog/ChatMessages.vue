<script lang="ts" setup>
/**
 * Messages tab of the chat log: the selected conversation's transcript (with
 * search highlighting), per-message tone, sentiment, confidence, tokens and
 * feedback, the "Revise" shortcut, and paging.
 */
import MessageCard from '@/components/chat/MessageCard.vue';
import { Button } from '@/components/ui/button';
import { ScrollArea } from '@/components/ui/scroll-area';
import { usePage } from '@inertiajs/vue3';
import { Activity, AudioLines, LoaderCircle, MessageSquare, RotateCcw, Search, ThumbsDown, ThumbsUp } from 'lucide-vue-next';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { useChatLog } from './useChatLog';

type Message = Domain.Agent.Data.Resources.ChatLog.Messages.ChatLogMessageData;

const { t } = useI18n();

const emit = defineEmits<{
    (e: 'open-revise', message: Message): void;
}>();

const {
    currentConversationId,
    messagesData,
    messages,
    isLoadingMessages,
    search,
    fetchMessages,
    formatTimeAgo,
    formatFullDateTime,
    highlightText,
    getToneDisplayName,
    getToneBadgeColor,
    getConfidenceColor,
    getSentimentDisplayName,
} = useChatLog();

function clearSearch() {
    search.value = '';
}

// The confidence of the knowledge behind an answer; null when the answer used none.
function getConfidenceScore(message: Message): number {
    return message.confidence_score ?? 0;
}

function formatConfidenceScore(score: number): string {
    return score.toFixed(2);
}

// Null unless the viewer may open /spectra (an admin-panel session, or a
// workspace granted Spectra access) and the reply carries a trace.
const page = usePage();

function getTraceUrl(message: Message): string | null {
    const base = (page.props as { spectraTraceUrl?: string | null }).spectraTraceUrl;
    if (!base || !message.trace_id) return null;
    return `${base}?trace_id=${message.trace_id}`;
}

function getTotalTokens(message: Message): number | null {
    if (message.role !== 'assistant') return null;
    const total = (message.prompt_tokens ?? 0) + (message.completion_tokens ?? 0);
    return total > 0 ? total : null;
}

function formatTokens(value: number): string {
    return value.toLocaleString();
}

function getFeedbackInfo(feedback: string | null): { color: string; label: string } | null {
    if (!feedback) return null;
    if (feedback === 'positive') {
        return { color: 'text-green-600 dark:text-green-400', label: t('agents.chatlog.messages.positive_feedback') };
    }
    return { color: 'text-red-600 dark:text-red-400', label: t('agents.chatlog.messages.negative_feedback') };
}

function openReviseDialog(message: Message) {
    emit('open-revise', message);
}

const hasMorePages = computed(() => (messagesData.value?.last_page ?? 0) > 1);

function goToPage(offset: number) {
    const current = messagesData.value?.current_page ?? 1;
    if (currentConversationId.value) void fetchMessages(currentConversationId.value, current + offset);
}
</script>

<template>
    <ScrollArea class="h-0 flex-1">
        <!-- Loading State -->
        <div v-if="isLoadingMessages" class="flex h-full items-center justify-center py-20">
            <LoaderCircle class="text-muted-foreground h-6 w-6 animate-spin" />
        </div>

        <!-- Empty States -->
        <div v-else-if="!currentConversationId" class="text-muted-foreground flex h-full flex-col items-center justify-center py-20">
            <MessageSquare class="mb-3 h-10 w-10 opacity-30" />
            <p class="text-sm">{{ t('agents.chatlog.messages.select_conversation') }}</p>
        </div>
        <div v-else-if="messages.length === 0 && !search" class="text-muted-foreground flex h-full flex-col items-center justify-center py-20">
            <MessageSquare class="mb-3 h-10 w-10 opacity-30" />
            <p class="text-sm">{{ t('agents.chatlog.messages.no_messages') }}</p>
        </div>
        <div v-else-if="messages.length === 0 && search" class="text-muted-foreground flex h-full flex-col items-center justify-center py-20">
            <Search class="mb-3 h-10 w-10 opacity-30" />
            <p class="text-sm">{{ t('agents.chatlog.messages.no_results') }}</p>
            <Button variant="link" size="sm" @click="clearSearch" class="mt-1 h-auto p-0"> {{ t('agents.chatlog.messages.clear_search') }} </Button>
        </div>

        <!-- Messages List -->
        <div v-else class="space-y-5 px-6 py-5">
            <div v-for="m in messages" :key="m.id">
                <div class="flex" :class="m.role === 'user' ? 'justify-end' : 'justify-start'">
                    <div class="flex max-w-[75%] flex-col gap-2" :class="m.role === 'user' ? 'items-end' : 'items-start'">
                        <!-- Message Bubble -->
                        <div
                            class="rounded-2xl px-4 py-3"
                            :class="m.role === 'user' ? 'bg-primary text-primary-foreground rounded-ee-md' : 'bg-muted/60 rounded-es-md'"
                        >
                            <div v-if="search" v-html="highlightText(m.content, search)" class="prose dark:prose-invert max-w-none"></div>
                            <div v-else-if="m.role === 'user'" class="text-base leading-relaxed break-words whitespace-pre-wrap">{{ m.content }}</div>
                            <MessageCard v-else :message="m" />
                        </div>

                        <!-- Meta Information -->
                        <div class="text-muted-foreground flex flex-wrap items-center gap-2 px-1 text-xs">
                            <!-- Tone (for user messages) -->
                            <span
                                v-if="m.role === 'user' && m.tone"
                                :class="getToneBadgeColor(m.tone)"
                                class="rounded px-1.5 py-0.5 text-[10px] font-medium"
                            >
                                {{ getToneDisplayName(m.tone) }}
                            </span>

                            <span v-if="m.role === 'user' && m.tone && m.sentiment" class="text-muted-foreground/30">•</span>

                            <!-- Sentiment (for user messages) -->
                            <span
                                v-if="m.role === 'user' && m.sentiment"
                                class="capitalize"
                                :class="{
                                    'text-green-600 dark:text-green-400': m.sentiment === 'positive',
                                    'text-red-600 dark:text-red-400': m.sentiment === 'negative',
                                    'text-muted-foreground': m.sentiment === 'neutral',
                                }"
                            >
                                {{ getSentimentDisplayName(m.sentiment) }}
                            </span>

                            <span v-if="m.role === 'user' && (m.tone || m.sentiment)" class="text-muted-foreground/30">•</span>

                            <!-- Confidence (for assistant) -->
                            <template v-if="m.role === 'assistant' && m.confidence_score !== null">
                                <span class="group relative cursor-help tabular-nums" :class="getConfidenceColor(getConfidenceScore(m))">
                                    {{ formatConfidenceScore(getConfidenceScore(m)) }}
                                    <span
                                        class="pointer-events-none absolute start-0 bottom-full z-50 mb-1.5 w-64 rounded bg-gray-900 px-2 py-1 text-start text-[10px] text-white opacity-0 shadow-lg transition-opacity group-hover:opacity-100"
                                    >
                                        <span class="block font-medium">
                                            {{ t('agents.chatlog.messages.confidence', { percent: (getConfidenceScore(m) * 100).toFixed(0) }) }}
                                        </span>
                                        <span class="mt-0.5 block text-white/80">{{ t('agents.chatlog.messages.confidence_help') }}</span>
                                    </span>
                                </span>
                                <span class="text-muted-foreground/30">•</span>
                            </template>

                            <!-- Tokens (for assistant) -->
                            <template v-if="m.role === 'assistant' && getTotalTokens(m) !== null">
                                <span class="group relative cursor-help tabular-nums">
                                    {{ t('agents.chatlog.messages.tokens', { count: formatTokens(getTotalTokens(m)!) }) }}
                                    <span
                                        class="pointer-events-none absolute start-0 bottom-full z-50 mb-1.5 rounded bg-gray-900 px-2 py-1 text-[10px] whitespace-nowrap text-white opacity-0 shadow-lg transition-opacity group-hover:opacity-100"
                                    >
                                        {{
                                            t('agents.chatlog.messages.tokens_breakdown', {
                                                prompt: formatTokens(m.prompt_tokens ?? 0),
                                                completion: formatTokens(m.completion_tokens ?? 0),
                                            })
                                        }}
                                    </span>
                                </span>
                                <span class="text-muted-foreground/30">•</span>
                            </template>

                            <!-- Spectra trace (shown only to staff who may open /spectra) -->
                            <template v-if="m.role === 'assistant' && getTraceUrl(m)">
                                <a
                                    :href="getTraceUrl(m)!"
                                    target="_blank"
                                    rel="noopener"
                                    class="group hover:text-foreground relative inline-flex items-center gap-1 transition-colors"
                                >
                                    <Activity class="h-3 w-3" aria-hidden="true" />
                                    {{ t('agents.chatlog.messages.trace') }}
                                    <span
                                        class="pointer-events-none absolute start-0 bottom-full z-50 mb-1.5 rounded bg-gray-900 px-2 py-1 text-[10px] whitespace-nowrap text-white opacity-0 shadow-lg transition-opacity group-hover:opacity-100"
                                    >
                                        {{ t('agents.chatlog.messages.trace_help') }}
                                    </span>
                                </a>
                                <span class="text-muted-foreground/30">•</span>
                            </template>

                            <!-- Spoken in a voice call -->
                            <template v-if="m.voice">
                                <span class="inline-flex items-center gap-1">
                                    <AudioLines class="h-3 w-3" aria-hidden="true" />
                                    {{ t('agents.chatlog.messages.voice') }}
                                </span>
                                <span class="text-muted-foreground/30">•</span>
                            </template>

                            <!-- Playground indicator -->
                            <template v-if="m.source === 'playground'">
                                <span class="font-medium text-amber-600 dark:text-amber-400">{{ t('agents.chatlog.messages.playground') }}</span>
                                <span class="text-muted-foreground/30">•</span>
                            </template>

                            <!-- Time -->
                            <span class="group relative cursor-help">
                                {{ formatTimeAgo(m.created_at) }}
                                <span
                                    class="pointer-events-none absolute bottom-full z-50 mb-1.5 rounded bg-gray-900 px-2 py-1 text-[10px] whitespace-nowrap text-white opacity-0 shadow-lg transition-opacity group-hover:opacity-100"
                                    :class="m.role === 'user' ? 'end-0' : 'start-0'"
                                >
                                    {{ formatFullDateTime(m.created_at) }}
                                </span>
                            </span>

                            <!-- Feedback (for assistant) -->
                            <template v-if="m.role === 'assistant' && m.feedback">
                                <span class="text-muted-foreground/30">•</span>
                                <div class="group relative cursor-help" :class="getFeedbackInfo(m.feedback)?.color">
                                    <ThumbsUp v-if="m.feedback === 'positive'" class="h-3 w-3" />
                                    <ThumbsDown v-else class="h-3 w-3" />
                                    <span
                                        class="pointer-events-none absolute start-0 bottom-full z-50 mb-1.5 rounded bg-gray-900 px-2 py-1 text-[10px] whitespace-nowrap text-white opacity-0 shadow-lg transition-opacity group-hover:opacity-100"
                                    >
                                        {{ getFeedbackInfo(m.feedback)?.label }}
                                    </span>
                                </div>
                            </template>

                            <!-- Q&A Status (for assistant) -->
                            <template v-if="m.role === 'assistant'">
                                <span class="text-muted-foreground/30">•</span>
                                <span
                                    v-if="m.is_response_from_qa"
                                    class="inline-flex items-center gap-1 rounded bg-green-100 px-1.5 py-0.5 font-medium text-green-700 dark:bg-green-900/40 dark:text-green-300"
                                >
                                    ✓ {{ t('agents.chatlog.messages.qa_matched') }}
                                </span>
                                <span
                                    v-else-if="m.agent_question_answer_id"
                                    class="inline-flex items-center gap-1 rounded bg-green-100 px-1.5 py-0.5 font-medium text-green-700 dark:bg-green-900/40 dark:text-green-300"
                                >
                                    ✓ {{ t('agents.chatlog.messages.revised') }}
                                </span>
                                <button
                                    v-else
                                    class="text-muted-foreground hover:text-foreground flex cursor-pointer items-center gap-0.5 transition-colors"
                                    @click="openReviseDialog(m)"
                                >
                                    <RotateCcw class="h-2.5 w-2.5" />
                                    <span>{{ t('agents.chatlog.messages.revise') }}</span>
                                </button>
                            </template>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </ScrollArea>

    <!-- Pagination -->
    <div v-if="currentConversationId && hasMorePages" class="flex-shrink-0 border-t p-3">
        <div class="flex items-center justify-between">
            <Button variant="ghost" size="sm" class="h-8 text-xs" :disabled="!messagesData?.prev_page_url || isLoadingMessages" @click="goToPage(-1)">
                <LoaderCircle v-if="isLoadingMessages" class="me-1 h-3 w-3 animate-spin" />
                {{ t('agents.chatlog.pagination.prev') }}
            </Button>
            <span class="text-muted-foreground text-[11px] tabular-nums"> {{ messagesData?.current_page }} / {{ messagesData?.last_page }} </span>
            <Button variant="ghost" size="sm" class="h-8 text-xs" :disabled="!messagesData?.next_page_url || isLoadingMessages" @click="goToPage(1)">
                <LoaderCircle v-if="isLoadingMessages" class="me-1 h-3 w-3 animate-spin" />
                {{ t('agents.chatlog.pagination.next') }}
            </Button>
        </div>
    </div>
</template>
