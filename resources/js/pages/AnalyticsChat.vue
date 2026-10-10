<script setup lang="ts">
/**
 * "Ask your data" — an ephemeral, owner-facing analytics chat. Reuses the
 * hosted-chat streaming pipeline (useChatStream + AssistantMessageBody +
 * interactive charts) but stores nothing: the transcript lives only in this
 * component and is replayed to the server as `history` each turn. Clearing it
 * (reload / navigate away) wipes it.
 */
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import AssistantMessageBody from '@/components/chat/AssistantMessageBody.vue';
import WaitingIndicator from '@/components/chat/WaitingIndicator.vue';
import { Button } from '@/components/ui/button';
import { useChatStream } from '@/composables/useChatStream';
import AppLayout from '@/layouts/AppLayout.vue';
import { stream as streamAnalyticsChat } from '@/routes/analytics/chat';
import type { BreadcrumbItem } from '@/types';
import type { StreamToolData, ToolArtifact } from '@/types/stream';
import { Head } from '@inertiajs/vue3';
import { MessagesSquare, Radio, Send, UserPlus, UserRound } from 'lucide-vue-next';
import { computed, nextTick, ref } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

interface ChatMessage {
    id: string;
    role: 'user' | 'assistant';
    content: string;
    artifacts: ToolArtifact[];
}

const breadcrumbs: BreadcrumbItem[] = [{ title: t('analytics_chat.title'), href: '/my/ask' }];

const STARTERS = [
    { text: t('analytics_chat.starter_messages'), icon: MessagesSquare },
    { text: t('analytics_chat.starter_active_user'), icon: UserRound },
    { text: t('analytics_chat.starter_busiest_channel'), icon: Radio },
    { text: t('analytics_chat.starter_leads'), icon: UserPlus },
];

const { state, start } = useChatStream();
const messages = ref<ChatMessage[]>([]);
const input = ref('');
const streamingId = ref<string | null>(null);
const scrollEl = ref<HTMLElement | null>(null);

const isStreaming = computed(() => state.value === 'connecting' || state.value === 'streaming');
const canSend = computed(() => input.value.trim().length > 0 && !isStreaming.value);

function scrollToBottom(): void {
    nextTick(() => {
        if (scrollEl.value) scrollEl.value.scrollTop = scrollEl.value.scrollHeight;
    });
}

function find(id: string): ChatMessage | undefined {
    return messages.value.find((m) => m.id === id);
}

function send(text?: string): void {
    const message = (text ?? input.value).trim();
    if (!message || isStreaming.value) return;

    messages.value.push({ id: `u-${messages.value.length}-${message.length}`, role: 'user', content: message, artifacts: [] });

    // Replay the prior turns as context (the chat is stateless server-side).
    const history = messages.value
        .filter((m) => m.content !== '')
        .slice(-20)
        .map((m) => ({ role: m.role, content: m.content }));

    input.value = '';
    scrollToBottom();

    start(streamAnalyticsChat.url(), JSON.stringify({ message, history }), {
        onStart: (data) => {
            streamingId.value = data.assistant_message_id;
            messages.value.push({ id: data.assistant_message_id, role: 'assistant', content: '', artifacts: [] });
            scrollToBottom();
        },
        onDelta: (chunk, id) => {
            const m = find(id);
            if (m) {
                m.content += chunk;
                scrollToBottom();
            }
        },
        onTool: (data: StreamToolData, id) => {
            if (data.phase === 'end' && Array.isArray(data.artifacts) && data.artifacts.length > 0) {
                const m = find(id);
                if (m) m.artifacts = [...m.artifacts, ...data.artifacts];
                scrollToBottom();
            }
        },
        onError: (err, id) => {
            const m = id ? find(id) : undefined;
            if (m && m.content === '') m.content = `⚠️ ${err.message}`;
            streamingId.value = null;
        },
        onDone: () => {
            streamingId.value = null;
        },
    });
}

function onComposerKeydown(e: KeyboardEvent): void {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        send();
    }
}
</script>

<template>
    <Head :title="t('analytics_chat.title')" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex h-[calc(100vh-8rem)] w-full max-w-3xl flex-col px-4">
            <!-- Transcript -->
            <div ref="scrollEl" class="flex-1 space-y-6 overflow-y-auto py-6">
                <!-- Empty state -->
                <div v-if="messages.length === 0" class="flex h-full flex-col items-center justify-center text-center">
                    <AppLogoIcon :aria-label="t('analytics_chat.logo_alt')" class="mb-5 size-14" />
                    <h1 class="text-xl font-semibold">{{ t('analytics_chat.title') }}</h1>
                    <p class="text-muted-foreground mx-auto mt-1.5 max-w-md text-sm">
                        {{ t('analytics_chat.subtitle') }}
                    </p>

                    <div class="mt-6 grid w-full max-w-xl grid-cols-1 gap-2 sm:grid-cols-2">
                        <button
                            v-for="s in STARTERS"
                            :key="s.text"
                            class="group border-border bg-card hover:bg-accent flex items-center gap-3 rounded-lg border px-3 py-2.5 text-start text-sm transition-colors"
                            @click="send(s.text)"
                        >
                            <span
                                class="bg-muted text-muted-foreground group-hover:text-foreground flex size-7 shrink-0 items-center justify-center rounded-md transition-colors"
                            >
                                <component :is="s.icon" class="size-4" />
                            </span>
                            <span>{{ s.text }}</span>
                        </button>
                    </div>
                </div>

                <!-- Messages -->
                <div v-for="m in messages" :key="m.id" class="flex" :class="m.role === 'user' ? 'justify-end' : 'justify-start'">
                    <div
                        v-if="m.role === 'user'"
                        class="bg-primary text-primary-foreground max-w-[85%] rounded-2xl rounded-ee-sm px-4 py-2 text-sm whitespace-pre-wrap"
                    >
                        {{ m.content }}
                    </div>
                    <div v-else class="w-full max-w-full">
                        <WaitingIndicator v-if="m.content === '' && m.artifacts.length === 0" />
                        <AssistantMessageBody v-else :content="m.content" :artifacts="m.artifacts" :is-streaming="m.id === streamingId" />
                    </div>
                </div>
            </div>

            <!-- Composer -->
            <div class="border-border bg-background sticky bottom-0 border-t py-3">
                <div class="border-input focus-within:ring-ring flex items-end gap-2 rounded-2xl border px-3 py-2 focus-within:ring-1">
                    <textarea
                        v-model="input"
                        rows="1"
                        :placeholder="t('analytics_chat.composer_placeholder')"
                        class="max-h-40 flex-1 resize-none bg-transparent py-1 text-sm outline-none"
                        @keydown="onComposerKeydown"
                    />
                    <Button size="icon" class="size-8 shrink-0 rounded-full" :aria-label="t('messages.send')" :disabled="!canSend" @click="send()">
                        <Send class="size-4" />
                    </Button>
                </div>
                <p class="text-muted-foreground mt-1.5 text-center text-xs">{{ t('analytics_chat.disclaimer') }}</p>
            </div>
        </div>
    </AppLayout>
</template>
